<?php
/**
 * File Name : tcp_test.php
 * User : zhanglin
 * Date : 2020/5/28
 * Time : 10:38
 */
namespace Im\Service;

use Im\Common\Common;

require_once ('Common.php');

class TcpTest extends Common
{
    protected $serv = null;
    
    public function __construct($host='0.0.0.0',$port=9503,$mode='')
    {
        parent::__construct();
        $this->redis = new \Redis();
        $this->redis->pconnect('127.0.0.1',6379);
        $this->redis->auth('123456');
        $this->redis->select(2);
        $this->serv = new \Swoole\Server($host, $port);
        //设置异步任务的工作进程数量
        $this->serv->set([
            'task_worker_num' => 4
        ]);
        $this->serv->on('Connect', [$this, 'onConnect']);
        $this->serv->on('Receive', [$this, 'onReceive']);
        $this->serv->on('Close', [$this, 'onClose']);
        $this->serv->on('task', [$this,'onTask']);
				$this->serv->on('finish', [$this,'onFinish']);
        //启动服务器
        $this->serv->start();
    }
    
    public function onConnect($serv, $fd)
    {
        echo "客户端连接成功" . PHP_EOL;
    }
    
    public function onReceive($serv, $fd, $from_id, $data)
    {
        $task_id = $this->serv->task($data);
        echo "推送一个任务进程: id=".$task_id.PHP_EOL;
        $serv->send($fd, "服务端发送的数据: " . $data .PHP_EOL);
    }
    /**
     * 接收到推送的任务在任务进程中处理业务
     * onTask
     * @param $server
     * @param $task_id
     * @param $from_id
     * @param $data
     * @author: zhanglin
     */
    public function onTask($server, $task_id, $from_id, $data)
    {
        //投递异步任务
        echo "执行一个工作进程: [id=$task_id]".PHP_EOL;
        //返回任务执行的结果
        $server->finish("客户端的数据：".$data."==> OK");
        //return 'on task finish'; //调用finish 或者return告诉线程
    }
    
    /**
     * 任务 进程处理完成之后执行代码
     * onFinish
     * @param $server
     * @param $task_id
     * @param $data
     * @author: zhanglin
     */
    public function onFinish($server, $task_id, $data)
    {
        echo 'finish-task-id-'.$task_id.PHP_EOL;
        echo 'finish-success：'.$data.PHP_EOL;
    }
    public function onClose($serv, $fd)
    {
        echo "客户端主动断开连接" . PHP_EOL;
    }
}
new TcpTest();
