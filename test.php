<?php
/**
 * File Name : test.php
 * User : zhanglin
 * Date : 2020/4/24
 * Time : 15:39
 */
$redis = new Redis();
$redis->connect('127.0.0.1',6378);
$redis->auth('123456');
$a = 1;
if($a==1){
    $redis->select(1);
    $redis->set('select_1s',123456);
    echo $redis->get('select_1s').PHP_EOL;
}
$redis->select(2);
$redis->set('select_1s',44556);
echo $redis->get('select_1s');
