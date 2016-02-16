<?php

include 'Work.php';
include 'Http.php';
include 'FcgiClient.php';


class Master
{

    //可使用最大内存
    private $memoryLimit = '1024M';
    //默认子进程数
    private $defaultWorkNum = 3;
    //主进程pid
    private $mpid;
    private $mpidFile;
    //子进程集合
    private $pids = array();

    public function __construct()
    {
        //启动时间
        $this->startime = time();
        //使用内存限制
        ini_set('memory_limit', $this->memoryLimit);
        //主进程pid
//        $this->mpid = posix_getpid();
    }

    public function start($port)
    {
//        error_log("14\n",3,'/tmp/mytest');
        $socket = stream_socket_server ('tcp://0.0.0.0:' . $port, $errno, $errstr);
        stream_set_blocking($socket, 0);

        $worker = new Worker($socket);
        //创建默认子进程
        for($i = 1; $i <= $this->defaultWorkNum; $i++) {
            $this->pids[$i] = $worker->createOneWork();
        }
        //设置信号
        $this->setSignal();

        while (true) {
            //分发信号，使安装的信号处理器能接收。
            //低于php5.3该函数无效，但有开头的declare (ticks = 1);表示每执行一条低级指令，
            pcntl_signal_dispatch();
            //就检查一次信号，如果检测到注册的信号，就调用其信号处理器
            sleep(mt_rand(3, 5));//防止100%占用
        }
    }


    /**
     * 设置信号
     */
    private function setSignal()
    {
        //安装信号处理器
        pcntl_signal(SIGTERM, array($this, "sig_handler"));//进程被kill时发出的信号
        // pcntl_signal(SIGHUP,  "sig_handler");//终端关闭时发出的信号
//        pcntl_signal(SIGINT, array(&$this, "sig_handler"));//中断进程信号，如Ctrl+C
//        pcntl_signal(SIGCHLD, array($this, "sig_handler"));//进程退出信号
    }

    /**
     * 信号处理函数
     * @param $sig
     */
    private function sig_handler($sig)
    {
//        global $child;
        $child = 1;
        switch ($sig) {
            case SIGCHLD:
//                $child--;
                echo 'SIGCHLD received! now we have '.$child.' process'.PHP_EOL;
                break;
            case SIGINT:
//                $child--;
                echo 'SIGINT received! now we have '.$child.' process'.PHP_EOL;
                break;
            case SIGTERM:
//                $child--;
                echo 'SIGTERM received! now we have '.$child.' process'.PHP_EOL;
                break;
            default:
                # code...
                break;
        }
    }

    private function createMpidFile() {
        if (file_put_contents($this->mpidFile, $this->mpid) == false) {
            throw new Exception("创建master的pid文件失败");
        }
    }

}
$a = new Master;
$a->start(2000);
//Master::start(2000);


