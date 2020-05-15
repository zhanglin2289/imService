<?php
/**
 * File Name : test.php
 * User : zhanglin
 * Date : 2020/5/14
 * Time : 10:03
 */
//$redis = new \Redis();
//$redis->pconnect('127.0.0.1',6379);
//$redis->auth('123456');
//$redis->select(2);
//$data_json = [
//    'create_time'=>time(),
//    'user_id'=>99886,
//    'expire_time'=>7*24*60*60
//];
//$token = md5(time());
//$redis->hSet('user_token',$token,json_encode($data_json));
//
//echo $token;
namespace Service;


class ServiceObj
{
    protected $serv = null;
    public function __construct()
    {
        $this->serv = new \Swoole\Server('0.0.0.0',9502);
        $this->serv->on('Connect', [$this,'onConnect']);
        $this->serv->on('Receive', [$this,'onReceive']);
        $this->serv->on('Close', [$this,'onClose']);
        //启动服务器
        $this->serv->start();
    }
    public function onConnect($serv, $fd)
    {
        echo "客户端连接成功".PHP_EOL;
    }
    public function onReceive($serv, $fd, $from_id, $data)
    {
        $serv->send($fd, "服务端发送的数据: ".$data."\n");
    }
    public function onClose($serv, $fd)
    {
        echo "客户端主动断开连接".PHP_EOL;
    }
}
new ServiceObj();
