<?php
/**************************************************************
 * �������˷������󹤾��࣬�����������ݣ�
 *  1.�ṩͬ�����첽���ַ�����������ʵķ�ʽ
 *  2.�ṩcurl/fsocket ���ַ�ʽ
 *  3.��ÿ�ַ��ʷ�ʽ���ṩ����������Ͳ������ü��
 * 
 **************************************************************/
namespace XuXiaoZhou\Php;

include "../ChromePhp.php";

class ServerSendRequest 
{    
    const  VISIT_TYPE_NOT_EXIST = "��ָ���ķ��ʷ�ʽ������";
    /*
     * @property array $visit_type ���ʷ�ʽ
     */
    private $visit_type = array(
        'async' => array(
            'fsocket' => '_fsockSendRequest'
        ),
        'sync' => array(
            'fsocket' => '_fsockSendRequest',
            'curl' => '_curlSendRequest',
        )
    );
    
    /*
     * @property array $is_async �Ƿ�ʹ���첽����
     */
    private $is_async = false;
    
    
    /*
     * @property string $host Ҫ���ʵ�����
     */
    private $host = null;
    
    /*
     * @property int $port Ҫ���ʵ�����
     */
    private $port = 80;
    
    /*
     * @property int timeout �����Ӷ�ȡ���ݳ�ʱʱ��
     */
    private $timeout = 10;
        
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
         $this->timeout = $conf['timeout']? $conf['timeout']: 10;
         $this->host = $conf['host'];
         $this->port = $conf['port'];
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
    public function sendRequest($url, $type, $data, $visit_type = 'fsock', $options = null) {     
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
        return $this->response_text;
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
       $fp = fsockopen($this->host, $this->port, $this->error['errno'], $this->error['errmsg']);
       if ($fp) {
           stream_set_blocking($fp, $this->is_async? 0: 1);
           stream_set_timeout($fp, $this->timeout);
           $content = http_build_query($data);
           
           //����get��post����
           if ($type == 'get') {
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
           
           //��ͬ�������ǰ���£���ȡ��Ӧ����
           if ($this->is_async == false) {
               $flag = 0;
               
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
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt_array($ch, $options);
        
        if ($options) {
            curl_setopt_array($ch, $options);
        }
        
        if ($type == 'post') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } else {
            $url .= strpos($url, '?') != -1 ? http_build_query($data): "?".http_build_query($data); 
        }
        
        curl_setopt(CURLOPT_URL, $url);
        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header_rows = preg_split('/\r\n/', substr($response, 0, $header_size));
        foreach($header_rows as $row) {
            $row_piece = preg_split('/:/', $header_rows);
            $this->response['header'][$row_piece[0]] = $row_piece[1];
        }
        
        $this->response['body'] = substr($response, $header_size+1);
        $this->error['errno'] = curl_errno($ch);
        $this->error['errmsg'] = curl_error($ch);
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
        );
        return $type_map[$type]();
    }
    
    /**
     * @desc ���curl����
     * ------------------------------------------------
     */
    private function _checkCurlDependency($type) 
    {
        if (!extension_loaded('curl')) {
            $this->error['errmsg'] = 'curl ģ��δ����'; 
        }
        $this->error['errmno'] = $this->error['errmsg']? "curl_error": '';
    }
    
    /**
     * @desc ���curl����
     * ------------------------------------------------
     */
    private function _checkFsocketDependency($type)
    {
        if (!extension_loaded('curl')) {
            $this->error['errmsg'] = 'curl ģ��δ����';
        }
        $this->error['errmno'] = $this->error['errmsg']? "curl_error": '';
    }
    
}
\ChromePhp::log('ss');
