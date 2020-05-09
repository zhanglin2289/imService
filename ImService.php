<?php
		/**
		 * File Name: ImService.php
		 * User : zhangLin
		 * Time : 2020/4/22 --- 22:03
		 */
namespace Im\ImService;

require_once ('Common.php');

use Im\Common\Common;

class ImService extends Common
{
		protected $ws = null;
		protected $redis = null;
		protected $expire_time = 7*24*60*60;//默认一个星期的时间
		/**
		 * 初始化WebSocket 服务器
		 * WebSocket constructor.
		 * @param string $host
		 * @param int $port
		 * @param string $mode
		 */
		public function __construct($host='0.0.0.0',$port=9501,$mode='')
		{
        parent::__construct();
        //初始化 redis
				$this->redis = new \Redis();
				$this->redis->pconnect('127.0.0.1',6379);
				$this->redis->auth('123456');
				$this->redis->select(2);
				
				$this->ws = new  \Swoole\WebSocket\Server($host,$port,$mode);
				//设置参数
				$this->ws->set([
						'daemonize'                => 0,//是否开启守护进程 0 不开启 1 开启
						'heartbeat_check_interval' => 10, //每秒检测是否有人掉线
				    'heartbeat_idle_time'      => 60, //60秒内无应答就关闭连接
				    'log_level'                => SWOOLE_LOG_ERROR | SWOOLE_LOG_WARNING | SWOOLE_LOG_NOTICE,//swoole 的日志级别
				    'log_file'                 => DIR.'/log/swoole'.'.log',  //SWOOLE运行日志
				]);
				$this->ws->on('open', [$this,'onOpen']);
				$this->ws->on('message', [$this,'onMessage']);
//				$this->ws->on('task', [$this,'onTask']);
//				$this->ws->on('finish', [$this,'onFinish']);
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
		    $this->head($request);
        $client_token = $request->get['token'];//接收到客户端的token
        $user_id = $this->checkToken($client_token);
        if(empty($user_id)){
            $this->ws->push($request->fd, $this->json(401,'无效授权'));
        }else{
            if(!$this->hashHas('relat_user',$user_id)){//连接的用户不存在 绑定信息
                $this->redis->hSet('relat_user',$user_id,$request->fd);//追加到redis 的hash 表中
                $this->redis->hSet('relat_fd',$request->fd,$user_id);//追加到redis 的hash 表中
            }
            $this->ws->push($request->fd, $this->json(201,'WebSocket 连接成功'));
        }
		}

		/**
		 * 监听客户端发送信息
		 * onMessage
		 * @param $server
		 * @param $frame
		 * @author: zhanglin
		 * $frame 是swoole_websocket_frame对象，包含了客户端发来的数据帧信息
		 * $frame->fd，客户端连接的唯一标识socket id，使用$server->push推送数据时需要用到
		 * $frame->data，数据内容，可以是文本内容也可以是二进制数据，可以通过opcode的值来判断
     * {"client_token":"aa11223ssdsds","type":0,"to":"10452","message":"文字描述","resources":"1.png"}
		 * $frame->opcode，WebSocket的OpCode类型，可以参考WebSocket协议标准文档
		 * $frame->finish， 表示数据帧是否完整，一个WebSocket请求可能会分成多个数据帧进行发送（底层已经实现了自动合并数据帧，现在不用担心接收到的数据帧不完整）
		 */
		public function onMessage($server,$frame)
		{
		    //ToDo::onMessage 群聊和广播没做
				//检测到前端发送过来的信息
        if($frame->data=='heartbeat') {//检测到心跳数据,不进行处理
            $server->push($frame->fd, $this->json(201,'WebSocket 服务连接正常'));
            return true;
        }
        //接收前端json 数据
        //检测文字描述（防止脚本注入 检测最大输入字符长度）
        //检测数据中 并检查资源文件中的网址 是否正常
        $client_data = $this->checkJson($frame->data);
        if($client_data==false){//说明检测失败  直接输出数据
            $server->push($frame->fd,$this->error_json);
            return false;
        }
        //检测token
        $user_id = $this->checkToken($client_data->client_token);
        if(empty($user_id)){
            $this->ws->push($frame->fd, $this->json(401,'无效授权'));
            return false;
        }
        if($client_data->type==0){//单聊信息
            //找到对方的fd 发送数据（找不到 就不管了 就检测用户是否存在【检测 redis 的用户映射表】，不存在就返回数据）
            if(!$this->hashHas('user_list',$client_data->to)){
                $this->ws->push($frame->fd, $this->json(404,'对方账号不存在或已经注销'));
                return false;
            }
            //将数据写入到 redis 数据库(临时数据库存储)中 存储数据（ToDo:: 后期定时任务 将这张表的数据写入到数据库）
            $this->redis->hSet('im_user_message',$client_data->to,$frame->data);
        }
        if($client_data->type==1){//群聊信息
            //检测这个群聊是否存在
            if(!$this->hashHas('user_group_list',$client_data->to)){
                $this->ws->push($frame->fd, $this->json(404,'对方账号不存在或已经注销'));
                return false;
            }
            $group_list = $this->redis->hGetAll('user_group_list');
            //将数据推送给 任务进程处理  如果群里用户过多可以 将数据拆分开 分段推送
//            if(count($group_list) > 50){
//
//            }
            
            $this->ws->task(['message'=>$client_data->message,'group_list'=>$group_list]);
            
            
        }
        if($client_data->type==3){//发送广播
        
        
        
        
        }
        
        //告知用户我的信息发送成功
        $server->push($frame->fd, $this->json(201,'WebSocket 服务连接正常'));
        return true;
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
				if(!empty($data)){//接收到 投递的任务
				    foreach($data['group_list'] as $k=>$v){
				        //检测用户是否在线
                $relat_user = $this->redis->hGet('relat_user',$v);
                if($relat_user){
                    //在线处理（用户离线不做处理）
                    $server->push($relat_user,$this->json(200,'成功',$data['message']));
                }
            }
        }
				$this->ws->finish('ok');
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
				echo 'finish-task-id-'.$task_id;
				echo 'finish-success'.$data;
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
        $user_id = $this->redis->hGet('relat_fd',$fd);
        if(!empty($user_id)){
            $this->redis->hDel('relat_fd',$fd);
            if($this->hashHas('relat_user',$user_id)){
                $this->redis->hDel('relat_user',$user_id);
            }
        }
				//客户端关闭 记录日志
        $this->write('客户端断开连接','info');
		}
    
    /**
     * 检测客户端token
     * checkToken
     * @param string $client_token
     * @return bool
     */
		public function checkToken(string $client_token)
    {
        $server_token = $this->redis->hGet('user_token',$client_token);//利用客户端Token 拉取服务端的用户数据
        if(empty($server_token)){//客户端Token 不存在
            return false;
        }
        $info = json_decode($server_token,false);
        $time = time() - $info->create_time;
        if($time > $this->expire_time){//检测到客户端token 过期
            $this->redis->hDel('user_token',$client_token);//检测到过期删除这个token
            return false;
        }
        //正常就返回 用户ID
        return $info->user_id;
    }
    
    /**
     * 检测哈希的Key
     * hashHas
     * @param $key
     * @param $hashKey
     * @return bool
     */
    public function hashHas($key,$hashKey)
    {
        $is_check = $this->redis->hExists($key,$hashKey);
        return $is_check;
    }
    
}
