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
define('DIR',dirname(__FILE__));

class ServiceObj
{
    //protected $serv = null;
//    public function __construct()
//    {
//        $this->serv = new \Swoole\Server('0.0.0.0',9502);
//        $this->serv->on('Connect', [$this,'onConnect']);
//        $this->serv->on('Receive', [$this,'onReceive']);
//        $this->serv->on('Close', [$this,'onClose']);
//        //启动服务器
//        $this->serv->start();
//    }
//    public function onConnect($serv, $fd)
//    {
//        echo "客户端连接成功".PHP_EOL;
//    }
//    public function onReceive($serv, $fd, $from_id, $data)
//    {
//        $serv->send($fd, "服务端发送的数据: ".$data."\n");
//    }
//    public function onClose($serv, $fd)
//    {
//        echo "客户端主动断开连接".PHP_EOL;
//    }
        public function __construct()
        {
            $this->redis = new \Redis();
            $this->redis->pconnect('127.0.0.1',6379);
            $this->redis->auth('123456');
            $this->redis->select(2);
    
            $this->ws = new  \Swoole\WebSocket\Server('0.0.0.0',9502);
            //设置参数
            $this->ws->set([
                'daemonize'                => 0,//是否开启守护进程 0 不开启 1 开启
//                'heartbeat_check_interval' => 10, //每秒检测是否有人掉线
//                'heartbeat_idle_time'      => 60, //60秒内无应答就关闭连接
//                'log_level'                => SWOOLE_LOG_ERROR | SWOOLE_LOG_WARNING | SWOOLE_LOG_NOTICE,//swoole 的日志级别
//                'log_file'                 => DIR.'/log/swoole'.'.log',  //SWOOLE运行日志
            ]);
            $this->ws->on('open', [$this,'onOpen']);
            $this->ws->on('message', [$this,'onMessage']);
            $this->ws->on('close', [$this,'onClose']);
            $this->ws->start();
        }
    
    /**
     * 客户端连接到 服务
     * onOpen
     * @param $server
     * @param $request
     * @author: zhanglin
     */
    public function onOpen($server,$request)
    {
        echo '客户端连接成功'.PHP_EOL;
    }
    
    /**
     * 监听客户端发送信息
     * onMessage
     * @param $server
     * @param $frame
     */
    public function onMessage($server,$frame)
    {
        $server->push($frame->fd,'服务端收到数据'.PHP_EOL);
        echo '接收到客户端数据：'.$frame->data.PHP_EOL;
    }
    /**
     * 监听客户端断开连接
     * onClose
     * @param $server
     * @param $fd
     * @author: zhanglin
     */
    public function onClose($server, $fd)
    {
        echo "客户端断开连接：FD==>".$fd;
    }
}
new ServiceObj();
