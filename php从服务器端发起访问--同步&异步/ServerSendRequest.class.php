<?php
/**************************************************************
 * 服务器端发起请求工具类，包含以下内容：
 *  1.提供同步和异步两种服务器发起访问的方式
 *  2.提供curl/fsocket 两种方式
 *  3.对每种访问方式，提供必需的依赖和参数设置检测
 * 
 **************************************************************/
namespace XuXiaoZhou\Php;

class ServerSendRequest {
    /*
     * @property array $visit_type 访问方式
     */
    private $visit_type = array(
        'fsocket' => '_fsockSendRequest',
        'curl' => '_curlSendRequest' 
    );
    
    /*
     * @property array $is_async 是否使用异步访问
     */
    private $is_async = false;
    
    
    /*
     * @property string $host 要访问的主机
     */
    private $host = null;
    
    /*
     * @property int $port 要访问的主机
     */
    private $port = 80;
    
    /*
     * @property int timeout 从链接读取数据超时时间
     */
    private $timeout = 10;
        
    /*
     * @property array $error 错误信息
     */
    private $error = array(
        'errno' => 0,
        'errmsg' => ''
    );
    
    /*
     * @property array $response 返回的数据,包含头部和响应体
     */
    private $response = array(
        'body' => '',
        'header' => ''
    );
    
    /**
     * -----------------------------------------
     * @desc 发起请求
     * -----------------------------------------
     */
    public function init() {
         
    }
    
    
    /**
     * -----------------------------------------
     * @desc 发起请求
     * -----------------------------------------
     */
    public function sendRequest() {
   
    }
    
    /**
     * -----------------------------------------
     * @desc 获取响应
     * -----------------------------------------
     */
    public function getResponse(){
        return $this->response_text;
    }
    
    /**
     * -----------------------------------------
     * @desc 获取错误信息
     * -----------------------------------------
     */
    public function getErrorMsg(){
        return $this->error;
    }
    
    /**
     * @desc 使用fsocketopen 函数初始化一个连接导致指定的主机 ，并发起请求
     * ------------------------------------------------
     * @param string type: get/post 
     * ------------------------------------------------
     * @param array data: 要发送的数据
     * ------------------------------------------------
     * @param string url: 要访问的url
     * ------------------------------------------------
     */
    private function _fsockSendRequest( $type, $data, $url) 
    {
       $fp = fsockopen($this->host, $this->port, $this->error['errno'], $this->error['errmsg']);
       if( $fp ) {
           stream_set_blocking($fp, $this->is_async? 0: 1);
           stream_set_timeout($fp, $this->timeout);
           $content = http_build_query($data);
           
           //发起get或post请求
           if( $type == 'get' ) {
               $url .= strpos( $url, '?') != -1 ? $content: "?".$content; 
               $send_str = "".
                   "GET {$url} HTTP/1.1\r\n".
                   "Host: {$this->host}\r\n".
                   "Connection: close\r\n".
                   "\r\n";
           } 
           else {
               $send_str = "".
                   "POST {$url} HTTP/1.1\r\n".
                   "Host: {$this->host}\r\n".
                   "Content-Type: application/x-www-form-urlencoded\r\n".
                   "Content-Length: " . strlen($content) . "\r\n".
                   "Connection: close\r\n".
                   "\r\n";
           }
           fwrite($fp, $send_str);
           
           //在同步请求的前提下，获取响应内容
           if( $this->is_async == false ) {
               $flag = 0;
               
               while( !feof( $fp ) ) {
                   $tmp_data = fgets( $fp );
                   
                   if( $tmp_data == "\r\n" ) {
                       $flag = 1; 
                       continue;
                   }
                   if( $flag == 1 ) {
                       $this->response['body'].= $tmp_data;
                   } else {
                       $tmp_data = preg_replace('/\r\n/', '', $tmp_data);
                       $header_piece = preg_split('/:/', $tmp_data);
                       $this->response['header'][$header_piece[0]] = $header_piece[1];
                   }
               }
           }
           fclose($fp);
       }
       
    }
    
    /**
     * @desc 使用curl 发起请求
     * ------------------------------------------------
     * @param string type: get/post
     * ------------------------------------------------
     * @param array data: 要发送的数据
     * ------------------------------------------------
     * @param string url: 要访问的url
     * ------------------------------------------------
     * @param array $options: 附加的curl选项参数
     * ------------------------------------------------
     * 
     */
    private function _curlSendRequest( $type, $data, $url, $options) {
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt_array($ch, $options);
        
        if( $options ) {
            curl_setopt_array($ch, $options);
        }
        
        if( $type == 'post' ) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } else {
            $url .= strpos( $url, '?') != -1 ? http_build_query($data): "?".http_build_query($data); 
        }
        
        curl_setopt(CURLOPT_URL, $url);
        $this->response_text = curl_exec($ch);
        $this->error['errno'] = curl_errno( $ch );
        $this->error['errmsg'] = curl_error( $ch );
    }
    
}