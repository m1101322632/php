<?php
/**************************************************************
 * �������˷������󹤾��࣬�����������ݣ�
 *  1.�ṩͬ�����첽���ַ�����������ʵķ�ʽ
 *  2.�ṩcurl/fsocket ���ַ�ʽ
 *  3.��ÿ�ַ��ʷ�ʽ���ṩ����������Ͳ������ü��
 * 
 **************************************************************/
namespace XuXiaoZhou\Php;

ini_set('display_errors', false);

class ServerSendRequest 
{    
    const  VISIT_TYPE_NOT_EXIST = "��ָ���ķ��ʷ�ʽ������";
    const  MODULE_NOT_LOADED = "ģ��δ����";
    /*
     * @property array $visit_type ���ʷ�ʽ
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
     * @property array $is_async �Ƿ�ʹ���첽����
     */
    private $is_async = false;
    

    /*
     * @property array connect_info ������Ϣ 
     */
    private $connect_info = array(
        'host' => null,
        'port' => 80,
        'connect_timeout' => 5,  //�������ӵ�ʱ������ ����λ ��
        'read_timeout' => 5     //��ȡ���ݵ�ʱ�����ƣ���λ ��
    );
    
        
    /*
     * @property array $error ������Ϣ
     */
    private $error = array(
        'errno' => 0,
        'errmsg' => ''
    );
    
    /*
     * @property array $response ���ص�����,����ͷ������Ӧ��
     */
    private $response = array(
        'body' => '',
        'header' => ''
    );
    
    /**
     * -----------------------------------------
     * @desc ִ�г�ʼ��
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
     * @desc ��������
     * ------------------------------------------------
     * @param string type: get/post 
     * ------------------------------------------------
     * @param array data: Ҫ���͵�����
     * ------------------------------------------------
     * @param string url: Ҫ���ʵ�url
     * ------------------------------------------------
     * @param string visit_type: ���ʷ�ʽ 
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
     * @desc ��ȡ��Ӧ
     * -----------------------------------------
     */
    public function getResponse(){
        return $this->response;
    }
    
    /**
     * -----------------------------------------
     * @desc ��ȡ������Ϣ
     * -----------------------------------------
     */
    public function getErrorMsg(){
        return $this->error;
    }
    
    /**
     * @desc ʹ��fsocketopen ������ʼ��һ�����ӵ���ָ�������� ������������
     * ------------------------------------------------
     * @param string type: get/post 
     * ------------------------------------------------
     * @param array data: Ҫ���͵�����
     * ------------------------------------------------
     * @param string url: Ҫ���ʵ�url
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
           $content = http_build_query($data);
           
           //����get��post����
           if ($type == 'get') {
               $url .= strpos( $url, '?') !== false ? "&".$content: "?".$content; 
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
           //��ͬ�������ǰ���£���ȡ��Ӧ����
           if ($this->is_async == false) {
               $flag = 0;
               $header = "";
               
               while (!feof($fp)) {
                   $tmp_data = fgets( $fp );
                   
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
               $this->_dealHeaderInfo($header);
           }
           fclose($fp);
       }
       return empty($this->error['errno']);
       
    }
    
    
    /**
     * @desc ʹ��phpԭ��socket ������ʼ��һ�����ӵ���ָ�������� ������������
     *    ע�⣺����ֻ�����˷��ص����ݵĶ�ȡʱ�䣬û���������ӳ�ʱʱ��
     * ------------------------------------------------
     * @param string type: get/post
     * ------------------------------------------------
     * @param array data: Ҫ���͵�����
     * ------------------------------------------------
     * @param string url: Ҫ���ʵ�url
     * ------------------------------------------------
     */
    private function _socketSendRequet($url, $type, $data)
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            $this->error = "socket_create() failed: reason: " . socket_strerror(socket_last_error());
            return false;
        }
        //����socket�����ݵĶ�ȡʱ��
        socket_set_option($socket,SOL_SOCKET,SO_RCVTIMEO,array("sec"=> $this->connect_info['read_timeout'], "usec"=>0 ) );
        
        $address = gethostbyname($this->connect_info['host']);
        $connect = socket_connect($socket, $address, $this->connect_info['port']);
        if ($connect === false) {
            $this->error =  "socket_connect() failed.\nReason: ($connect) " . socket_strerror(socket_last_error($socket));
            return false;
        }
        stream_set_blocking($socket, $this->is_async? 0: 1);
       
        //����get��post����
        $content = http_build_query($data);
        if ($type == 'get') {
            $url .= strpos( $url, '?') !== false ? "&".$content: "?".$content;
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
        socket_write($socket, $send_str, strlen($send_str));
        
       
        if (!$this->is_async) {
            //������Ӧ
            $flag = 0;
            $header = '';
            while ($tmp_data = socket_read($socket,  2048)) {
               
                if ($flag == 1) {
                    $this->response['body'] .= $tmp_data;
                    continue;
                    }
                
                //����httpͷ��Ϣ
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
        socket_close($socket);
        return empty($this->error['errno']);
        
    }
    
    /**
     * @desc ʹ��curl ��������
     * ------------------------------------------------
     * @param string type: get/post
     * ------------------------------------------------
     * @param array data: Ҫ���͵�����
     * ------------------------------------------------
     * @param string url: Ҫ���ʵ�url
     * ------------------------------------------------
     * @param array $options: ���ӵ�curlѡ�����
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
     * @desc �����ʹ�õķ��ʷ�ʽ��Ҫ������
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
     * @desc ���curl����
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
     * @desc ���Fsocket����
     * ------------------------------------------------
     */
    private function _checkFsocketDependency()
    {
        return true;
    }
    
    /**
     * @desc ���socket����
     * ------------------------------------------------
     */
    private function _checkSocketDependency()
    {
        if (!extension_loaded('socket')) {
            $this->error['errmsg'] = 'socket '.self::MODULE_NOT_LOADED; 
        }
        $this->error['errmno'] = $this->error['errmsg']? "socket_error": '';
        return empty($this->error['errno']);
    }
    
    /**
     * @desc ����ͷ��Ϣ
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
}

//test
$request_obj = new ServerSendRequest();
$request_obj->init(array('host'=>'zhibo.jindinghui.com.cn', 'port'=> 80, 'is_async'=> false));
$request_obj->sendRequest('http://zhibo.jindinghui.com.cn/index.php?fid=1001', 'post', array(), 'socket');
// print_r($request_obj->getErrorMsg());
print_r( $request_obj->getResponse());
