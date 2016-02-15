<?php

include 'Work.php';
include 'Http.php';
include 'FcgiClient.php';


class Master
{

    private static $connections = array();
    private static $buffers = array();
    private static $remote_address = array();


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
        //创建并且初始事件
        $base = event_base_new();
        //创建一个新的事件
        $event = event_new();
        //准备想要在event_add中添加事件
        event_set($event, $socket, EV_READ | EV_PERSIST, array(__CLASS__, 'ev_accept'), $base);
        //关联事件到事件base
        event_base_set($event, $base);
        //向指定的设置中添加一个执行事件
        event_add($event);
        //处理事件，根据指定的base来处理事件循环
        event_base_loop($base);

    }

    public function ev_accept($socket, $flag, $base)
    {

        static $id = 0;
        $connection = stream_socket_accept($socket);
        stream_set_blocking($connection, 0);

        $id += 1;
        //建立一个新的缓存事件
        $buffer = event_buffer_new($connection, array(__CLASS__, 'ev_read'), NULL, array(__CLASS__, 'ev_error'),  $id);
        //关联缓存的事件到event_base
        event_buffer_base_set($buffer, $base);
        //给一个缓存的事件设定超时的读写时间
        event_buffer_timeout_set($buffer, 30, 30);
        //设置读写事件的水印标记
        event_buffer_watermark_set($buffer, EV_READ, 0, 0xffffff);
        //缓存事件的优先级设定
        event_buffer_priority_set($buffer, 10);
        //启用一个指定的缓存的事件
        event_buffer_enable($buffer, EV_READ | EV_PERSIST);

        self::$connections[$id] = $connection;
        self::$buffers[$id] = $buffer;
    }

    public function ev_read($buffer, $id)
    {
        echo 111;exit;
    }

    public function ev_error($buffer, $error, $id)
    {
        event_buffer_disable(self::$buffers[$id], EV_READ | EV_WRITE);
        event_buffer_free(self::$buffers[$id]);
        fclose(self::$connections[$id]);
        unset(self::$buffers[$id], self::$connections[$id]);
    }



/*
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
*/

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


