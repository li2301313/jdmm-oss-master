<?php

namespace Jdmm\Oss\Common;

use Jdmm\Oss\Exception\UploadException;
use Swoft\Bean\Annotation\Mapping\Bean;

/**
 * Class Common
 * @package Jdmm\Oss\Cache
 * @Bean()
 */
class Common
{
    /**
     * @param $code
     * @throws UploadException
     */
    public function throwMessage($code)
    {
        throw new UploadException(UploadException::ERROR_MESSAGE[$code],$code);
    }

    public function readContents($filePath)
    {
        $str = '';
        if (file_exists($filePath)) {
            $fp = fopen($filePath, "r");
            $str = "";
            $buffer = 1024; //每次读取 1024 字节
            while (!feof($fp)) {//循环读取，直至读取完整个文件
                $str .= fread($fp, $buffer);
            }
            fclose($fp);
        }
        return $str;
    }

    /**
     * @param $filename
     * @return bool
     */
    public function resetFile($filename)
    {
        if (is_file($filename)){
            unlink($filename);
        }
        return touch($filename);
    }

    /**
     * @param $stream
     * @param $moveToFilename
     * @return bool
     */
    public function write($stream, $moveToFilename)
    {
        while (!feof($stream)) {//循环读取，直至读取完整个文件
            //$contents = fread($stream, 100000);
            $contents = stream_get_line($stream, 1000000);
            file_put_contents($moveToFilename,$contents,FILE_APPEND);
        }
        return fclose($stream);
    }

}