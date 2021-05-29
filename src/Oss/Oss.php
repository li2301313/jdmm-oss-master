<?php

declare(strict_types=1);

namespace Jdmm\Oss\Oss;
use Aws\Sdk;
use Jdmm\Oss\Cache\UploadCache;
use Jdmm\Oss\Common\Common;
use Jdmm\Oss\Contract\UploadInterface;
use Jdmm\Oss\Exception\UploadException;
use Swoft\Bean\Annotation\Mapping\Bean;
use Swoft\Co;
use Swoft\Config\Annotation\Mapping\Config;
use Swoft\Bean\Annotation\Mapping\Inject;

/**
 * Class Oss
 * @package Jdmm\Oss\Oss
 * @Bean()
 */
class Oss implements UploadInterface
{
    /**
     * @Config("oss.region")
     * @var string
     */
    private $region;
    /**
     * @Config("oss.endpoint")
     * @var string
     */
    private $endpoint;
    /**
     * @Config("oss.key")
     * @var string
     */
    private $key;
    /**
     * @Config("oss.secret")
     * @var string
     */
    private $secret;
    /**
     * @Config("oss.bucket")
     * @var string
     */
    private $bucket;

    /**
     * @Config("oss.largeCdn")
     * @var string
     */
    private $largeCdn;

    /**
     * @Config("oss.smallCdn")
     * @var string
     */
    private $smallCdn;

    /**
     * @Inject()
     * @var UploadCache
     */
    private $cache;

    /**
     * @Inject()
     * @var Common
     */
    private $common;
    public function connectOss()
    {
        $sharedConfig = [
            'version'           => 'latest',
            'region'            => $this->region,
            'endpoint'          => $this->endpoint,
            'signature_version' => 'v4',
            'credentials'       => [
                'key'    => $this->key ,
                'secret' => $this->secret
            ],
        ];
        $sdk = new Sdk($sharedConfig);
        return $sdk->createS3();
    }

    /**
     * 图片上传 默认销毁原来的图片
     * @param string $url
     * @param string $filename
     * @param string $dir
     * @param bool $isLink
     * @return mixed|null
     */
    public function put( $url, $filename,$dir, $isLink = true)
    {
        $client = $this->connectOss();

        $con = fopen($url,'r');

        $data = [
            'Bucket' => $this->bucket,
            'Key'    =>  $dir . '/' . $filename,
            'Body'   =>  $con,
            'ACL'    => 'public-read'
        ];

        //默认销毁原来的图片
        if ($isLink) unlink($url);

        
        $url  = $client->putObject($data)->get('ObjectURL');
        
        fclose($con);
    
        return $url;

    }

    /**
     * @param $uploadId
     * @param $partContent
     * @param $partNum
     * @param $expire
     * @return mixed|string
     * @throws UploadException
     */
    public function patchPartUpload( $uploadId,  $partContent,  $partNum,  $expire = 3600)
    {

        $data = $this->cache->getData($uploadId);

        if (empty($data)) throw new UploadException(UploadException::ERROR_MESSAGE[UploadException::PART_NOT_FOUND],UploadException::PART_NOT_FOUND);

        $relativePath = $data['relativePath'];
        $partAll      = $data['partAll'];
        $countKey     = $uploadId.$partAll;

        //执行分片上传 获取返回的ETag
        $etag = $this->uploadPart($uploadId,$partContent,$relativePath,$partNum);

        //追加分片的内容
        $parts = [
            'ETag'       => $etag,
            'PartNumber' => $partNum,
        ];

        $count = $this->cache->lpush($countKey,$parts);

        //队列重试
        if ($count === false) $count = $this->lpushRetry($countKey,$parts);

        $data['parted'] = $count;

        //获取列表的长度
        $realCount = $this->cache->llen($countKey);

        if ($realCount == $partAll){

            //取出列表的内容
            $parts = $this->cache->llist($countKey,0,-1);

            foreach ($parts as $k => $v){
                $parts[$k] = unserialize($v);
            }

            $url = $this->completeMultipartUpload($relativePath,$uploadId,$parts);


            $data['url'] = $url;

            $data['relativePath'] = $relativePath;

        }else{
            //保存緩存
            $this->cache->setData($uploadId,$data,$expire);
        }

        return $data;

    }
    /**
     * 创建上传的UploadId
     * @param $relativePath
     * @return mixed
     */
    public function createMultipartUpload( $relativePath)
    {
        $client = $this->connectOss();
        $data = [
            'Bucket' => $this->bucket,
            'Key'    =>  $relativePath,
        ];

        $uploadId = $client->createMultipartUpload($data)->get('UploadId');

        unset($client);
        unset($data);
        return $uploadId;
    }

    /**
     * @param $uploadId
     * @param $data
     * @param $relativePath
     * @param $part
     * @return mixed
     */
    public function uploadPart( $uploadId, $data, $relativePath, $part)
    {
        $client = $this->connectOss();
        $data = [
            'Bucket'    => $this->bucket,
            'Key'       =>  $relativePath,
            'Body'      =>  $data,
            'PartNumber'=>  $part,        // Required 每次的序号唯一且递增
            'UploadId'  =>  $uploadId,                      // Required 创建context时返回的值
        ];

        return $client->UploadPart($data)->get("ETag");

    }

    /**
     * @param $relativePath
     * @param $uploadId
     * @param $parts
     * @return mixed
     */
    public function completeMultipartUpload( $relativePath,  $uploadId,  $parts)
    {
        $client = $this->connectOss();
        /* $parts = [
            [
                 'ETag' => '<string>',
                 'PartNumber' => 1,
             ],
         ];*/

        $count = count($parts);

        $partArr = array_column($parts,null,'PartNumber');

        $arr = [];
        for ($i = 1; $i <= $count; $i++){
            $arr[] = $partArr[$i];

        }

        $data = [
            'Bucket' => $this->bucket,
            'Key' => $relativePath,
            'MultipartUpload' => [
                'Parts' => $arr
            ],
            'UploadId' => $uploadId
        ];


        return $client->completeMultipartUpload($data)->get('ObjectURL');


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

    /**
     * @param string $relativePath
     * @param int $partAll
     * @param string $fileMd5
     * @param int $fileSize
     * @param string $fileName
     * @param int $expire
     * @return mixed|string
     */
    public function createUploadId( $relativePath,  $partAll,  $fileMd5,  $fileSize,  $fileName, $expire = 3600)
    {

        $uploadId = $this->createMultipartUpload($relativePath);

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


    /**
     * @param $countKey
     * @param $parts
     * @param int $counts
     * @return bool
     */
    private function lpushRetry($countKey,$parts,$counts = 3)
    {
        $num = false;
        for ($i = 0; $i < $counts; $i++){
           $num =  $this->cache->lpush($countKey,$parts);
           if ($num !== false) break;
        }

        return $num;
    }


    /**
     * 文件的复制
     * @param $copySourcePath
     * @param $newSourcePath
     * @return mixed
     */
    public function copyFile( $copySourcePath,  $newSourcePath)
    {
        $client = $this->connectOss();

        $data = [
            'Bucket'     =>  $this->bucket,
            'Key'        =>  $newSourcePath,
            'CopySource' =>  $this->bucket . '/' . $copySourcePath
        ];

        return $client->copyObject($data)->get('ObjectURL');
    }

    /**
     * @param $uploadId
     * @return array|mixed
     */
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
        return $path . '?response-content-disposition=attachment;%20filename=' . urlencode(utf8_encode($name));
    }

    /**
     * @param $pathOrKey
     * @param $saveName
     * @return bool
     */
    public function getFileResource($pathOrKey, $saveName)
    {
        $client = $this->connectOss();

        $data = [
            'Bucket' =>  $this->bucket,
            'Key'    =>  $pathOrKey
        ];

        $body = $client->getObject($data)->get('Body');

        $stream =  array_values((array)($body))[0];

        $this->common->resetFile($saveName);

        return $this->common->write($stream, $saveName);
    }
}
