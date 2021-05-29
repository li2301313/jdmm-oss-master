<?php

namespace Jdmm\Oss\Cache;

use Swoft\Bean\Annotation\Mapping\Bean;
use Swoft\Bean\Annotation\Mapping\Inject;
use Swoft\Redis\Pool;
/**
 * Created by PhpStorm.
 * User: 22784
 * Date: 2020/3/10
 * Time: 12:05
 */

/**
 * Class UploadCache
 * @package Jdmm\Oss\Cache
 * @Bean()
 */
class UploadCache
{

    /**
     * @Inject()
     *
     * @var Pool
     */
    private $redis;

    public function setData($key,$data,$expire)
    {
        return $this->redis->set($key,serialize($data),$expire);
    }
    public function delData($key1,$key2 = null)
    {
        return $this->redis->del($key1,$key2);
    }
    public function getData($key)
    {
        $data = $this->redis->get($key);
        return  empty($data) ? [] : unserialize($data);
    }
    public function get($key)
    {
        return $this->redis->get($key);
    }

    public function incr($key)
    {
        return $this->redis->incr($key);
    }

    public function lpush($key,$value)
    {
        return $this->redis->lpush($key,serialize($value));
    }

    public function llen($key)
    {
        return $this->redis->llen($key);
    }

    public function llist($key,$start,$stop)
    {
        return $this->redis->lRange($key,$start,$stop);
    }

    public function expire($key,$expire)
    {
        return $this->redis->expire($key,(int)$expire);
    }
}