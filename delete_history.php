<?php

    $obj = new mysqli('127.0.0.1' , 'root' , '' , 'demo' ,'3307');
    $obj->query('set names utf8');
    $time = time() - 7*24*60*60;

    $sql = 'delete from line_history where ctime<' . $time;

    $res = $obj->query($sql);
    if($res !== false){
        $str = 'success';
    }else{
        $str = 'fail';
    }
    echo '>>>>>>>>>>>>>>>>>>>>>'.date('Y-m-d H:i:s' , time())."\r\n".  $str .'<<<<<<<<<<<<<<<<<<<<<<<<';