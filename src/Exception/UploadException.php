<?php


namespace Jdmm\Oss\Exception;

/**
 * Class ImageException
 * @package Jdmm\Oss\Exception
 */
class UploadException extends \Exception
{
    //错误码
    const IM_NOT_FOUND     = 404;
    const IM_SIZE_FAIL     = 410;
    const IM_TYPE_FAIL     = 420;
    const IM_FAIL          = 500;

    const PART_NOT_FOUND   = 501;
    //错误信息
    const ERROR_MESSAGE = [
        self::IM_NOT_FOUND    => '上传文件不存在',
        self::IM_FAIL         => '上传文件失败',
        self::IM_SIZE_FAIL    => '上传文件大小不符合要求',
        self::IM_TYPE_FAIL    => '上传文件类型不符合要求',
        self::PART_NOT_FOUND  => '分片上传的信息不存在或者已过期',
    ];
}