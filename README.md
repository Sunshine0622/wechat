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

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\External\WechatService;

/*
 * 微信API控制器
 */
class WeChatPayController extends Controller
{
    // 微信API服务层
    protected $WechatService;

    /**
     * 构造函数
     */
    public function __construct(){
        // 继承父类
        parent::__construct();
        // 实例化服务层
        $this->WechatService = new WechatService();

    }

   /**
     * 小程序支付
     * @param $params
     *   description  //支付内容描述
     *   amount       //支付金额单位分
     *   outTradeNo   //商户订单号
     *   openid       //用户openid
     * @return array
     * @author: liuchao
     */
    public function wxPay(Request $request)
    {
        try {
            $description = '小程序支付测试';
            $amount = 100; // 单位: 分
            $outTradeNo = 'MINIPAY' . time();
            $openid = '用户的OpenID'; // 需从前端传递
            echo 333;
            $payParams = $this->WechatService->createMiniProgramPay($description, $amount, $outTradeNo, $openid);
            dd($payParams);
            if ($payParams) {
                return response()->json($payParams);
            }
    
        } catch (\Throwable $th) {
            //throw $th;
            return $th->getMessage();
        }
        
    }

    /**
     * 微信H5支付
     * @param $params
     *   description  //支付内容描述
     *   amount       //支付金额单位分
     *   outTradeNo   //商户订单号
     *   clientIp     //客户端IP
     * @return array
     * @author: liuchao
     */
    public function wxPayh5(Request $request){
        $description = '测试商品H5支付';
        $amount = 1; // 单位: 分
        $outTradeNo = 'H5PAY' . time();
        $clientIp = $_SERVER['REMOTE_ADDR'];

        $h5Url = $this->WechatService->createH5Pay($description, $amount, $outTradeNo, $clientIp);
        dd($h5Url);
        if ($h5Url) {
            // 返回支付链接，前端跳转
            return response()->json(['h5_url' => $h5Url]);
        }

    }

    /**
     * 微信支付回调通知
     * @param $params
     * @return array
     * @author: liuchao
     */
    public function wxPayNotify(Request $request){
        // 获取请求头
        $headers = $request->headers->all();
        // 获取请求体
        $body = $request->getContent();
        // 验证签名合法
        $signRes = $this->WechatService->verifySignature($headers,$body);
        // 验证失败，返回错误信息
        if(!$signRes)return response()->json(['code' => 500, 'message' => '签名验证失败'], 500);
        // 解密数据、
        $bodyArrData = (array)json_decode($body,true);
        $decryptData = $this->WechatService->decryptToString($bodyArrData['ciphertext'], $bodyArrData['associated_data'], $bodyArrData['nonce']);
        dd($decryptData);
        
    }

    /**
     * 微信支付回调通知
     * @param $params
     * @return array
     * @author: liuchao
     */
    public function getOpenId(Request $request){
        try {
            return $this->WechatService->getMiniOpenId($request->code);
        } catch (\Throwable $th) {
            //throw $th;
            return $th->getMessage();
        }
    }
}

```
