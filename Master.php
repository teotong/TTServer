<?php
namespace TTServer;

require 'Worker.php';

class Master
{
    private $config = array();
    //主进程pid
    private $mpid;
    private $mpidFile;
    //子进程集合
    private $pids = array();

    public function __construct($config)
    {
        //判断主进程是否已经启动
        $this->masterIsRunning();

        $this->config = $config;
        //启动时间
        $this->startime = time();
        //使用内存限制
        ini_set('memory_limit', $config['run']['memory_limit']);
        //主进程pid
        $this->mpid = posix_getpid();
//        $this->mpid = getmypid();


//        error_log("日志\n",3,'/tmp/mytest');
    }

    private function __init()
    {
        //创建master的pid文件
        $this->createMpidFile();
    }

    public function start()
    {
        //初始化
        $this->__init();

        $socket = stream_socket_server("tcp://{$this->config['master']['host']}:" . $this->config['master']['port'], $errno, $errstr);
        stream_set_blocking($socket, 0);

        $this->worker = new Worker($socket, $this->config);
        //创建默认子进程
        for ($i = 1; $i <= $this->config['worker']['count']; $i++) {
            $this->pids[$i] = $this->worker->createOneWork();
        }
        //设置信号
        $this->setSignal();

        //设置第一次心跳检测的时间
        $last_check_heartbeat_time = time();

        while (true) {
            //分发信号，使安装的信号处理器能接收。
            //低于php5.3该函数无效，但有开头的declare (ticks = 1);表示每执行一条低级指令，
            //就检查一次信号，如果检测到注册的信号，就调用其信号处理器
            pcntl_signal_dispatch();

            if($last_check_heartbeat_time - time() > 2) {
                $this->checkWorkerStatus();
                $last_check_heartbeat_time = time();
            }

//            sleep(mt_rand(3, 5));//防止100%占用
            usleep(1000);//防止100%占用
        }
    }


    /**
     * 设置信号
     */
    public function setSignal()
    {
        //安装信号处理器
//        pcntl_signal(SIGCHLD, SIG_IGN); //子进程退出信号
        pcntl_signal(SIGCHLD, array($this, "sig_handler")); //子进程退出信号
        pcntl_signal(SIGPIPE, SIG_IGN);
        pcntl_signal(SIGTERM, array($this, "sig_handler"));//进程被kill时发出的信号
        pcntl_signal(SIGHUP, array($this, "sig_handler"));//终端关闭时发出的信号
        pcntl_signal(SIGINT, array($this, "sig_handler"));//中断进程信号，如Ctrl+C
        pcntl_signal(SIGUSR1, array($this, "sig_handler"));//用户自定义信号
    }

    /**
     * 信号处理函数
     * @param $sig
     */
    public function sig_handler($sig)
    {
        switch ($sig) {
            case SIGTERM:
            case SIGHUP:
            case SIGINT:
                //关闭主进程
                $this->shutdown();
                break;
            case SIGUSR1:
                #TODO 接受子进程发送的自定义信号 做一些事
                break;
            case SIGCHLD:
                //子进程退出前都会向主进程发送一个SIGCHLD信号
                $p = pcntl_waitpid(-1, $status, WNOHANG);
                if($p > 0){
                    echo " Reaped zombie child " . $p . "\n";
                }
                unset($this->pids[array_search($p, $this->pids)]);
                $this->pids[] = $this->worker->createOneWork();
                break;
            default:
                # code...
                break;
        }
    }

    //创建master的pid文件
    public function createMpidFile()
    {
        $this->mpidFile = $this->config['run']['working_dir'] . '/TTServer_master.pid';
        if (file_put_contents($this->mpidFile, $this->mpid) == false) {
            throw new Exception("创建master的pid文件失败");
        }
    }

    //关闭主进程
    public function shutdown()
    {
        unlink($this->mpidFile);
        //关闭所有子进程
        foreach($this->pids as $pid) {
            posix_kill($pid, SIGTERM);
        }
        #TODO 发邮件给管理员
        exit;
    }

    //查看进程状态
    public function getStatus($pid)
    {
        $file = "/proc/{$pid}/status";

        if (file_exists($file) === false) {
            return false;
        }

        $data = array();
        $lines = file($file);
        foreach ($lines as $line) {
            $line = trim($line);
            list($name, $value) = explode(':', $line);
            $data[trim($name)] = trim($value);
        }

        return $data;
    }

    //判断主进程是否已经启动
    private function masterIsRunning()
    {
        if (file_exists($this->mpidFile) == false) return;

        $lastPid = file_get_contents($this->mpidFile);
        $masterStatus = $this->getStatus($lastPid);

        if ($masterStatus === false) {
            unlink($this->mpidFile);
        } else {
            exit("TTServer is already running");
        }
    }

    //检查子进程状态
    private function checkWorkerStatus()
    {
        foreach($this->pids as $key => $pid) {
            $workStatus = $this->getStatus($pid);
            //如果此子进程已经不运行 则杀掉子进程
            if($workStatus === false) {
                unset($this->pids[$key]);
                $this->pids[] = $this->worker->createOneWork();
            }
            //State的状态
            //R (running)", "S (sleeping)", "D (disk sleep)", "T (stopped)", "T(tracing stop)", "Z (zombie)", or "X (dead)"
            switch ($workStatus['State']) {
                //如果是僵死
                case 'Z (zombie)' :
                    //回收子进程
                    pcntl_waitpid($pid, $status, WNOHANG);
                    unset($this->pids[$key]);
                    $this->pids[] = $this->worker->createOneWork();
                    break;
                default :
                    break;
            }
//                posix_kill($pid, SIGINT);
        }
    }

}




