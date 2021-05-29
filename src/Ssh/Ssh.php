<?php

namespace Jdmm\Oss\Ssh;

use Swoft\Bean\Annotation\Mapping\Bean;
use Swoft\Config\Annotation\Mapping\Config;
/**
 * Created by PhpStorm.
 * User: wanghuashun
 * Date: 2020/2/17
 * Time: 11:29
 */
/**
 * Class Ssh
 * @package Jdmm\Oss\Ssh
 * @Bean()
 */
class Ssh
{
    /**
     * @Config("ssh.ip")
     * @var string
     */
    private $ip;
    /**
     * @Config("ssh.name")
     * @var string
     */
    private $name;
    /**
     * @Config("ssh.serverName")
     * @var string
     */
    private $serverName;
    /**
     * @Config("ssh.secret")
     * @var string
     */
    private $secret;
    //scp上传文件至远程服务
    // $host为B服务器域名(IP)
    // $user B服务器用户
    // $password B服务器密码
    // $local_file为本地文件
    // $remote_file为远程文件
    public function put($url,$saveName,$dir,$isLink = true)
    {
        $ssh2 = ssh2_connect($this->ip, 22);  //先登陆SSH并连接

        ssh2_auth_password($ssh2,$this->name,$this->secret);//身份认证  也可以用

        //拼接远端地址
        $remote = $this->serverName . '/' . $dir .'/'. $saveName;
        //上传
        $stream = ssh2_scp_send($ssh2, $url, $remote, 0777);

        //默认销毁原来的图片
        if ($isLink) unlink($url);

        //默认权限为0644，返回值为bool值，true或false.
        return $stream;
    }
}