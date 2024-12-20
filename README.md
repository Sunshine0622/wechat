## 微信相关API接口
[![Latest Stable Version](https://img.shields.io/packagist/v/shitoudev/phone-location.svg)](https://packagist.org/packages/shitoudev/phone-location)
[![Build Status](https://travis-ci.org/shitoudev/phone-location.svg?style=flat-square&branch=master)](https://travis-ci.org/shitoudev/phone-location)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg)](https://php.net/)


微信API服务类 用户获取微信小程序openId、小程序、H5支付以及相关订单查询操作 

### 安装

请先安装微信SDK

```shell
composer require wechatpay/wechatpay
```
### 使用
```php
<?php
use App\Services\External\WechatService;

/*MD5 签名规则
签名生成的通用步骤如下:
第一步，设所有发送或者接收到的数据为集合 M(所有参数，包括公共参数》，将集合 M 内非空参数值的参数按照参数名 ASCI 码从小到大排序 (字典序) ，使用 URL 键值对的格式(即 key1=value1&key2=value2...)拼接成字符串 stringA。
特别注意以下重要规则:o
参数名 ASCII 码从小到大排序 (字典序) ;
如果参数的值为空不参与签名;
参数名区分大小写;验证调用返回或主动通知签名时，传送的 sign 参数不参与签名，将生成的签名与该 sign 值作校验。
接口可能增加字段，验证签名时必须支持增加的扩展字段若 value 内碰到特殊字符，按照标准 UTF-8 字符集的 URL-Encoding 方式进行转义第二步，在stringA 最后拼接上 key (接入流程中供) 得到 stringSignTemp 字符串，并对 stringSignTemp 进行MD5 运算，再将得到的字符串所有字符转换为大写，得到 sign 值 signValue。特别注意以下重要规则:
。拼接 key 的具体方式为: stringA&key=keyValue*/
	
$ApiSignature = new ApiSignature();
$params = [
    'province' => '上海',
    'city' => '上海',
    'postcode' => '200000',
    'tel_prefix' => '021',
    'sp' => '联通',
];
// 签名key
$signKey = 'your_sign_key';
// 对输入参数进行加密
$sign = $ApiSignature->generateMd5Sign($params, $signKey);
// Output;
"8B3CCE65E24FE6338F10BA0FE7ACBA3F"
```
