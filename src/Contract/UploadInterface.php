<?php declare(strict_types=1);


namespace Jdmm\Oss\Contract;

/**
 * Class UploadInterface
 *
 * @since 2.0
 */
interface UploadInterface
{

    /**
     * 单个上传oss
     * @param string $url
     * @param string $filename
     * @param $dir
     * @param bool $isLink
     * @return mixed
     */
    public function put( $url, $filename,$dir, $isLink = true);

    /**
     * 获取分片上传id
     * @param $relativePath
     * @param $partAll
     * @param $fileMd5
     * @param $fileSize
     * @param $fileName
     * @param int $expire
     * @return mixed
     */
    public function createUploadId( $relativePath,  $partAll,  $fileMd5,  $fileSize,  $fileName, $expire = 3600);

    /**
     * 分片上传
     * @param $uploadId
     * @param $partContent
     * @param $partNum
     * @param $expire
     * @return mixed
     */
    public function patchPartUpload( $uploadId,  $partContent, $partNum,  $expire = 3600);

    /**
     * 删除上传文件的缓存
     * @param $uploadId
     * @return mixed
     */
    public function delPartUploadCache( $uploadId);

    /**
     * 后期上传文件的信息
     * @param $uploadId
     * @return mixed
     */
    public function getPartFileInfo( $uploadId);


    /**
     * 获取访问的相对链接
     * @param string $path 文件的上传相对路径
     * @param string $name 文件的原始名称
     * @return string
     */
    public function getDownloadRelativePath( $path,  $name);

    /**
     * 写入文件
     * @param $pathOrKey
     * @param $saveName
     * @return bool
     */
    public function getFileResource($pathOrKey, $saveName);

}