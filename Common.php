<?php
/**
 * File Name: Common.php
 * User : zhangLin
 * Time : 2020/4/22 --- 22:30
 */

namespace Im\Common;

define('DEBUG',false);
define('DIR',dirname(__FILE__));
define('DS',DIRECTORY_SEPARATOR);

class Common
{
    private $head           = '';
    private $message        = '';//日志写入数据
    private $path           = '';
    private $dividing_line  = '------------------------------------------------------------------------'.PHP_EOL;
    protected $error_json     = '';//异常描述
    
    public function __construct()
    {
        date_default_timezone_set('PRC');//设置时区
        $this->path = DIR. DS .'log'. DS .date('Y-m-d').DS;
    }
    
    
    /********************************************* 可继承的接口 ********************************************************/
    
    /**
     * 检测json 是否正常
     * checkJson
     * @param string $json
     * @param array $param
     * @return false|mixed|string
     * @author: zhanglin
     */
    protected function checkJson(string $json,array $param = ['client_token','type','to','message','resources']){
        if(empty($json)){
            $this->error_json = $this->json(404,'未接收到数据');
            return false;
        }
        $jsonObject = json_decode($json,false);//强制转换为json 对象
        if(empty($jsonObject) && is_object($jsonObject)){//检测必须有值 且为 json对象
            $this->error_json = $this->json(404,'json 格式错误,无法解析!');
            return false;
        }
        if(empty($param)){
            $this->error_json = $this->json(500,'json 格式检测失败');
            return false;
        }
        $success = (object)[];
        foreach($param as $v){
            if(empty($jsonObject->$v) && $jsonObject->$v!='' && $jsonObject->$v==0){
                $error[] = $v;
            }else{
                $success->$v = $jsonObject->$v;
            }
        }
        if(!empty($error)){
            $this->error_json = $this->json(403,'参数：'.implode(',',$error).';检测不存在!');
            return false;
        }
        //开始检测 字段的值 是否合法
        $resources = $this->checkUrl($success->resources);//检测url
        if($resources && is_string($resources)){
            $success->file_type = $resources;
        }
        $messgae = $this->checkUserMessage($success->messgae);//检测 用户发过来的 信息
        if($messgae && is_string($messgae)){
            $success->messgae = $messgae;
        }
        if($resources==false || $messgae==false){
            return false;
        }
        //正常就返回json 对象
        return $success;
    }
    
    /**
     * 输出json 文件
     * json
     * @param int $code
     * @param string $msg
     * @param array $data
     * @param array $other
     * @return false|string
     * @author: zhanglin
     */
    protected function json($code=200,$msg='Success',$data=[],array $other=[])
    {
        $return_arr = [
            'status'=>$code,
            'msg'=>$msg,
            'data'=>$data,
        ];
        //$this->writeLog($msg,'error');
        if(DEBUG===false && $code!==200){
            $return_arr['msg'] = 'WebSocket 接收数据异常!';
        }
        if(!empty($other)){//根据需求追加数据
            foreach ($other as $k=>$v){
                $return_arr[$k] = $v;
            }
        }
        return json_encode($return_arr);
    }
    
    /**
     * 写入 日志函数(脚本运行日志)
     * write
     * @param string $message
     * @author: zhanglin
     */
    protected function write(string $message,string $level)
    {
        if(DEBUG===true){//开启debug 将日志输出到 控制台
            echo $message.PHP_EOL;
        }else{
            $this->message = $message;
            if($this->checkFile($this->path)){
                switch($level){
                    case 'error':$this->error();break;
                    case 'info':$this->info();break;
                    case 'sql':$this->sql();break;
                    case 'warning':$this->warning();break;
                }
            }
        }
    }
    
    /**
     * 设置头部信息
     * head
     * @author: zhanglin
     */
    protected function head($request=null)
    {
        $request_info = 'Message';
        if(!empty($request)){
            $request_info = $request->server['remote_addr']." ".$request->server['request_method']." ".$request->header['origin'];
        }
        $this->head = $this->dividing_line."[".date('Y-m-d H:i:s')."]  ".$request_info.PHP_EOL;
    }
    
    /*********************************************** 不可用接口 ********************************************************/
    
    /**
     * 写入错误日志
     * error
     * @author: zhanglin
     */
    private function error()
    {
        $message = $this->head."[ error ]  ".$this->message.PHP_EOL;
        $this->writeFile($this->path."error.log",$message);
    }
    
    /**
     * 写入info 日志
     * info
     * @param string $message
     * @author: zhanglin
     */
    private function info()
    {
        $message = $this->head."[ info ]  ".$this->message.PHP_EOL;
        $this->write($this->path."info.log",$message);
    }
    
    /**
     * 写入sql 操作日志
     * sql
     * @author: zhanglin
     */
    private function sql()
    {
        $message = $this->head."[ sql ]  ".$this->message.PHP_EOL;
        $this->write($this->path."sql.log",$message);
    }
    
    /**
     * 写入警告性错误
     * warning
     * @author: zhanglin
     */
    private function warning()
    {
        $message = $this->head."[ warning ]  ".$this->message.PHP_EOL;
        $this->write($this->path."notes.log",$message);
    }
    
    /**
     * 检测文件（创建文件）
     * checkFile
     * @param string $filename
     * @param bool $check true 检测文件/目录是否存在 不存在直接创建 false 仅检测
     * @return bool
     * @author: zhanglin
     */
    protected function checkFile(string $filename)
    {
        //检查文件是是否是目录/且目录是否存在
        if(!is_dir($filename)){
            mkdir(iconv("UTF-8", "GBK", $filename),0777,true);
        }
        //检测文件 是否存在 不存在创建文件
        if(!file_exists($filename)){
            touch($filename);
        }
        return true;
    }
    
    /**
     * 文件写入
     * writeFile
     * @param string $file_path
     * @param string $message
     */
    private function writeFile(string $file_path,string $message='')
    {
        $file = fopen($file_path, "a") or die("Unable to open file!");
        fwrite($file, $message."\n");
        fclose($file);
    }
    
    /**
     * 检测资源地址
     * checkUrl
     * @param string $url
     * @return bool|string
     */
    private function checkUrl(string $url)
    {
        $file_type = '';
        if(!empty($url)){
            if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
                $this->error_json = $this->json(403,'资源地址错误');
                return false;
            }
            $file_type = strtolower(pathinfo($url,PATHINFO_EXTENSION));
            return $file_type;
        }else{
            return $file_type;
        }
    }
    
    private function checkUserMessage(string $messgae)
    {
        $data = '';
        if(!empty($messgae)){
            $data = htmlspecialchars(addslashes($messgae));
            if(strlen($data)>2000){
                $this->error_json = $this->json(403,'文字过多无法处理');
                return false;
            }
            return $data;
        }else{
            return $data;
        }
    }
}
