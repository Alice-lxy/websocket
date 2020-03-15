<?php
    $fd = empty($_GET['fd'])?die('非法操作'):$_GET['fd'];


    $obj = new mysqli('127.0.0.1' , 'root' , 'root' , 'test' ,'3306');
    $obj->query('set names utf8');

    $sql = 'select * from line_history';

    $res = $obj->query( $sql );
    while( $row=mysqli_fetch_assoc($res) ){
        $info[] = $row;
    }
    //var_dump($info);exit;
    $data = [];
    foreach ($info as $k => $v){
        if( $v['send_to'] == -1 || $v['send_from'] == $fd || $v['send_to'] == $fd ){
            $data[] = $v;
        }
    }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>聊天记录</title>
</head>
<body>
    <?php foreach( $data as $k => $v){?>
        <?php if($v['send_to'] == -1){?>
            <?php if($v['send_from'] == $fd){?>
                <p>您对所有用户:<?php echo $v['message']; ?></p>
            <?php }else{ ?>
                <p><?php echo $v['send_from_name']; ?>对所有用户说:<?php echo $v['message']; ?></p>
            <?php }?>
        <?php }elseif($v['send_from'] == $fd){ ?>
            <p>您对<?php echo $v['send_to_name']; ?>私聊说：<?php echo $v['message']; ?></p>
        <?php }elseif($v['send_to'] == $fd){ ?>
            <p><?php echo $v['send_from_name']; ?>对您私聊说：<?php echo $v['message']; ?></p>
        <?php } ?>
    <?php }?>
</body>
</html>
