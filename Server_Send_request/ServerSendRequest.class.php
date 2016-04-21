<?php
/**************************************************************
 * 服务器端发起请求工具类，包含以下内容：
 *  1.提供同步和异步两种服务器发起访问的方式
 *  2.提供curl/fsocket 两种方式
 *  3.对每种访问方式，提供必需的依赖和参数设置检测
 * 
 **************************************************************/
namespace XuXiaoZhou\Php;

ini_set('display_errors', false);

class ServerSendRequest 
{    
    const  VISIT_TYPE_NOT_EXIST = "所指定的访问方式不存在";
    const  MODULE_NOT_LOADED = "模块未加载";
    const  READ_DATA_TIMEOUT = "读取数据超时";
    /*
     * @property array $visit_type 访问方式
     */
    private $visit_type = array(
        'async' => array(
            'fsocket' => '_fsockSendRequest',
            'socket' => '_socketSendRequet'
        ),
        'sync' => array(
            'fsocket' => '_fsockSendRequest',
            'curl' => '_curlSendRequest',
            'socket' => '_socketSendRequet'
        )
    );
    
    /*
     * @property array $is_async 是否使用异步访问
     */
    private $is_async = false;
    

    /*
     * @property array connect_info 连接信息 
     */
    private $connect_info = array(
        'host' => null,
        'port' => 80,
        'connect_timeout' => 5,  //建立链接的时间限制 ，单位 秒
        'read_timeout' => 5   //读取数据的时间限制，单位 秒
    );
    
        
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
     * @desc 执行初始化
     * -----------------------------------------
     */
    public function init($conf) {
         $this->response = array('body'=> '', 'header'=> '');
         $this->error = array('errno'=> 0, 'errmsg'=> '');
         $this->is_async = $conf['is_async']? $conf['is_async']: false;
         $this->connect_info['connect_timeout'] = $conf['timeout']? $conf['timeout']: 10;
         $this->connect_info['host'] = $conf['host'];
         $this->connect_info['port'] = $conf['port'];
    }
    
    
    /**
     * -----------------------------------------
     * @desc 发起请求
     * ------------------------------------------------
     * @param string type: get/post 
     * ------------------------------------------------
     * @param array data: 要发送的数据
     * ------------------------------------------------
     * @param string url: 要访问的url
     * ------------------------------------------------
     * @param string visit_type: 访问方式 
     * ------------------------------------------------
     */
    public function sendRequest($url, $type, $data, $visit_type = 'fsocket', $options = array()) 
    { 
        $func_name = $this->is_async? $this->visit_type['async'][$visit_type]: $this->visit_type['sync'][$visit_type];
        if (empty($func_name)) {
            $this->error['errmsg'] = self::VISIT_TYPE_NOT_EXIST;
            return false;   
        }
        if ($this->checkDependency($visit_type)) {
            return $this->$func_name($url, $type, $data, $options);
        }
        return false;
    }
    
    /**
     * -----------------------------------------
     * @desc 获取响应
     * -----------------------------------------
     */
    public function getResponse(){
        return $this->response;
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
    private function _fsockSendRequest($url, $type, $data) 
    {
       $fp = fsockopen(
           $this->connect_info['host'], 
           $this->connect_info['port'], 
           $this->error['errno'], 
           $this->error['errmsg'], 
           $this->connect_info['connect_timeout']
       );
       if ($fp) {
           stream_set_blocking($fp, $this->is_async? 0: 1);
           stream_set_timeout($fp, $this->connect_info['read_timeout']);
           
           //发起get或post请求
           $send_str = $this->_buildOrginHttpRequest($url, $type, $data);
           fwrite($fp, $send_str);
           //在同步请求的前提下，获取响应内容
           if ($this->is_async == false) {
               $flag = 0;
               $header = "";
               
               $info = stream_get_meta_data($fp);
               while (!feof($fp) && !$info['timed_out']) {
                   $tmp_data = fgets( $fp );
                   $info = stream_get_meta_data($fp);
                   
                   if ($tmp_data == "\r\n") {
                       $flag = 1; 
                       continue;
                   }
                   if ($flag == 1) {
                       $this->response['body'].= $tmp_data;
                   } 
                   else {
                       $header.= $tmp_data;
                   }
               }
               
               if ($info['timed_out']) {
                   $this->error = array(
                        'errno' => 'fsocket_error',
                        'errmsg' => '【read_data error】:'.self::READ_DATA_TIMEOUT
                   );
                   return false;
               }
               $this->_dealHeaderInfo($header);
           }
           fclose($fp);
       }
       return empty($this->error['errno']);
       
    }
    
    
    /**
     * @desc 使用php原生socket 函数初始化一个连接导致指定的主机 ，并发起请求
     *    注意：这里只设置了返回的数据的读取时间，没有设置链接超时时间
     * ------------------------------------------------
     * @param string type: get/post
     * ------------------------------------------------
     * @param array data: 要发送的数据
     * ------------------------------------------------
     * @param string url: 要访问的url
     * ------------------------------------------------
     */
    private function _socketSendRequet($url, $type, $data)
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            $this->error = "socket_create() failed: reason: " . socket_strerror(socket_last_error());
            return false;
        }
        //设置socket中数据的读取时间
        socket_set_option($socket,SOL_SOCKET,SO_RCVTIMEO,array("sec"=> $this->connect_info['read_timeout'], "usec"=>0 ) );
        
        $address = gethostbyname($this->connect_info['host']);
        $connect = socket_connect($socket, $address, $this->connect_info['port']);
        if ($connect === false) {
            $this->error =  "socket_connect() failed.\nReason: ($connect) " . socket_strerror(socket_last_error($socket));
            return false;
        }
       
        //发起get或post请求
        $send_str = $this->_buildOrginHttpRequest($url, $type, $data);
        socket_write($socket, $send_str, strlen($send_str));

        if (!$this->is_async) {
            //解析响应
            $flag = 0;
            $header = '';
            while ($tmp_data = socket_read($socket,  2048)) {
                
                if ($flag == 1) {
                    $this->response['body'] .= $tmp_data;
                    continue;
                }
                
                //处理http头信息
                $tmp_slice = preg_split('/\r\n/', $tmp_data);
                if (count($tmp_slice) > 1) {
                   
                   for ($i = 0; $i < count($tmp_slice); $i++) {
                       if ($tmp_slice[$i] == '') {
                          $flag = 1;
                       }
                       
                       if ($flag == 0) {
                           $header .= $tmp_slice[$i]."\r\n";
                       } else {
                           $this->response['body'] .= $tmp_slice[$i]."\r\n";
                       }
                   }
               }
            }
            $this->_dealHeaderInfo($header);
        }
        
        if (socket_last_error($socket) > 0) {
            $this->error = array(
                'errno' => socket_last_error($socket),
                'errmsg' => '【socket error】:'.socket_strerror(socket_last_error($socket))
            );
        }
        socket_close($socket);
        return empty($this->error['errno']);
        
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
    private function _curlSendRequest($url, $type, $data, $options) 
    {
        $ch = curl_init(); 
        $default_set = array(
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connect_info['connect_timeout'],
            CURLOPT_TIMEOUT => $this->connect_info['read_timeout'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_PORT => $this->connect_info['port'],
            CURLOPT_HTTPHEADER => "Host: {$this->connect_info['host']}"
        );
        curl_setopt_array($ch, $default_set);
        
        if ($options) {
            curl_setopt_array($ch, $options);
        }
        
        if ($type == 'post') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } else {
            $url .= strpos($url, '?') !== false ? "&".http_build_query($data): "?".http_build_query($data); 
        }
        
        curl_setopt($ch,CURLOPT_URL, $url);
        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->_dealHeaderInfo(substr($response, 0, $header_size));
        $this->response['body'] = substr($response, $header_size);
        $this->error['errno'] = curl_errno($ch);
        $this->error['errmsg'] = curl_error($ch);
        curl_close($ch);
        return empty($this->error['errno']);
    }
    
    /**
     * @desc 检测所使用的访问方式需要的依赖
     * ------------------------------------------------
     */
    public function checkDependency($type) 
    {
        $type_map = array(
            'curl' => '_checkCurlDependency',
            'fsocket' => '_checkFsocketDependency',
            'socket' => '_checkSocketDependency'
        );
        $func_name = $type_map[$type];
        return $this->$func_name();
    }
    
    /**
     * @desc 检测curl依赖
     * ------------------------------------------------
     */
    private function _checkCurlDependency() 
    {
        if (!extension_loaded('curl')) {
            $this->error['errmsg'] = 'curl '.self::MODULE_NOT_LOADED; 
        }
        $this->error['errmno'] = $this->error['errmsg']? "curl_error": '';
        return empty($this->error['errno']);
    }
    
    /**
     * @desc 检测Fsocket依赖
     * ------------------------------------------------
     */
    private function _checkFsocketDependency()
    {
        return true;
    }
    
    /**
     * @desc 检测socket依赖
     * ------------------------------------------------
     */
    private function _checkSocketDependency()
    {
        if (!extension_loaded('sockets')) {
            $this->error['errmsg'] = 'socket '.self::MODULE_NOT_LOADED; 
        }
        $this->error['errno'] = $this->error['errmsg']? "socket_error": '';
        return empty($this->error['errno']);
    }
    
    /**
     * @desc 处理头信息
     * ------------------------------------------------
     */
    private function _dealHeaderInfo($header) 
    {
        $header_rows = preg_split('/\r\n/', $header);
        foreach($header_rows as $row) {
            
            if (empty($row)) {
                continue;
            }
            $row_piece = preg_split('/:/', $row, 2);
            if (count($row_piece) == 1) {
                $this->response['header']['Host'] = $row_piece[0];
            }
            elseif ($this->response['header'][$row_piece[0]] && !is_array($this->response['header'][$row_piece[0]]) ) {
                $this->response['header'][$row_piece[0]] = array( $this->response['header'][$row_piece[0]] );
                $this->response['header'][$row_piece[0]][] = $row_piece[1];
            } elseif (is_array($this->response['header'][$row_piece[0]])) {
                $this->response['header'][$row_piece[0]][] = $row_piece[1];
            } else {
                $this->response['header'][$row_piece[0]] = $row_piece[1];
            }
        }
    }
    
    /**
     * @desc 建立原生http请求
     * ------------------------------------------------
     * @param string $url: 要访问的url 
     * ------------------------------------------------
     * @param string $type: 访问方式 get/post 
     * ------------------------------------------------
     * @param array $addition_header: 附加的头信息
     * ------------------------------------------------
     */
    private function _buildOrginHttpRequest($url, $type, $data, $addition_header = array()) 
    {
        //发起get或post请求
        $content = http_build_query($data);
        $addition_header_str = empty($addition_header)? "": join('\r\n', $addition_header)."\r\n";
        if ($type == 'get') {
            $url .= strpos( $url, '?') !== false ? "&".$content: "?".$content;
            $request_str = "".
                "GET {$url} HTTP/1.1\r\n".
                "Host: {$this->connect_info[host]}\r\n".
                "Connection: close\r\n".
                $addition_header_str.
                "\r\n";
        }
        else {
            $request_str = "".
                "POST {$url} HTTP/1.1\r\n".
                "Host: {$this->connect_info[host]}\r\n".
                "Content-Type: application/x-www-form-urlencoded\r\n".
                "Content-Length: " . strlen($content) . "\r\n".
                "Connection: close\r\n".
                $addition_header_str.
                "\r\n";
        }
        return $request_str;
    }
}

//test
$request_obj = new ServerSendRequest();
$request_obj->init(array('host'=>'zhibo.jindinghui.com.cn', 'port'=> 80, 'is_async'=> false));
$request_obj->sendRequest('http://zhibo.jindinghui.com.cn/index2.php?fid=1001', 'post', array(), 'socket');
print_r($request_obj->getErrorMsg());
print_r( $request_obj->getResponse());
