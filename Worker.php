<?php
namespace TTServer;

require 'Http.php';

use Workerman\Protocols\Http;
use Adoy\FastCGI\Client;

class Worker
{
    private static $connections = array();
    private static $buffers = array();
    private static $remote_address = array();
    private $http_response;
    private $http_error;

    public function __construct($socket, $config)
    {
        $this->config = $config;
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
        if($id == PHP_INT_MAX) {
            $id = 0;
        }
        $id += 1;
        #TODO 待处理惊群
        $connection = stream_socket_accept($socket, ini_get("default_socket_timeout"), $remote_address);
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

    public function ev_read($buffer, $id)
    {
        $ct_data = '';
        while ($read = event_buffer_read($buffer, 1024)) {
            $ct_data .= $read;
        }
        //获得http格式的返回数据给浏览器
        $this->get_http($ct_data, $id);
        if($this->http_error) {
            #TODO 记日志
        }
//        fwrite(self::$connections[$id] , $this->http_response. "\r\n" .  getmypid());
        fwrite(self::$connections[$id] , $this->http_response);
        //删除所有变量
        $this->destroy_var($id);
//        fclose(self::$connections[$id]);
    }

    public function ev_error($buffer, $error, $id)
    {
        event_buffer_disable(self::$buffers[$id], EV_READ | EV_WRITE);
        event_buffer_free(self::$buffers[$id]);
        //删除所有变量
        $this->destroy_var($id);
        fclose(self::$connections[$id]);
    }

    //删除所有变量
    public function destroy_var($id)
    {
        unset(
            self::$connections[$id],
            self::$buffers[$id],
            self::$remote_address[$id],
            $this->http_response,
            $this->http_error,
            $this->cgi_content,
            $this->cgi_params
        );
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
            $this->start();
//            exit;
        }
    }

    //获得http格式的返回数据给浏览器
    public function get_http($ct_data, $id)
    {
        //处理请求headers,获得fastcgi协议需要的参数
        $_REQUEST = Http::decode($ct_data, self::$remote_address[$id]);
//        if($_REQUEST['get']['test'] == '111') {
//            sleep(10);
//        }
        //fastcgi参数构建
        $this->build_cgi_params($_REQUEST);

        //请求fastcgi
        $cgi_response = $this->request_fastcgi();
        //处理从fastcgi返回的数据 构成http协议格式返回给浏览器
        $http = Http::response($cgi_response);

        //http错误信息
        $this->http_error = $http['error'];
        //http返回值
        $this->http_response = $http['response'];

    }


    //请求fastcgi
    public function request_fastcgi()
    {
        $client = new Client($this->config['fastcgi']['host'], $this->config['fastcgi']['port']);
        //请求fastcgi
        $cgi_response = $client->request($this->cgi_params, $this->cgi_content);
        return $cgi_response;
    }


    //fastcgi参数构建
    public function build_cgi_params($_REQUEST)
    {
        switch ($_REQUEST['server']['REQUEST_METHOD']) {
            case 'GET' :
                $cgi_content = false;
                $content_length = strlen(http_build_query($_REQUEST['get']));
                break;
            case 'POST' :
                $cgi_content = http_build_query($_REQUEST['post']);
                $content_length = strlen($cgi_content);
                break;
            default :
                $cgi_content = false;
                $content_length = 0;
                break;
        }

        $cgi_params = array(
            'GATEWAY_INTERFACE' => 'FastCGI/1.0',
            'REQUEST_METHOD' => $_REQUEST['server']['REQUEST_METHOD'],
            'SCRIPT_FILENAME' => $this->config['run']['script_file'],
            'QUERY_STRING'      => $_REQUEST['server']['QUERY_STRING'],
            'REQUEST_URI'       => $_REQUEST['server']['REQUEST_URI'],
//            nginx存在 apache不存在此值
            'DOCUMENT_URI'      => $_REQUEST['server']['REQUEST_URI'],
            'SERVER_SOFTWARE' => 'php/fcgiclient',
            'REMOTE_ADDR' => $_REQUEST['server']['REMOTE_ADDR'],
            'REMOTE_PORT' => $_REQUEST['server']['REMOTE_PORT'],
            'SERVER_ADDR' => $this->getLocalIp(),
            'SERVER_PORT' => $_REQUEST['server']['SERVER_PORT'],
            'SERVER_NAME' => php_uname('n'),
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'CONTENT_TYPE' => $_REQUEST['server']['CONTENT_TYPE'],
            'CONTENT_LENGTH' => $content_length,
        );

         $this->cgi_params = $cgi_params;
         $this->cgi_content = $cgi_content;
    }

    public function getLocalIp($device = '')
    {
        $osType = ucfirst(strtolower(php_uname('s')));
        if (!$device) {
            // Linux and other OS use eth0 as default device
            $device = 'eth0';
            if ($osType == 'Darwin') {
                $device = 'en0';
            }
        }
        $checkDeviceCommand = "(ifconfig {$device} >> /dev/null 2>&1 || (echo false && exit 1))";
        $awkCommand = 'awk \'/inet / {ipstr = $0;gsub("addr:", "", ipstr);split(ipstr, ip, " ");print ip[2]}\'';
        $ip = trim(shell_exec("{$checkDeviceCommand} && (ifconfig {$device} | {$awkCommand})"));
        if ($ip && $ip != 'false') {
            return $ip;
        }
        return '0.0.0.0';
    }

}
