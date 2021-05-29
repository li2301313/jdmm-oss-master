<?php

declare(strict_types=1);

namespace Jdmm\Oss;
use Co\Channel;
use Jdmm\Oss\Common\Common;
use Jdmm\Oss\Contract\UploadInterface;
use Jdmm\Oss\Exception\UploadException;
use Jdmm\Oss\Local\Local;
use Jdmm\Oss\Oss\Oss;
use Jdmm\Oss\Ssh\Ssh;
use Swoft\Bean\Annotation\Mapping\Bean;
use Swoft\Bean\Annotation\Mapping\Inject;
use Swoft\Config\Annotation\Mapping\Config;
use think\Image;
/**
 * Class Upload
 * @package Jdmm\Oss
 * @Bean()
 */
class Upload
{
    /**
     * @Inject()
     * @var Local
     */
    private $local;
    /**
     * @Inject()
     * @var Oss
     */
    private $oss;
    /**
     * @Inject()
     * @var Ssh
     */
    private $ssh;

    /**
     * @Config("upload.localPath")
     * @var string
     */
    private $localPath;
    /**
     * @Config("upload.maxSize")
     * @var int
     */
    private $maxSize;
    /**
     * @Config("upload.imgType")
     * @var string
     */
    private $imgType;
    /**
     * @Config("upload.uploadType")
     * @var string
     */
    private $uploadType;
    /**
     * @Config("upload.remotePath")
     * @var string
     */
    private $remotePath;
    /**
     * @Config("upload.thumb")
     * @var string
     */
    private $thumb;
    /**
     * @Config("upload.width")
     * @var string
     */
    private $width;
    /**
     * @Config("upload.height")
     * @var string
     */
    private $height;
    /**
     * @Config("upload.thumbType")
     * @var string
     */
    private $thumbType;

    private $file;

    private $absolutePath;
    private $filename;

    /**
     * @Inject()
     * @var Common
     */
    private $common;

    public function __construct()
    {
        $this->maxSize    = $this->maxSize ?? 1048576;
        $this->imgType    = $this->imgType    ?? 'jpg,jpeg,png';
        $this->localPath  = $this->localPath    ?? '/usr/app/public';
        $this->uploadType = $this->uploadType ?? 'local';
        $this->remotePath = $this->remotePath  ??  'upload';
        $this->thumb      = $this->thumb  ??  false ;
        $this->width      = $this->width  ??   120;
        $this->height     = $this->height  ??  120;
        $this->thumbType  = $this->thumbType  ??  1;
    }

    /**
     * @param object $file 文件对象
     * @param string $extraPath 添加保存文件路径
     * @return mixed
     * @throws UploadException
     */
    public function upload($file,   $extraPath = '')
    {
        //校验文件师傅存在
        if(empty($file)) $this->common->throwMessage(UploadException::IM_NOT_FOUND);

        //校验文件是否上传成功
        if (!$file->isOk()) $this->common->throwMessage(UploadException::IM_FAIL);

        //成功后暂存文件对象
        $this->file = $file;

        //文件的尺寸的校验
        if ($file->getSize() > $this->maxSize ) $this->common->throwMessage(UploadException::IM_SIZE_FAIL);

        //保存
        $this->filename = $file->getClientFilename();

        //读取上传文件名称
        $saveName = $file->getClientFilename();

        //分离出文件后缀
        $suffix = $this->getSuffix($saveName);

        //图片类型的校验
        if (!in_array($suffix,explode(',',$this->imgType))) $this->common->throwMessage(UploadException::IM_TYPE_FAIL);

        //文件重命名
        $reName = md5($saveName . uniqid() . microtime() . $suffix) . '.' . $suffix;

        //暂存本地服务器的路径
        list($localUrl,$saveName)= $this->getUrl($extraPath,$reName);

        try{

            //移动到此路径
            $file->moveTo($localUrl);

            $dir = $this->remotePath;

            if (!empty($extraPath)) $dir = $this->remotePath . '/' . $extraPath;


            $url = $this->checkTypeAndUpload($localUrl,$saveName,$dir);

            if ($this->thumb){
                //是否选择进行压缩
                list($localThumbUrl) = $this->getUrl($extraPath,$saveName,true);

                $localThumbUrl = $this->__thumb($localUrl,$localThumbUrl);

                $url = [$url,$localThumbUrl];
            }

            $this->absolutePath = trim($dir . '/' . $saveName,'/');

            return $url ;
        }catch (\Exception $exception){
            $this->common->throwMessage(UploadException::IM_FAIL);
        }
    }

    private function __thumb($localUrl,$localThumbUrl)
    {
        $image = Image::open($localUrl);
        $image->thumb($this->width,$this->height,$this->thumbType)->save($localThumbUrl,null,90);
        //上传类型的校验并执行上传
        return $localThumbUrl;
    }
    /**
     * @param $localUrl
     * @param $saveName
     * @param $dir
     * @return string
     */
    protected function checkTypeAndUpload($localUrl,$saveName,$dir)
    {
        $isDel = true;

        if ($this->uploadType == UploadConst::LOCAL){
            $isDel = false;
        }else{
            if ($this->thumb) $isDel = false;
        }

        return $this->getObj()->put($localUrl,$saveName,$dir,$isDel);
    }

    /**
     * @return UploadInterface
     */
    private function getObj():UploadInterface
    {
        $list = [
            UploadConst::LOCAL => $this->local,
            UploadConst::OSS   => $this->oss,
            UploadConst::SSH   => $this->ssh,
        ];

        return $list[$this->uploadType];
    }

    private function checkName($reName,$suffix)
    {
        if(!empty($reName)) {
            $name = $reName . '.' . $suffix;
        }else{
            $name = md5(uniqid().time()) . '.' . $suffix;
        }
        return $name;
    }

    public function getUploadFileInfo()
    {
        return [
           'path'     =>  $this->absolutePath,
           'filename' =>  $this->filename,
        ];
    }

    /**
     * @return mixed
     */
    public function getSize()
    {
        return $this->file->getSize();
    }

    public function getType()
    {
        return $this->getSuffix($this->file->getClientFilename());

    }

    protected function getSuffix($name)
    {
        return substr($name,strripos($name,'.') + 1);
    }
    protected function getUrl($extraPath,$saveName,$isThumb = false)
    {
        if ($isThumb){
            $saveName = 'thumb_' . $saveName;
        }
        if (empty($extraPath)){
            $url = $this->localPath . '/' . $this->remotePath . '/' . $saveName;
        }else{
            $url = $this->localPath . '/' .$this->remotePath. '/' . $extraPath . '/' . $saveName;
        }
        return [$url,$saveName];
    }

    /**
     * 获取分片上传的id
     * @param string $fileName
     * @param int $partAll
     * @param string $fileMd5
     * @param int $fileSize
     * @param int $expire
     * @return string
     */
    public function patchUploadId( $fileName,  $partAll,  $fileMd5,  $fileSize, $expire = 3600)
    {
        $info = pathinfo($fileName);

        $md5filename = md5($fileName . uniqid() . $info['filename'] .microtime());

        $path = trim($info['dirname'] . '/' . $md5filename . '.' . $info['extension'],'/');

        $relativePath = $this->remotePath . '/' . $path;

        return $this->getObj()->createUploadId($relativePath,$partAll,$fileMd5,$fileSize,$fileName,$expire);
    }

    /**
     * 分片上传
     * @param string $uploadId
     * @param string $partFile
     * @param int $partNum
     * @param int $expire
     * @return mixed
     */
    public function patchPartUpload( $uploadId,  $partFile,  $partNum, $expire = 3600)
    {
        //处理文件内容
        if ($this->uploadType === UploadConst::LOCAL){

            $partContent =  $this->common->readContents($partFile);

        }else if($this->uploadType == UploadConst::OSS){

            $partContent =  fopen($partFile,'r');
        }
         
        $data = $this->getObj()->patchPartUpload($uploadId,$partContent,$partNum,$expire);
        
        if($this->uploadType == UploadConst::OSS && is_resource($partContent) ) fclose($partContent);
        
        return $data;
        
    }

    /**
     * 删除分片的缓存
     * @param $uploadId
     * @return mixed
     */
    public function delPartUploadCache( $uploadId)
    {
        return $this->getObj()->delPartUploadCache($uploadId);
    }
    /**
     * 文件的复制
     * @param $copySourcePath
     * @param $newSourcePath
     * @return mixed
     */
    public function ossCopyFile( $copySourcePath,  $newSourcePath)
    {
        return $this->oss->copyFile($copySourcePath,$newSourcePath);
    }

    /**
     * 获取分片上传的文件信息
     * @param string $uploadId
     * @return mixed
     */
    public function getPartFileInfo( $uploadId)
    {
        return $this->getObj()->getPartFileInfo($uploadId);
    }
    /**
     * 获取文件访问的相对链接
     * @param string $path 文件的上传相对路径
     * @param string $name 文件的原始名称
     * @return string
     */
    public function getDownloadRelativePath( $path,  $name)
    {
       return $this->getObj()->getDownloadRelativePath($path, $name);
    }

    /**
     * @param array $keys
     * @param string $zipName
     * @return string
     */
    public function batchDownload(array $keys, string $zipName) : string
    {
        $chan = new Channel();

        $len = count($keys);

        $localPath = $this->localPath . '/' . 'download/zip';

        if (!is_dir($localPath)){
            mkdir($localPath,0777,true);
        }

        foreach ($keys as $v){

            sgo(function () use ($v, $chan, $localPath){

                //生成文件
                $url = $this->getFile($v['key'], $v['downloadName'], $localPath);

                $chan->push($url);
            });

        }

        $urls = [];

        for ($i = 0 ;$i < $len ; $i++){
            $urls[] = $chan->pop();
        }

        $chan->close();

        $zipFilename = $localPath . '/' . $zipName;

        if (!empty($urls)){
            //多个压缩
            $this->zipFile($urls, $zipFilename);
        }

        $this->filename = $zipName;
        $ossKey = 'download/zip/' . microtime();
        $this->absolutePath = $ossKey . '/' . $zipName;

        $a = $this->getObj()->put($zipFilename, $zipName, $ossKey);

        return $a;
    }

    /**
     * @param $key
     * @param $downloadName
     * @param $localPath
     * @return string
     */
    private function getFile($key, $downloadName, $localPath)
    {
        try {

            $saveName  = $localPath . '/' . $downloadName;
            $this->getObj()->getFileResource($key, $saveName);
            return $saveName;

        }catch (\Exception $e){
            echo '[' . date('Y-m-d H:i:s') . '][error]: 打包失敗' . PHP_EOL;
            echo '[' . date('Y-m-d H:i:s') . '][error]: ' . $e->getFile() . ' :Line ' . $e->getLine() . ' :message ' . $e->getMessage() . PHP_EOL;
        }catch (\Error $e){
            echo '[' . date('Y-m-d H:i:s') . '][error]: 打包失敗' . PHP_EOL;
            echo '[' . date('Y-m-d H:i:s') . '][error]: ' . $e->getFile() . ' :Line ' . $e->getLine() . ' :message ' . $e->getMessage() . PHP_EOL;
        }
    }

    /**
     * @param array $urls
     * @param string $zipFilename
     */
    private function zipFile(array $urls, string $zipFilename)
    {

        $zip = new \ZipArchive();

        $zip->open($zipFilename, \ZipArchive::CREATE);   //打开压缩包

        foreach ($urls as $file) {
            $zip->addFile($file, basename($file));   //向压缩包中添加文件
        }

        $zip->close();  //关闭压缩包

        sgo(function () use ($urls){
            foreach ($urls as $file) {
                unlink($file);
            }
        });
    }
}