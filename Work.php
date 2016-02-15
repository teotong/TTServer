<?php

class Worker
{
    private static $connections = array();
    private static $buffers = array();
    private static $remote_address = array();

    public function __construct($socket)
    {
        $this->socket = $socket;
    }

    public function start()
    {
        //创建并且初始事件
        $base = event_base_new();
        //创建一个新的事件
        $event = event_new();
        //准备想要在event_add中添加事件
        event_set($event, $this->socket, EV_READ | EV_PERSIST, array($this, 'ev_accept'), $base);
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
        $id += 1;
        $connection = stream_socket_accept($socket, ini_get("default_socket_timeout"), $remote_address);
//        error_log($a, 3, '/var/log/apache/php_error.log');
        stream_set_blocking($connection, 0);
        //建立一个新的缓存事件
        $buffer = event_buffer_new($connection, array($this, 'ev_read'), NULL, array($this, 'ev_error'),  $id);
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
        self::$remote_address[$id] = $remote_address;
    }

    private function ev_read($buffer, $id)
    {
        echo 11;exit;
//        static $ct = 0;
//        $ct_last = $ct;
        $ct_data = '';
        while ($read = event_buffer_read($buffer, 1024)) {
//            $ct += strlen($read);
            $ct_data .= $read;
        }
//        $ct_size = ($ct - $ct_last) * 8;
        /*
        //处理headers
        $_REQUEST = Http::decode($ct_data, self::$remote_address[$id]);
        $client = new FcgiClient('127.0.0.1', 9000);
        $params = array(
            'GATEWAY_INTERFACE' => 'FastCGI/1.0',
            'REQUEST_METHOD'    => $_REQUEST['server']['REQUEST_METHOD'],
            'SCRIPT_FILENAME'   => '/usr/local/www/test/TTServer/1.php',
//            'SCRIPT_FILENAME'   => '/usr/local/www/test/TTServer/12.php',
            'SCRIPT_NAME'       => $_REQUEST['server']['REQUEST_URI'],
            'QUERY_STRING'      => $_REQUEST['server']['QUERY_STRING'],
            'REQUEST_URI'       => $_REQUEST['server']['REQUEST_URI'],
            //nginx存在 apache不存在此值
            'DOCUMENT_URI'      => $_REQUEST['server']['REQUEST_URI'],
            'SERVER_SOFTWARE'   => 'php/fcgiclient',
            'REMOTE_ADDR'       => $_REQUEST['server']['REMOTE_ADDR'],
            'REMOTE_PORT'       => $_REQUEST['server']['REMOTE_PORT'],
            'SERVER_ADDR'       => '127.0.0.1',
            'SERVER_PORT'       => '80',
            'SERVER_NAME'       => php_uname('n'),
            'SERVER_PROTOCOL'   => 'HTTP/1.1',
            'CONTENT_TYPE'      => $_REQUEST['server']['CONTENT_TYPE'],
            'CONTENT_LENGTH'    => Http::input($ct_data),
        );
        $http_response = $client->request($params, false)."\n";
//        print_r($http_response);exit;
//        $header = "HTTP/1.1 404 OK\r\n";
//        $http_response['response'] = $header . $http_response;
//        print_r($http_response);exit;
        $http_response = Http::response($http_response);
        if($http_response['error']) {
            //TODO 记日志
        }

       */
//        sleep(4);
        $http_response['response'] = $ct_data;
//        $http_response = "HTTP/1.1 404 Not Found\r\nX-Powered-By: PHP/5.6.17\r\nContent-type: text/html;\r\ncharset=UTF-8\r\n\r\nFile not found.";
//        posix_kill($this->pids[$childId]['pid'], SIGUSR1);
//        $content = stream_get_contents($this->pids[$childId]['channel']);
        fwrite(self::$connections[$id] , $http_response['response'] . "\r\n" .  getmypid());
//        fwrite(self::$connections[$id] , $http_response);
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
        unset(self::$connections[$id], self::$buffers[$id], self::$remote_address[$id]);

    }

    private function ev_error($buffer, $error, $id)
    {
        event_buffer_disable(self::$buffers[$id], EV_READ | EV_WRITE);
        event_buffer_free(self::$buffers[$id]);
        fclose(self::$connections[$id]);
        unset(self::$buffers[$id], self::$connections[$id]);
    }


    /**
     * 创建一个子进程
     * @param $i
     */
    public function createOneWork()
    {
        $pid = pcntl_fork();
        if ($pid < 0) {
            printf("fork failed: %s\n", $pid);
        } elseif ($pid) {
            return $pid;
        } else {
//            $this->setSignal();
//            pcntl_signal_dispatch(); // 接收到信号时，调用注册的signalHandler()
            $this->start();
//            exit;
        }
    }

    /**
     * 安装信号
     */
    public function setSignal()
    {
        //安装信号处理器
        pcntl_signal(SIGTERM, array($this, "sig_handler"));//进程被kill时发出的信号
        // pcntl_signal(SIGHUP,  "sig_handler");//终端关闭时发出的信号
        pcntl_signal(SIGINT, array($this, "sig_handler"));//中断进程信号，如Ctrl+C
        pcntl_signal(SIGCHLD, array($this, "sig_handler"));//进程退出信号
    }

    //信号处理函数
    public function sig_handler($sig)
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

}