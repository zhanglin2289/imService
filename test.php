<?php
/**
 * File Name : test.php
 * User : zhanglin
 * Date : 2020/5/14
 * Time : 10:03
 */
$redis = new \Redis();
$redis->pconnect('127.0.0.1',6379);
$redis->auth('123456');
$redis->select(2);
$data_json = [
    'create_time'=>time(),
    'user_id'=>99886,
    'expire_time'=>7*24*60*60
];
$token = md5(time());
$redis->hSet('user_token',$token,json_encode($data_json));

echo $token;
