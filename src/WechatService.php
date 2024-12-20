<?php
namespace App\Services\External;
use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Crypto\AesGcm;
use Exception;
use WeChatPay\Util\PemUtil;
use WeChatPay\Formatter;
use App\Services\ApiService;
/*
 * 微信API服务类 用户获取微信小程序openId、小程序、H5支付以及相关订单查询操作
 * @author: liuchao
 */
class WechatService
{
    private $mchid;                      // 商户号
    private $appid;                      // 微信支付AppID
    private $notifyUrl;                  // 通知回调URL
    private $merchantPrivateKey;         // 商户私钥
    private $merchantCertSerialNumber;   // 商户证书序列号
    private $wechatPayClient;            // 微信支付客户端实例
    private $platCert;                   // 平台证书
    private $platCertResource;           // 平台证书资源
    private $platCertSerialNumber;       // 平台证书序列号
    private $aesKey;                     // AES密钥
    private $miniAppid;                  // 小程序AppID
    private $appSecret;                  // 小程序AppSecret
    /**
     * 构造函数：初始化支付服务
     */
    public function __construct()
    {
        // 配置参数（读取 .env 配置文件）
        $this->mchid = Env('WECHAT_PAY_MCHID');  // 商户号
        $this->appid = Env('WECHAT_PAY_APPID');  // AppID
        $this->notifyUrl = Env('WECHAT_PAY_NOTIFY_URL'); // 回调通知URL
        $this->merchantCertSerialNumber = Env('WECHAT_PAY_MERCHANT_CERT_SERIAL_NO'); // 商户证书序列号
        $this->platCert = Env('WECHAT_PAY_CERT_PATH'); // 平台证书
        $this->aesKey = Env('WECHAT_PAY_KEY'); // AES密钥
        $this->appSecret = Env('WECHAT_PAY_APP_SECRET'); // 小程序AppSecret
        $this->miniAppid = Env('WECHAT_PAY_MINIPROGRAM_APPID'); // 小程序AppID
        // 加载商户私钥
        $this->merchantPrivateKey = Rsa::from(
            file_get_contents(Env('WECHAT_PAY_KEY_PATH')),
            Rsa::KEY_TYPE_PRIVATE
        );

        // 加载平台证书
        $platformCertificateContent = file_get_contents($this->platCert);

        // 加载平台证书资源
        $this->platCertResource   = Rsa::from(
            $platformCertificateContent, 
            Rsa::KEY_TYPE_PUBLIC
        );

        // 加载商户证书序列号
        $this->platCertSerialNumber = PemUtil::parseCertificateSerialNo($platformCertificateContent);

        // 初始化微信支付客户端
        $this->wechatPayClient = Builder::factory([
            'mchid'      => $this->mchid,
            'serial'     => $this->merchantCertSerialNumber,
            'privateKey' => $this->merchantPrivateKey,
            'certs'      => [
                $this->platCertSerialNumber => $this->platCertResource,
            ],
        ]);
    }

    /**
     * H5支付: 生成支付链接
     *
     * @param string $description 商品描述
     * @param int $amount         支付金额（单位：分）
     * @param string $outTradeNo  商户订单号
     * @param string $clientIp    用户IP地址
     * @return string|false       返回H5支付链接
     */
    public function createH5Pay($description, $amount, $outTradeNo, $clientIp)
    {
        try {
            $response = $this->wechatPayClient->chain('v3/pay/transactions/h5')
                ->post(['json' => [
                    'appid'        => $this->appid,
                    'mchid'        => $this->mchid,
                    'description'  => $description,
                    'out_trade_no' => $outTradeNo,
                    'notify_url'   => $this->notifyUrl,
                    'amount'       => [
                        'total'    => $amount,
                        'currency' => 'CNY',
                    ],
                    'scene_info' => [
                        'payer_client_ip' => $clientIp,
                        'h5_info' => [
                            'type' => 'Wap', // 场景类型
                        ],
                    ],
                ]]);

            $result = json_decode($response->getBody()->getContents(), true);

            return $result['h5_url'] ?? false;
        } catch (Exception $e) {
            return  $e->getMessage();
        }
    }

    /**
     * 小程序支付: 生成支付参数
     *
     * @param string $description 商品描述
     * @param int $amount         支付金额（单位：分）
     * @param string $outTradeNo  商户订单号
     * @param string $openid      用户OpenID
     * @return array|false        返回支付参数数组
     */
    public function createMiniProgramPay($description, $amount, $outTradeNo, $openid)
    {
        try {
            // 调用微信支付接口
            $response = $this->wechatPayClient->chain('v3/pay/transactions/jsapi')
                ->post(['json' => [
                    'appid'        => $this->miniAppid,
                    'mchid'        => $this->mchid,
                    'description'  => $description,
                    'out_trade_no' => $outTradeNo,
                    'notify_url'   => $this->notifyUrl,
                    'amount'       => [
                        'total'    => $amount,
                        'currency' => 'CNY',
                    ],
                    'payer' => [
                        'openid' => $openid, // 用户OpenID
                    ],
                ]]);
               
            $result = json_decode($response->getBody()->getContents(), true);
        
            // 生成小程序前端支付所需的参数
            $timeStamp = (string)time();
            $nonceStr = bin2hex(random_bytes(16));
            $prepayId = $result['prepay_id'] ?? '';

            $paySignData = [
                'appId'     => $this->appid,
                'timeStamp' => $timeStamp,
                'nonceStr'  => $nonceStr,
                'package'   => "prepay_id={$prepayId}",
                'signType'  => 'RSA',
            ];

            //生成签名
            $paySign = Rsa::sign(
                implode("\n", [
                    $paySignData['appId'],
                    $paySignData['timeStamp'],
                    $paySignData['nonceStr'],
                    $paySignData['package'],
                    '',
                ]),
                $this->merchantPrivateKey
            );

            $paySignData['paySign'] = $paySign;

            return $paySignData;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 支付通知回调验签
     * @param string $inWechatpaySignature header['Wechatpay-Signature']验签的签名值
     * @param string $inWechatpayTimestamp header['Wechatpay-Timestamp']验签的时间戳
     * @param string $inWechatpayNonce     header['Wechatpay-Nonce']验签的随机串
     * @param string $inBody 请求体
     * @return boolean true:验签成功，false:验签失败
     * @throws Exception
     */
    public function verifySignature($header=[],$inBody='')
    {
        try {
            
            $inWechatpayTimestamp = $header['Wechatpay-Timestamp'] ?? '';
            $inWechatpayNonce = $header['Wechatpay-Nonce'] ?? '';
            $inWechatpaySignature = $header['Wechatpay-Signature'] ?? '';

            //平台证书
            $platformPublicKeyInstance = $this->platCertResource;

            // 检查通知时间偏移量，允许5分钟之内的偏移
            $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
            // 调用SDK校验签名
            $verifiedStatus = Rsa::verify(
                // 构造验签名串
                Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
                $inWechatpaySignature,
                $platformPublicKeyInstance
            );

            return $verifiedStatus;

        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * AES-256-GCM 解密
     * @param string $ciphertext 加密的密文
     * @param string $nonce 随机字符串
     * @param string $associatedData 附加数据
     * @return string 返回解密后的明文
     * @throws Exception
     */
    public function decryptToString(string $ciphertext, string $nonce, string $associatedData): string
    {
        // AES-256-GCM 解密
        return AesGcm::decrypt($ciphertext, $this->aesKey, $nonce, $associatedData);
        
    }

    /**
     * 获取小程序openId
     * @param string $code 小程序登录code
     * @return string
     */
    public function getMiniOpenId($code)
    {
        try {
            //构建请求参数
            $requestParams = [
                'appid'      => $this->miniAppid,
                'secret'     => $this->appSecret,
                'js_code'    => $code,
                'grant_type' => 'authorization_code',
            ];
            // 调用微信接口
            $url = 'https://api.weixin.qq.com/sns/jscode2session';
            $response = (new ApiService())->get($url, $requestParams);
            // 返回结果
            return $response;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

}
