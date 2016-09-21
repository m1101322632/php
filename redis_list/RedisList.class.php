<?php
include_once "vendor/autoload.php";

use Monolog\Logger;

use Monolog\Handler\StreamHandler;

use Monolog\Handler\FirePHPHandler;

/**
 * redis 队列操作类，提供以下功能：
 *   · 报警线设置
 *   · 最大长度设置
 *   · 超过报警线或最大长度了报警
 *   · 同一事件报警间隔设置
 * Date: 2016/9/2
 * Time: 11:21
 */
class RedisList
{
    //报警线
    private $warming_line = 0;
    //最大长度限制
    private $max_length = 0;
    //报警时间间隔
    private $log_interval = 0;
    //redis链接
    private static $redis_conn = null;
    //monolog obj
    private $monolog = null;
    //redis 配置
    private $redis_cfg = null;

    const LIST_LONGER_THAN_WARMING_LINE = 1;
    const LIST_LONGER_THAN_MAX_LENGTH = 2;


    public function __construct( $cfg = null)
    {
        if ($cfg) {
            $this->setCfg($cfg);
        }
        $this->monolog = new Logger('my_reids_logger');
        $this->monolog->pushHandler(new StreamHandler(__DIR__.'/redis_list.log', Logger::DEBUG));
    }


    public static function setRedis($host, $port, $select =0) {
        if (self::$redis_conn == null) {
            self::$redis_conn = new Redis();
            self::$redis_conn->open($host, $port);
            self::$redis_conn->select($select);
        }
    }
    /**
     * @desc 对对象调用的方法进行分类处理
     */
    public function __call($name, $argumets)
    {
        $methods_union = array(
            'lPush', 'rPush',  'lInsert',
        );
        if (in_array($name, $methods_union)) {

            if (!$this->isLongerThanMaxLength($argumets[0])) {
                $this->isLongerThanWarmingLine($argumets[0]);
            } else {  //超过队列最大长度则返回false
                return false;
            }
        }
        return call_user_func_array(array(self::$redis_conn, $name), $argumets);
    }

    /**
     * @desc 设置配置项
     * @param $config
     */
    public function setCfg($config)
    {
        $valid_cfg = array(
            'warming_line',
            'max_length',
            'log_interval'
        );
        foreach($config as $key => $item) {
            if (in_array($key, $valid_cfg)) {
                $this->$key = $item;
            }
        }
    }

    public function getCfg($name)
    {
        $valid_cfg = array(
            'warming_line',
            'max_length',
            'log_interval'
        );
        if (in_array($name, $valid_cfg)) {
            return $this->$name;
        } else {
            return false;
        }
    }

    /**
     * 报警线检测
     */
    private  function isLongerThanWarmingLine($list_name)
    {
        if ($this->warming_line > 0 && self::$redis_conn->lSize($list_name) > $this->warming_line) {
            $this->writeLog(self::LIST_LONGER_THAN_WARMING_LINE,  array('list'=> $list_name,'length' => self::$redis_conn->lSize($list_name)));
        }
        return true;
    }

    /**
     * 最大长度检测
     */
    private function isLongerThanMaxLength($list_name)
    {
        if ($this->max_length > 0 && self::$redis_conn->lSize($list_name) > $this->max_length) {
            $this->writeLog(self::LIST_LONGER_THAN_MAX_LENGTH, array('list'=> $list_name, 'length' => self::$redis_conn->lSize($list_name)));
            return true;
        } else {
            return false;
        }
    }

    /**
     * @desc 记录日志
     */
    private function writeLog($type, $data)
    {
        switch ($type) {
            case self::LIST_LONGER_THAN_MAX_LENGTH :
                if ($this->isTimeIntvalGone($data['list'])) {
                    $this->monolog->addInfo("list is longer than max_length【{$this->max_length}】." . print_r($data, true));
                }
                break;
            case self::LIST_LONGER_THAN_WARMING_LINE:
                if ($this->isTimeIntvalGone('max_length')) {
                    $this->monolog->addInfo("list is longer than warming_linie【{$this->warming_line}】.".print_r($data, true));
                }
                break;
        }
    }

    /**
     * @desc 时间间隔是否已经耗尽
     *
     * @return bool true -- 时间间隔已到  false --时间间隔未到
     */
    private function isTimeIntvalGone($key)
    {
        $key_name = __CLASS__.'log_intval_'.$key;
        if (self::$redis_conn->get($key_name)) {
            return false;
        } else {
            self::$redis_conn->set($key_name, true, $this->log_interval);
            return true;
        }
    }
}