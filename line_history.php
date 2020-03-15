<?php
$fd = empty($_GET['fd'])?die('非法操作'):$_GET['fd'];
//echo $fd;

$obj = new mysqli('127.0.0.1' , 'root' , '' , 'demo' ,'3306');
$obj->query('set names utf8');

$sql = 'select * from user_chat';
//echo $sql;
$res = $obj->query( $sql );
//var_dump($res);
while( $row=mysqli_fetch_assoc($res) ){
    $info[] = $row;
}
//var_dump($info);exit;
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
<?php foreach( $info as $k => $v){?>
    <?php if($v['all_send_content'] != NULL ){?>
            <p><?php echo $v['fd_name']?>对所有用户:<?php echo $v['all_send_content']; ?></p>
    <?php }else{?>
            <p><?php echo $v['fd_name']?>对用户说:<?php echo $v['sig_send_content']; ?></p>
    <?php }?>
<?php }?>
</body>
</html>

