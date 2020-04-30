<?php
/**
 * File Name : HelpCommon.php
 * User : zhanglin
 * Date : 2020/4/28
 * Time : 14:51
 */

namespace Im\Common;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;

require_once ('vendor/autoload.php');
require_once ('Common.php');

class HelpCommon extends Common
{
    private $dirname = '';
    private $tokenKey = 'afsaffsdfd546545645456';
    public function __construct()
    {
        parent::__construct();
        $this->dirname = __DIR__.DIRECTORY_SEPARATOR.'QrCode'.DIRECTORY_SEPARATOR.date('Y-m-d',time()).DIRECTORY_SEPARATOR;
        $this->checkFile($this->dirname);
    }
    
    /**
     * 生成二维码
     * generateQrCode
     * @param string $qrcode_txt
     * @param string $qrcode_name
     * @return string
     */
    public function generateQrCode(string $qrcode_txt,string $qrcode_name)
    {
        $qrCode  = new QrCode($qrcode_txt);
        $qrCode->setSize(300);
        // Set advanced options
        $qrCode->setWriterByName('png');
        $qrCode->setEncoding('UTF-8');
        $qrCode->setErrorCorrectionLevel(ErrorCorrectionLevel::HIGH());
        $qrCode->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0]);
        $qrCode->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0]);
        $qrCode->setLabel('扫码登录系统', 16);
//        $qrCode->setLogoPath(__DIR__.'/../assets/images/symfony.png');
//        $qrCode->setLogoSize(150, 200);
        $qrCode->setValidateResult(false);
        $qrCode->setRoundBlockSize(true);
        $qrCode->setMargin(10);
        $qrCode->setWriterOptions(['exclude_xml_declaration' => true]);
        $qrCode->writeFile($this->dirname.$qrcode_name.'.png');
        return $this->dirname.$qrcode_name.'.png';
        
    }
    /**
     * 字符加密和解密
     * encrypt
     * @param string $string 要加密/解密的字符串
     * @param string $operation 类型，E 加密；D 解密
     * @param string $key    密钥
     * @return false|string|string[]
     */
    protected function encrypt(string $string,string $operation,string $key)
    {
        if(empty($key) || empty($string) || empty($operation)){
            return false;
        }
        $key = md5($key);
        $key_length = strlen($key);
        $string = $operation == 'D' ? base64_decode($string) : substr(md5($string . $key), 0, 8) . $string;
        $string_length = strlen($string);
        $rndkey = $box = array();
        $result = '';
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($key[$i % $key_length]);
            $box[$i] = $i;
        }
        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        for ($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }
        if ($operation == 'D') {
            if (substr($result, 0, 8) == substr(md5(substr($result, 8) . $key), 0, 8)) {
                return substr($result, 8);
            } else {
                return '';
            }
        } else {
            return str_replace('=', '', base64_encode($result));
        }
    }
    protected function tempData($fd)
    {
        $token =  md5('zhang'.date('Y-m-d H:i').'lin'.$this->tokenKey);
        $data = '{"fd":'.$fd.',"temp_token":'.$token.'}';
        return $data;
    }
    
}
