<?php
    $client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);

    $arr = range(1,100);
    shuffle($arr);
    $json = json_encode($arr);
    //注册连接成功回调
    $client->on("connect", function($cli) use ($json) {

//        $cli->send("hello world\n");
        $cli->send($json);
    });

    //注册数据接收回调
    $client->on("receive", function($cli, $data){
        echo "Received: ".$data."\n";
    });

    //注册连接失败回调
    $client->on("error", function($cli){
        echo "Connect failed\n";
    });

    //注册连接关闭回调
    $client->on("close", function($cli){
        echo "Connection close\n";
    });

    //发起连接
    $client->connect('127.0.0.1', 9501, 0.5);