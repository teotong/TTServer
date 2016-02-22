#!/usr/bin/env php
<?php
$config = parse_ini_file($argv[1], true);
//print_r($config);exit;
require 'vendor/autoload.php';
require 'Master.php';

$server = new Master($config);
$server->start();


#TODO 待优化如下
//设置事件回调函数
//$client->on("connect", function($cli) {
//    $cli->send("hello world\n");
//});
//$client->on("receive", function($cli, $data){
//    echo "Received: ".$data."\n";
//});
//$client->on("error", function($cli){
//    echo "Connect failed\n";
//});
//$client->on("close", function($cli){
//    echo "Connection close\n";
//});
////发起网络连接
//$client->connect('127.0.0.1', 9501, 0.5);
