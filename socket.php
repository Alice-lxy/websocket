<?php
//创建websocket服务器对象，监听0.0.0.0:9502端口
$ws = new \swoole_websocket_server("0.0.0.0", 9502);

$ws->set(array('task_worker_num' => 50,'task_enable_coroutine'=>true,'enable_coroutine'=>true));

//监听WebSocket连接打开事件
$ws->on('open', function ($ws, $request) {
    //var_dump($request->fd, $request->get, $request->server);
    $return = [
        'type'    =>  'connect',
        'msg'       =>  'ok'
    ];
    $ws->push($request->fd, json_encode($return ));
});

//监听WebSocket消息事件
$ws->on('message', function ($ws, $frame) {

    $data = json_decode( $frame->data , true);
    if(!empty( $data['type'] )){
        if ($data['type'] == 1){
            //登录
            if(!empty($data['username']) && !empty($data['password']) && $data['password'] == '123456'){
                //展示登录列表
                $user = [
                    $frame->fd => [
                        'username'  =>  $data['username'],
                        'fd'        =>  $frame -> fd
                    ]
                ];
                $user_send = [
                    'type'  =>  'login',
                    'status'    =>  1000,
                    'username'  =>  $data['username'],
                    'fd'        =>  $frame -> fd
                ];
                $ws->push($frame->fd, json_encode(  $user_send , JSON_UNESCAPED_UNICODE));

                //链接redis
                $redis = new \Swoole\Coroutine\Redis();
                $redis->connect('127.0.0.1', 6379);
                //查询用户登录信息    将新用户加入数组中
                $user_all_str = $redis->get('online_user');
                if(empty($user_all_str)){
                    $user_all_arr = [];
                }else{
                    $user_all_arr = json_decode($user_all_str , true);
                }
                $user_all = (array)$user_all_arr + (array)$user;

                $redis -> set('online_user' , json_encode($user_all , JSON_UNESCAPED_UNICODE));


                foreach ( $user_all as $k => $v ){
                    //提示用户已登录
                    $msg = '欢迎用户<b>' . $data['username'] . '</b>登录聊天室';
                    $info = [
                        'type'  =>  'online',
                        'msg'   =>  $msg,
                    ];
                    $ws->push($v['fd'], json_encode($info , JSON_UNESCAPED_UNICODE));
                    //右侧用户列表展示
                    $new_online_list = [
                        'type'=>'online_list',
                        'list'=>$user_all
                    ];
                    $ws->push($v['fd'],json_encode($new_online_list));
                }

            }else{
                $return = [
                    'type'      =>  'login',
                    'status'    =>  1,
                    'msg'    =>  '账号或密码错误',
                ];
                $ws->push($frame->fd, json_encode($return , JSON_UNESCAPED_UNICODE));

            }

        }elseif ( $data['type'] == 2 ){
            $redis = new \Swoole\Coroutine\Redis();
            $redis->connect('127.0.0.1', 6379);
            //查询用户登录信息    将新用户加入数组中
            $user_all_str = $redis->get('online_user');

            //用户列表
            $user_all = json_decode($user_all_str , true);
            //发送消息用户
            $user = $user_all[ $frame->fd ];
            $info = [
                'user_all' => $user_all,
                'user'     => $user,
                'data'     => $data
            ];

            //调用异步方法
            $task_id = $ws->task($info);

        }
    }

});

//处理异步任务
$ws->on('task', function ($ws, $task) {
    $data = $task -> data;
    $user_all = $data['user_all'];
    $user = $data['user'];
    $data1 = $data['data'];
//echo 111;var_dump($user);echo 111;
//echo 222;var_dump($data1);echo 222;

    $sql_data = [
        'send_from' =>  $user['fd'],
        'send_from_name'    =>  $user['username'],
        'send_to'   =>  $data1['sendto'],
        'msg'       =>  $data1['message'],
        'ctime'     =>  strtotime( date( 'Y-m-d' , time() ) )
    ];

    $swoole_mysql = new \Swoole\Coroutine\MySQL();
    $swoole_mysql->connect([
        'host' => '127.0.0.1',
        'port' => 3307,
        'user' => 'root',
        'password' => '',
        'database' => 'demo',
    ]);
    if($data1['sendto'] == -1) {
//        echo 1;

        if ($data1['sendto'] == -1) {
//            echo 2;
            //所有人
            foreach ($user_all as $k => $v) {
                $info = [
                    'type' => 'message',
                ];
                if ($user['fd'] == $v['fd']) {
                    $msg = '<p class="right_p"><b>' . $user['username'] . ':</b>' . $data1['message'] . '</p>';
                } else {
                    $msg = '<p class="left_p"><b>' . $user['username'] . ':</b>' . $data1['message'] . '</p>';
                }
                $info['msg'] = $msg;
                $sql = 'insert into line_history values(null,"' . $user['fd'] . '","' . $user['username'] . '","' . $data1['sendto'] . '","所有人","' . $data1['message'] . '","' . time() . '")';

                $ws->push($v['fd'], json_encode($info, JSON_UNESCAPED_UNICODE));
            }
        } else {
            $send_user = $user_all[$data1['sendto']];
            $info = [
                'type' => 'message',
            ];
            $info['msg'] = '<p class="blue_r_p">您对用户<b>' . $send_user['username'] . '</b>发起私聊:' . $data1['message'] . '</p>';
            $ws->push($user['fd'], json_encode($info, JSON_UNESCAPED_UNICODE));
            $info['msg'] = '<p class="blue_l_p">用户<b>' . $user['username'] . '</b>对您发起私聊:' . $data1['message'] . '</p>';
            $ws->push($data1['sendto'], json_encode($info, JSON_UNESCAPED_UNICODE));

            $sql = 'insert into line_history values(null,"' . $user['fd'] . '","' . $user['username'] . '","' . $data1['sendto'] . '","' . $send_user['username'] . '","' . $data1['message'] . '","' . time() . '")';
            echo $sql;
        }

        $res = $swoole_mysql->query($sql);

    }
//    echo "New AsyncTask[id=$task_id]".PHP_EOL;
//    //返回任务执行的结果
//    $serv->finish("$data -> OK");
    });

//监听WebSocket连接关闭事件
    $ws->on('close', function ($ws, $fd) {
        //链接redis
        $redis = new \Swoole\Coroutine\Redis();
        $redis->connect('127.0.0.1', 6379);

        //查询用户登录信息    将新用户加入数组中
        $user_all_str = $redis->get('online_user');
        //用户列表
        $user_all = json_decode($user_all_str , true);
        //离线用户信息
        $user = $user_all[$fd];
        //移除离线用户
        unset($user_all[$fd]);
        $redis -> set('online_user' , json_encode($user_all , JSON_UNESCAPED_UNICODE));
//        var_dump($user);echo 111;
//        var_dump($user_all);echo 222;
        foreach ( $user_all as $k => $v ){
            //提示用户已登录
            $msg = '用户<b>' . $user['username'] . '</b>已离开聊天室聊天室';
            $info = [
                'type'  =>  'message',
                'msg'   =>  $msg,
            ];
            $ws->push($v['fd'], json_encode($info , JSON_UNESCAPED_UNICODE));
            //右侧用户列表展示
            $list = [
                'type'  =>  'online_list',
                'msg'   =>  $user_all
            ];
            $ws->push($v['fd'], json_encode($list , JSON_UNESCAPED_UNICODE));
        }
    });

    $ws->start();
