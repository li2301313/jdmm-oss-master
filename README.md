# 文件上传包使用说明

## 安装composer包

```shell
composer require jdmm/oss
```

##创建在swoft的config配置文件

#### 1、upload.yaml

```yaml
imgType: jpg,jpeg,png  #上传文件的允许类型
localPath: /usr/app/public/upload #本地保存的目录
maxSize: 1048888  #最大不能超过的大小字节
uploadType: oss  #上传类型 local(本地)  oss（oss）  ssh（另一台服务器） 等等
remotePath: vhub/upload  #远端目录
thumb: true
width: 120
height: 120
thumbType: 1
#thumbType
#1 //常量，标识缩略图等比例缩放类型
#2 //常量，标识缩略图缩放后填充类型
#3 //常量，标识缩略图居中裁剪类型
#4 //常量，标识缩略图左上角裁剪类型
#5 //常量，标识缩略图右下角裁剪类型
#6 //常量，标识缩略图固定尺寸缩放类型
```

### 2、如果需要上传oss 需要添加oss.yaml 配置文件

```yaml
endpoint: http://s3.cn-north-1.jdcloud-oss.com
key: 57F3EDB5FA6ACA47D10FD7CA1AF61D77
secret: 505DE82B1A224936B0B28CD37252A4A0
region: devtest
bucket: devtest
largeCdn:   # cdn  大文件的域名
smallCdn:   # cdn 小文件的域名

```
### 3、如果需要上传ssh 需要安装ssh2扩展 添加ssh.yaml 配置文件

```yaml
ip: 106.52.23.53 #远端IP地址 
name: root #用户名
secret: 123456 #密码
serverName: www.test.com #访问域名
```
## swoft使用示例

```php
<?php

namespace App\Http\Controller;

use Swoft\Bean\Annotation\Mapping\Inject;
use Swoft\Http\Message\Request;
use Swoft\Http\Server\Annotation\Mapping\Controller;
use Swoft\Http\Server\Annotation\Mapping\RequestMapping;
use Jdmm\Oss\Upload;

/**
 * Class ImageController
 * @package App\Http\Controller
 * @Controller()
 */
class ImageController
{
    /**
     * @Inject()
     * @var Upload
     */
    private $upload;

    /**
     * @RequestMapping("/upload")
     * @param Request $request
     * @return string
     */
    public function upload(Request $request)
    {
        //文件的对象
        $file = $request->file('file');
        //额外的保存路径默认为空  会在基本的保存路径拼接  例如： 'test'
        $extraPath = ''; 
        //文件重命名默认为空  可不传  传代表需要重命名例子： 'swoft'
        $reName = '';
        //调用upload方法之前查看文件的大小和类型
       	$size = $file->getSize();
        $type = substr($file->getClientFilename(),strripos($file->getClientFilename(),'.') + 1)
        //上传成功文件的存放目录或者oss的地址
         try{
            $url = $this->upload->upload($file,$extraPath,$reName);
        }catch (\Exception $exception){
            //这个地方自行处理
            return [
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
            ]; 
        }
        //上传成功之后来查看文件的大小和类型
        $size = $this->upload->getSize();
        $type = $this->upload->getType();
        return [$url];
    }
}
```