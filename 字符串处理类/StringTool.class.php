<?php
/**
 * 字符串处理小工具
 * 
 * @author Mr Xu
 * @since 2015-12-25
 */
class StringTool {
    /**
     * @desc 中文字符串截取，规则如下：
     *   1.依据给定的编码类型截取指定长度的字符串
     *   2.可以按要求指定英文和英文符号是算作一个字符还是算作半个字符 
     *   
     * @param string $str  待截取的字符串
     * @param int $length 要截取的长度
     * @param int $length_type 
     *     字符长度的类型：
     *         0 --- 英文字母、英文字符被当做0.5个字符
     *         1 --- 英文字母、英文字符被当做1个字符
     * @param string $postfix 后缀 
     * @param string $code_type 字符编码
     */
    function cutStrs($str, $length, $length_type = 1, $postfix="...", $code_type = "UTF-8"){
        $cut_str_length = 0.0;  //要截取的字符串长度
        $cut_str_bytes = 0;     //要截取的字符串的字节数
        $str_length = strlen($str);
        
        if (empty($str)) {
            $return_str = $str;
        } 
        elseif (strtolower($code_type) == "utf-8") {
            $single_char_bytes = 0;
            
            for ($i = 0; $i < $str_length; $i += $single_char_bytes) {
                $asc = ord($str[$i]);
                
                if ($asc < 128) {
                    $single_char_bytes = 1;
                    
                    if (($asc < 127 && $asc >=32) || in_array($asc, array(9, 10))) {
                        $single_char_length = $length_type? 1: 0.5;
                    }   
                }
                else {
                    $single_char_bytes = 1;
                    $divisor = 192;
                    //计算当前字符使用UTF8编码需用几个字节来编码
                    while($asc / $divisor > 1 && $single_char_bytes < 6){
                        $divisor = 128 + ($divisor >>1);
                        $single_char_bytes++;
                    }
                    $single_char_length = 1;  
                } 
                $cut_str_bytes += $single_char_bytes;
                $cut_str_length += $single_char_length;
                //因为存在字符长度有0.5和1两种格式，所以在截取后指定长度后要进行长度修正
                if($cut_str_length >= $length) { 
                    $cut_str_bytes = $cut_str_length > $length? $cut_str_bytes - $single_char_bytes: $cut_str_bytes;
                    break;
                }
            }
            $return_str = substr($str, 0, $cut_str_bytes);        
        }
        else {  // utf-8之外的编码
            for ($i = 0; $i < $str_length;) {
                $single_char_length =  ord($str[$i]) < 127 && $length_type == 0? 0.5: 1;
                
                if($cut_str_length + $single_char_length > $length){
                    break;
                }
                $cut_str_bytes += ord($str[$i]) > 127? 2: 1;
                $cut_str_length += $single_char_length;
                $i = ord($str[$i]) > 127? $i + 2: $i + 1;
            }
            $return_str = substr($str, 0, $cut_str_bytes);  
        }
        
        return $return_str? $return_str.$postfix: $return_str; 
    }
}
?>