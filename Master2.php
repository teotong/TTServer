<?php
/**
 * Created by PhpStorm.
 * User: tong
 * Date: 15/12/31
 * Time: 下午3:47
 */
include 'Work.php';
class Master
{
    private static $connections = array();
    private static $buffers = array();
    //默认子进程数
    private $defaultWorkNum = 2;
    //可使用最大内存
    private $memoryLimit = '1024M';
    //子进程集合
    private $pids = array();

    public function __construct()
    {
        //启动时间
        $this->startime = time();
        //创建子进程
//        $this->createWork($this->defaultWorkNum);
        //使用内存限制
        ini_set('memory_limit', $this->memoryLimit);

    }

    /**
     * 创建子进程
     * @param $num
     */
    public function createWork($num)
    {
        for($i = 1; $i < $num + 1; $i++) {
            $this->createOneWork($i);
        }
    }

    /**
     * 创建一个子进程
     * @param $i
     */
    public function createOneWork($i)
    {
                $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        stream_set_blocking($sockets[0], 0);
        $read[0] = $sockets[0];
//        list($up, $down) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
//        stream_set_blocking($down, 0);
//        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
//        fwrite($down, $str);
        $pid = pcntl_fork();
        if ($pid < 0) {
            printf("fork failed: %s\n", $pid);
        } elseif ($pid) {
            $this->pids[$i]['pid'] = $pid;
            $this->pids[$i]['channel'] = $sockets[1];
        } else {


//            fclose($up);


//            $work = new Work($sockets);
//            $work->start();
//            Work::hahastart();


//            while (true) {
//                sleep(1);
//                // do something
////                echo 111;
//
//                pcntl_signal_dispatch(); // 接收到信号时，调用注册的signalHandler()
//            }

            // 信号注册：当接收到SIGINT信号时，调用signalHandler()函数
//            pcntl_signal(SIGUSR1, 'signalHandler');
//            pcntl_signal(SIGUSR1, function ($signal) use ($sockets) {
            pcntl_signal(SIGUSR1, function ($signal) {
                //TODO 子进程统计
                if ($signal == SIGUSR1) {


                    /*
                    fwrite($down, 1);
                    $buffer =  fgets($down);
                    var_dump($buffer);exit;
                    $headers = '';
                    while ($read = event_buffer_read($buffer, 1024)) {
//            $ct += strlen($read);
                        $headers .= $read;
                    }
                    var_dump($headers);exit;
//                    fclose($sockets[1])
                    $content = stream_get_contents($down);
                    fclose($down);

                    if ($content) {
//                        $str = 'signal received' .$id. PHP_EOL;
//                        fwrite($sockets[0], $str);
                        fwrite($down, serialize($content));
//                        $shell->setScopeVariables(@unserialize($content));
                    }
                    fwrite($down, 111);


//                    fclose($sockets[0]);
                    */
                }
            });

            while (true) {
                sleep(1);
                // do something
                pcntl_signal_dispatch(); // 接收到信号时，调用注册的signalHandler()
                $read = $write = $sockets;
                stream_select($read, $write = null, $e, 0, 100000000);
//                if($up)
//                {
//                    echo 111;exit;
//                }

                foreach ($read as $r) {
                    if(fread($r, 8192))
                    {
                        var_dump(fread($r, 8192));exit;
                    }
                }
                foreach ($write as $r) {
                    if(fread($r, 8192))
                    {
                        var_dump(fread($r, 8192));exit;
                    }
                }

            }

        exit;
        }
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

    private function ev_read($buffer, $id)
    {

        /*
        $headers = '';
        while ($read = event_buffer_read($buffer, 1024)) {
//            $ct += strlen($read);
            $headers .= $read;
        }
//        $ct_size = ($ct - $ct_last) * 8;
//        $str = sprintf("Hello %s\n", $id);

        $str = $headers;
*/
        $childId = mt_rand(1, $this->defaultWorkNum);
//            var_dump($this->pids[$childId]['channel']);exit;
        fwrite($this->pids[$childId]['channel'] , $id);
        posix_kill($this->pids[$childId]['pid'], SIGUSR1);
//        fwrite($this->pids[$childId]['channel'] , $buffer);
//        $str =  fgets($this->pids[$childId]['channel']);
//        $content = stream_get_contents($this->pids[$childId]['channel']);
//$str = 1;

//        fclose($this->pids[1]['socket'][0]);
//        fwrite($sockets[1], "child PID: $pid\n");
//        fclose($this->pids[1]['socket'][1]);

//        $str = 1;
        $str = 223;
        fwrite(self::$connections[$id] , $str);


        /*
        if(stripos($ct_data,"hehe") !== false) {
//            $a = exec("php /usr/local/test/webserver/1.php");
//            fwrite($connection , "nihao1.\r\n");
//            event_buffer_write($buffer, "Received $ct_size byte data./r/n");
//            event_buffer_write(self::$buffers[$id], "Received $ct_size byte data./r/n");
            fwrite(self::$connections[$id] , "nihao1.\r\n");
        } else {
//            fwrite($connection , "Received $ct_data byte data.\r\n");
            fwrite(self::$connections[$id] , "Received $ct_data byte data.\r\n");

        }
        */


        /*
        $pids = array();
        $pid = pcntl_fork();
        if ($pid < 0) {
            printf("fork failed: %s\n", $pid);
        } elseif ($pid) {
            $pids[$pid] = 1;
        } else {
            printf("child return: %s\n", 1111);
            exit;
        }
        while (!empty($pids)) {
            $status = 0;
            $pid = pcntl_waitpid(0, 2, WNOHANG);
            if (isset($pids[$pid])) {
                printf("child %s exit\n", $pid);
                unset($pids[$pid]);
            } else {
                usleep(100);
            }
        }
        printf("parent exit\n");
*/
//        fwrite(self::$connections[$id] , $str);
//        fwrite(self::$connections[$id] , 111);

        unset(self::$connections[$id], self::$buffers[$id]);

    }

    private function ev_error($buffer, $error, $id)
    {
        event_buffer_disable(self::$buffers[$id], EV_READ | EV_WRITE);
        event_buffer_free(self::$buffers[$id]);
        fclose(self::$connections[$id]);
        unset(self::$buffers[$id], self::$connections[$id]);
    }
}
$a = new Master;
$a->start(2000);
//Master::start(2000);


