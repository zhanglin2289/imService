<?php
/**
 * File Name : tcp_test.php
 * User : zhanglin
 * Date : 2020/5/28
 * Time : 10:38
 */
namespace Service;

class TcpTest
{
    protected $serv = null;
    
    public function __construct()
    {
        $this->serv = new \Swoole\Server('0.0.0.0', 9503);
        $this->serv->on('Connect', [$this, 'onConnect']);
        $this->serv->on('Receive', [$this, 'onReceive']);
        $this->serv->on('Close', [$this, 'onClose']);
        //启动服务器
        $this->serv->start();
    }
    
    public function onConnect($serv, $fd)
    {
        echo "客户端连接成功" . PHP_EOL;
    }
    
    public function onReceive($serv, $fd, $from_id, $data)
    {
        $serv->send($fd, "服务端发送的数据: " . $data . "\n");
    }
    
    public function onClose($serv, $fd)
    {
        echo "客户端主动断开连接" . PHP_EOL;
    }
}
new TcpTest();
