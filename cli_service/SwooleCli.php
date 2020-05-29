<?php
/**
 * File Name : SwooleLogin.php
 * User : zhanglin
 * Date : 2020/4/28
 * Time : 13:53
 */

namespace Im\ImService;

use Common\Helper\HelpCommon;

require_once('common/helper/HelpCommon.php');

/**
 * 业务进程处理 （扫码登录 逻辑 定时任务待定）
 * Class : SwooleCli
 * Date : 2020/4/30
 * @package Im\ImService
 */
class SwooleCli extends HelpCommon
{
    private $ws = null;
    private $redis = null;
    private $host_url = 'http://127.0.0.1/test/';
    /**
     * 初始化WebSocket 服务器
     * WebSocket constructor.
     * @param string $host
     * @param int $port
     * @param string $mode
     */
    public function __construct($host='0.0.0.0',$port=9502,$mode='')
    {
        parent::__construct();
        //初始化 redis
        $this->redis = new \Redis();
        $this->redis->pconnect('127.0.0.1',6379);
        $this->redis->auth('123456');
        $this->redis->select(1);
        $this->ws = new  \Swoole\WebSocket\Server($host,$port,$mode);
        //设置参数
        $this->ws->set([
            'daemonize'                => 0,//是否开启守护进程 0 不开启 1 开启
            'heartbeat_check_interval' => 10, //每秒检测是否有人掉线
            'heartbeat_idle_time'      => 60, //60秒内无应答就关闭连接
            'log_level'                => SWOOLE_LOG_ERROR | SWOOLE_LOG_WARNING | SWOOLE_LOG_NOTICE,//swoole 的日志级别
            'log_file'                 => DIR.'/log/swoole_cli'.'.log',  //SWOOLE运行日志
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
        if(empty($request->get['c_type'])){
            $server->push($request->fd, $this->json(500,'无效请求，禁止接入服务器'));
            $server->close($request->fd,true);
        }
        if(!in_array($request->get['c_type'],['qrcode','scanCodeLogin'])){
            $server->push($request->fd, $this->json(500,'无效请求，禁止接入服务器'));
            $server->close($request->fd,true);
        }
        if($request->get['c_type']=='qrcode'){
            $redis_key = $request->server['remote_addr'].date('Y-m-d H:i',time());
            $redis_value = $this->redis->hGet('qrcode_list',$redis_key);//查询临时 qrcode 是否存在 不存在 就是过期了
            if(!empty($redis_value)){//没有过期直接读出来
                $data['qrcode_url'] = $redis_value;
            }else{
                //加密生成 一个可以访问url 地址
                $qrcode_text = $this->host_url.$this->encrypt($this->tempData($request->fd),'E',$this->redis->get('qrcode_key'));
                $data['qrcode_url'] = $this->generateQrCode($qrcode_text,uniqid());
                $this->redis->hSet('qrcode_list',$redis_key,$data['qrcode_url']);//存储到redis hash 列表中
            }
            $server->push($request->fd, $this->json(200,'请扫描二维码',$data));
        }
        if($request->get['c_type']=='scanCodeLogin'){
            $server->push($request->fd, $this->json(200,'服务端连接成功'));
        }
    }
    
    /**
     * 监听客户端发送信息
     * onMessage
     * @param $server
     * @param $frame
     * @return bool
     */
    public function onMessage($server,$frame)
    {
        //检测到前端发送过来的信息
        if($frame->data=='heartbeat') {//检测到心跳数据,不进行处理
            $server->push($frame->fd, $this->json(201,'WebSocket 服务连接正常'));
            return false;
        }
        if(empty($frame->data)){
            $server->push($frame->fd, $this->json(400,'WebSocket 未接收到数据'));
            return false;
        }
        $data = $this->encrypt($frame->data,'D',$this->redis->get('request_key'));
        if(empty($data)){
            $server->push($frame->fd, $this->json(400,'WebSocket 数据传输异常'));
            return false;
        }
        $json = json_decode($data,false);
        if(empty($json)){
            $server->push($frame->fd, $this->json(400,'WebSocket 数据传输异常'));
            return false;
        }
        $server->push($json->fd, $this->json(200,'登录成功',$json));//发送给扫码登录的用户
        $server->push($frame->fd, $this->json(200,'成功'));//返回给页面登录的用户
        return true;
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
        //客户端关闭 记录日志
        $this->write('客户端断开连接','info');
    }
    
}
