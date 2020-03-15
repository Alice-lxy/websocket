<?php
//创建websocket服务器对象，监听0.0.0.0:9502端口
$ws = new \swoole_websocket_server("0.0.0.0", 9502);//swoole_websocket_server

/*//异步
$ws->on('task',function($ws,$task_id,$from_id,$data){
    file_put_contents(__DIR__.'a.log',$data.PHP_EOL,8);
    sleep(1);
    $ws->push($data['fd'],"121212");
    return "task finished";
});
//处理异步任务的结果
$ws->on('finish', function ($ws, $task_id, $data) {
    echo "AsyncTask[$task_id] Finish: $data".PHP_EOL;
});*/


//监听WebSocket连接打开事件
$ws->on('open', function ($ws, $request) {
//    var_dump($request->fd, $request->get, $request->server);echo 1111111;
    $return = [
        'type'=>'connect',
        'msg'=>'connect success',
        'status'=>1000
    ];
    $ws->push($request->fd, json_encode($return));
});

//监听WebSocket消息事件
$ws->on('message', function ($ws, $frame) {

    $swoole_mysql = new \Swoole\Coroutine\MySQL();
    $swoole_mysql->connect([
        'host'=>'127.0.0.1',
        'user' => 'root',
        'password' => '',
        'database' => 'demo',
        'port'=>3306,
    ]);



//    echo "Message: {$frame->data}\n";
    //redis
    $redis = new \Swoole\Coroutine\Redis();
    $redis->connect('127.0.0.1', 6379);
    $data = json_decode($frame->data,true);
    if(empty($data['type'])){
        $ws->push($frame->fd, "type not found");
    }
    var_dump($data);
    if($data['type']==1){
        $data['username'] = strip_tags($data['username']);
        if(!empty($data['username'])){//&& $data['password']=='111'
            if( trim( $data['username'] ) =='' ){
                $return = [
                    'type'=>'login',
                    'msg'  =>  '账号不能为空' ,
                    'status'=>3
                ];
                $ws->push($frame->fd,json_encode($return,JSON_UNESCAPED_UNICODE));
            }else{
                //登录成功  将用户信息写入redis中
                $redis_arr = [
                    $frame->fd=>[
                        'username'=>$data['username'],
                        'fd'=>$frame->fd
                    ]
                ];
//            $ws->push($frame->fd,json_encode($return,JSON_UNESCAPED_UNICODE));
                $online_str = $redis->get('online_list');
                if(empty($online_str)){
                    $online_arr = [];
                }else{
                    $online_arr = json_decode($online_str,true);
                }
                $online_list = (array)$redis_arr + (array)$online_arr;
                $redis->set('online_list',json_encode($online_list,JSON_UNESCAPED_UNICODE));
                $return = [
                    'type'=>'login',
                    'msg'  =>  'ok' ,
                    'status'=>1000,
                    'fd'=>$frame->fd
                ];
                $ws->push($frame->fd,json_encode($return,JSON_UNESCAPED_UNICODE));
                $msg = '欢迎'.$data['username'].'加入聊天室';
                foreach($online_list as $k=>$v){
                    $online_notice = [
                        'type'=>'online',
                        'msg'  =>  $msg ,
//                    'username'=>$data['username']
                    ];
                    //通知所有的用户加入 聊天室
                    $ws->push($v['fd'],json_encode($online_notice));
                    $new_online_list = [
                        'type'=>'online_list',
                        'list'=>$online_list
                    ];
                    $ws->push($v['fd'],json_encode($new_online_list));
                }
            }
        }else{
            $return = [
                'type'      =>  'login',
                'status'    =>  1,
                'msg'    =>  '账号或密码错误',
            ];
            $ws->push($frame->fd, json_encode($return , JSON_UNESCAPED_UNICODE));
        }
    }elseif($data['type']==2){
        //判断群发还是私聊
        $m_type=$data['sendto'];
        $online_str=$redis->get('online_list');
        $all_online=json_decode($online_str,true);
        $username=$all_online[$frame->fd]['username'];
        //群发
        if($m_type=='all'){
//            echo 1;
            foreach($all_online as $key => $value){
                $msg='<b>'.$username.':</b>'.$data['message'];
//
                var_dump($username);
                $message_info=[
                    'type'=>'message',
                    'msg'=>$msg,
//                    'username'=>$data['username']
                ];
                var_dump($message_info);
                if($frame->fd==$value['fd']){
                    $message_info['isme']=1;
                }else{
                    $message_info['isme']=0;
                }
                $time = time();
                $sql = 'insert into user_chat VALUES (NULL,"'.$username.'","'.$data['message'].'",NULL,0,"'.$time.'","'.$time.'","'.$username.'")';

                $res = $swoole_mysql->query($sql);
                var_dump($res);
                //把信息发送给所有客户
                $ws->push($value['fd'],json_encode($message_info));
            }
        }else{
            $fd[]=$frame->fd;
            $fd[]=$m_type;
            $count = count($fd);
            var_dump($fd);
//            var_dump($all_online[$m_type]['username']);
            for($i=0;$i<$count;$i++){
                if($fd[$i]!=$frame->fd){
                    $msg='<span class="color">'.$username.'对你说:'.$data['message'].'</span>';
                    $message_info=[
                        'type'=>'message',
                        'msg'=>$msg,
//                        'username'=>$data['username'],
                        'isme'=>0
                    ];
                }else{
                    $msg='你对'.$all_online[$m_type]['username'].'说:'.$data['message'].'';
                    $message_info=[
                        'type'=>'message',
                        'msg'=>$msg,
//                        'username'=>$data['username'],
                        'isme'=>1
                    ];
                }
                $time = time();
                $new = '对'.$all_online[$m_type]['username'].'说:'.$data['message'].'';
                $sql = 'insert into user_chat VALUES (NULL,"'.$username.'",NULL,"'.$new.'",0,"'.$time.'","'.$time.'","'.$username.'")';
//                echo $sql;
                $res = $swoole_mysql->query($sql);
//                var_dump($res);
                $ws->push($fd[$i],json_encode($message_info));
            }
        }
        //查询所有的用户
        $user_all=$redis->get('online_list');
        //用户列表
        $user_list = json_decode($user_all,true);
        //发送消息用户
        $user = $user_list[$frame->fd];
        $info = [
            'user_all'=>$user_list,
            'user'=>$user,
            'data'=>$data
        ];
        //调用异步方法
//        $task_id = $ws->task($info);
    }
});

//处理异步任务
$ws->on('task', function ($ws, $task) {
    $data = $task -> data;
    $user_all = $data['user_all'];
    $user = $data['user'];
    $data1 = $data['data'];

    $sql_data = [
        'send_from' =>  $user['fd'],
        'send_from_name'    =>  $user['username'],
        'send_to'   =>  $data1['sendto'],
        'msg'       =>  $data1['message'],
];
        /*$swoole_mysql = new \Swoole\Coroutine\MySQL();
        $swoole_mysql->connect([
            'host'=>'127.0.0.1',
            'user' => 'root',
            'password' => '',
            'database' => 'demo',
            'port'=>3307,
        ]);
            if($user['fd'] == $frame['fd']){
                $msg = '<p class="right_p"><b>' . $user['username'] . ':</b>' . $data1['message'] . '</p>';
            }else{
                $info['msg'] = $msg;

                $ws->push($v['fd'], json_encode($info , JSON_UNESCAPED_UNICODE));
            }
    }else{
    $send_user = $user_all[$data1['sendto']];
    $info = [
        'type'  =>  'message',
    ];
    $ws->push($user['fd'], json_encode($info , JSON_UNESCAPED_UNICODE));
    $ws->push($data1['sendto'], json_encode($info , JSON_UNESCAPED_UNICODE));


}
    $res = $swoole_mysql -> query( $sql );*/


//    echo "New AsyncTask[id=$task_id]".PHP_EOL;
//    //返回任务执行的结果
//    $serv->finish("$data -> OK");
});














//监听WebSocket连接关闭事件
$ws->on('close', function ($ws, $fd) {
    $redis = new \Swoole\Coroutine\Redis();
    $redis->connect('127.0.0.1',6379);
    $online_str=$redis->get('online_list');
    $online_arr=json_decode($online_str,true);
    $username=$online_arr[$fd]['username'];

    //关闭的时候删除对应的fd
    unset($online_arr[$fd]);
    $redis->set('online_list',json_encode($online_arr));
    if(!empty($online_arr)){
        foreach($online_arr as $key =>$value){
            $msg='用户'.$username.'离开聊天室';
            $online_notify=[
                "type"=>'online',
                "msg"=>$msg,
                "username"=>$username
            ];
            //通知所有用户***退出聊天室
            $ws->push($value['fd'],json_encode($online_notify));
            $online_list=[
                "type"=>'online_list',
                'list'=>$online_arr
            ];
            //获取所有在线的用户，返回给客户端==  展示右侧的在线用户列表
            $ws->push($value['fd'],json_encode($online_list));
        }
    }
    echo "client-{$fd} is closed\n";
});

$ws->start();
