<?php

namespace Jdmm\Oss\Local;


use Jdmm\Oss\Common\Common;
use Jdmm\Oss\Contract\UploadInterface;
use Jdmm\Oss\Exception\UploadException;
use Jdmm\Oss\Upload;
use Swoft\Bean\Annotation\Mapping\Bean;
use Swoft\Co;
use Swoft\Config\Annotation\Mapping\Config;
use Swoft\Bean\Annotation\Mapping\Inject;
use Jdmm\Oss\Cache\UploadCache;

/**
 * Class Local
 * @package Jdmm\Oss\Local
 * @Bean()
 */
class Local implements UploadInterface
{
    /**
     * @Inject()
     * @var UploadCache
     */
    private $cache;

    /**
     * @Config("upload.localPath")
     * @var string
     */
    private $localPath;


    /**
     * @Inject()
     * @var Common
     */
    private $common;

    /**
     * 图片上传本地
     * @param string $url
     * @param string $filename
     * @param bool $isLink
     * @return mixed|null
     */
    public function put( $url, $filename,$dir, $isLink = false)
    {
        return $url;
    }


    public function createUploadId( $relativePath,  $partAll,  $fileMd5,  $fileSize,  $fileName,  $expire = 3600)
    {
        $uploadId = $this->getUploadId($relativePath);

        $data = [
            'uploadId'          => $uploadId,
            'originalFileMame'  => $fileName,
            'relativePath'      => $relativePath,
            'partAll'           => $partAll,
            'parted'            => 0,
            'size'              => $fileSize,
            'md5'               => $fileMd5,
        ];

        if (!$this->cache->setData($uploadId,$data,$expire)) return '';

        return $uploadId;
    }

    private function getUploadId( $relativePath)
    {
        return strtoupper(substr(md5($relativePath.uniqid()),0,16));
    }

    /**
     * @param $uploadId
     * @param $partContent
     * @param $partNum
     * @param $expire
     * @return array|mixed
     * @throws UploadException
     */
    public function patchPartUpload( $uploadId,  $partContent,  $partNum,  $expire = 3600)
    {
        $data = $this->cache->getData($uploadId);

        if (empty($data)) throw new UploadException(UploadException::ERROR_MESSAGE[UploadException::PART_NOT_FOUND],UploadException::PART_NOT_FOUND);

        $relativePath = $data['relativePath'];

        $partAll      = $data['partAll'];

        $countKey = $uploadId.$partAll;

        //追加分片的内容
        $parts = [
            'ETag'    => $partContent,
            'PartNumber' => $partNum,
        ];

        $count = $this->cache->lpush($countKey,$parts);

        //如果过期不是null 设置过期时间
        if (!is_null($expire)){
            $this->cache->expire($countKey,$expire);
            $this->cache->expire($uploadId,$expire);
        }

        $data['parted'] = $count;

        //获取列表的长度
        $realCount = $this->cache->llen($countKey);

        if ($realCount == $partAll){

            ini_set('memory_limit', '1024');

            //取出列表的内容
            $parts = $this->cache->llist($countKey,0,-1);

            foreach ($parts as $k => $v){
                $parts[$k] = unserialize($v);
            }
            //文件内容的整合

            $url = $this->completePartUpload($relativePath,$parts);

            $data['url'] = $url;

            $data['relativePath'] = $relativePath;
        }else{
            //保存緩存
            $this->cache->setData($uploadId,$data,$expire);

        }

        return $data;
    }


    /**
     * @param string $relativePath
     * @param array $parts
     * @return string
     */
    private function completePartUpload( $relativePath,  $parts)
    {
        $url  = $this->localPath. '/' . $relativePath;

        $dir = pathinfo($url)['dirname'];

        if(!is_dir($dir)){
            mkdir($dir,0777,true);
        }

        $content = array_column($parts,'ETag','PartNumber');

        ksort($content);

        foreach ($content as $value){
            Co::writeFile($url,$value,FILE_APPEND);
        }

        return $url;
    }

    /**
     * @param $uploadId
     * @return mixed
     * @throws UploadException
     */
    public function delPartUploadCache( $uploadId)
    {
        $data = $this->cache->getData($uploadId);

        if (empty($data)) throw new UploadException(UploadException::ERROR_MESSAGE[UploadException::PART_NOT_FOUND],UploadException::PART_NOT_FOUND);

        $partAll      = $data['partAll'];
        $countKey     = $uploadId.$partAll;
        //幹掉緩存
        return $this->cache->delData($uploadId,$countKey);
    }

    public function getPartFileInfo( $uploadId)
    {
        return $this->cache->getData($uploadId);
    }


    /**
     * @param string $path 文件的上传相对路径
     * @param string $name 文件的原始名称
     * @return string
     */
    public function getDownloadRelativePath( $path,  $name)
    {
        //本地上传不需要组装
        return $path;
    }

    /**
     * @param $pathOrKey
     * @param $saveName
     * @return bool
     */
    public function getFileResource($pathOrKey, $saveName)
    {
        $stream =  fopen($this->localPath . '/' . $pathOrKey,'r');

        $this->common->resetFile($saveName);

        return $this->common->write($stream, $saveName);
    }
}