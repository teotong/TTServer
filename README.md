# TTServer
一个类似nginx的httpserver(libevent、epoll、异步非阻塞)

##需求

####软件版本

* PHP-5.4+
* php-fpm

####composer

* "adoy/fastcgi-client": "^1.0"

####PHP第三方开源类

* Workerman\Protocols\Http

####PHP扩展

* pcntl
* posix
* libevent

####安装

composer install

####配置如下(config.ini)
```
[master]
name = "config_1"
host = "0.0.0.0"
port = 8080


[run]
script_file = "/usr/local/test/TTServer/1.php"
memory_limit = "1024M"
working_dir = "/tmp"

[worker]
count = 3
max_run_count = 10000
max_run_seconds = 3600
max_idle_seconds = 60
empty_sleep_seconds = 0.1

[fastcgi]
host = "127.0.0.1"
port = 9000
```
####使用
php TTServer.php config.ini
