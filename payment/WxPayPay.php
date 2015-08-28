<?php
/**
 * Created by PhpStorm.
 * User: hoter.zhang
 * Date: 2015/8/28
 * Time: 11:12
 */

namespace hoter\wechat\payment;


class WxPayPay {

    //接口API URL前缀
    const API_URL_PREFIX = 'https://api.mch.weixin.qq.com';
    //下单地址URL
    const UNIFIEDORDER_URL = "/pay/unifiedorder";
    //查询订单URL
    const ORDERQUERY_URL = "/pay/orderquery";
    //关闭订单URL
    const CLOSEORDER_URL = "/pay/closeorder";

    //公众账号ID
    private $appid;
    //秘钥
    private $appsecret;
    //商户号
    private $mch_id;
    //随机字符串
    private $nonce_str;
    //支付密钥
    private	$key;
    //客户端ip
    private $spbill_create_ip;
    //签名
    private $sign;
    //所有参数
    private $params = array();
    //openid
    private $openid;

    //支付参数
    private $body;
    private $out_trade_no;
    private $total_fee;
    private $notify_url;
    private $trade_type;

    //授权返回链接
    private $redirect_url;



    public function __construct($options) {
        $this->appid = isset($options['appid'])?$options['appid']:'';
        $this->appsecret = isset($options['appsecret']) ? $options['appsecret']:'';
        $this->mch_id = isset($options['mch_id'])?$options['mch_id']:'';
        $this->notify_url = isset($options['notify_url'])?$options['notify_url']:'';
        $this->key = isset($options['key'])?$options['key']:'';
        $this->redirect_url = isset($options['redirect_url']) ? $options['redirect_url']:'';
    }


    /**
     * 统一支付接口类 主要使用$result['prepay_id']
     * @param $params
     * @return bool|mixed
     */
    public function unifiedOrder($params) {
        $this->body = $params['body'];
        $this->out_trade_no = $params['out_trade_no'];
        $this->total_fee = $params['total_fee'];
        $this->notify_url = $params['notify_url'];
        $this->trade_type = $params['trade_type'];
        $this->spbill_create_ip = $_SERVER['REMOTE_ADDR'];//终端ip
        $this->nonce_str = WxPayCore::createNoncestr();//随机字符串

        $this->params['out_trade_no'] = $this->out_trade_no;
        $this->params['body'] = $this->body;
        $this->params['total_fee'] = $this->total_fee;
        $this->params['notify_url'] = $this->notify_url;
        $this->params['trade_type'] = $this->trade_type;
        $this->params['appid'] = $this->appid;
        $this->params['mch_id'] = $this->mch_id;
        $this->params['spbill_create_ip'] = $this->spbill_create_ip;
        $this->params['nonce_str'] = $this->nonce_str;
        //如果支付方式为JSAPI 则需传入openid
        if ($params['trade_type'] == 'JSAPI') {
            $this->params['openid'] = $this->getOpenid();
        }
        //获取签名
        //参数out_trade_no body total_fee notify_url trade_type openid appid mch_id spbill_create_ip nonce_str
        $this->sign = WxPaySign::getSign($this->params,$this->key);
        $this->params['sign'] = $this->sign;
        //获取post的xml
        $xml = WxPayCore::arrayToXml($this->params);
        //提交
        $response = WxPayCore::postXmlCurl($xml,self::API_URL_PREFIX.self::UNIFIEDORDER_URL);
        if (!$response) {
            return false;
        }
        //拿到结果
        $result = WxPayCore::xmlToArray($response);
        if( !empty($result['result_code']) && !empty($result['err_code']) ){
            $result['err_msg'] = $this->error_code( $result['err_code'] );
        }
        return $result;
    }

    /**
     * 获得openid
     * @return mixed
     */
    public function getOpenid() {
        if ($openid = \Yii::$app->session->get('wx_openid')) {//先读取session中是否有openid
            $this->openid = $openid;
        } else {
            $this->createOauthUrlForOpenid();
            //初始化curl
            $ch = curl_init();
            //设置超时
            //curl_setopt($ch, CURLOP_TIMEOUT, $this->curl_timeout);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
            curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            //运行curl，结果以jason形式返回
            $res = curl_exec($ch);
            curl_close($ch);
            //取出openid
            $data = json_decode($res,true);
            $this->openid = $data['openid'];
            \Yii::$app->session->set('wx_openid',$this->openid);
        }
        return $this->openid;
    }

    /**
     * 	作用：生成可以获得openid的url
     */
    function createOauthUrlForOpenid()
    {
        $urlObj["appid"] = $this->appid;
        $urlObj["secret"] = $this->appsecret;
        $urlObj["code"] = \Yii::$app->session->get('wx_code');
        $urlObj["grant_type"] = "authorization_code";
        $bizString = WxPayCore::formatBizQueryParaMap($urlObj, false);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
    }

    /**
     * 	作用：生成可以获得code的url
     *  然后在redirect_url中获取code 并设置到session['wx_conde']
     */
    function createOauthUrlForCode()
    {
        $urlObj["appid"] = $this->appid;
        $urlObj["redirect_uri"] = $this->redirect_url;
        $urlObj["response_type"] = "code";
        $urlObj["scope"] = "snsapi_base";
        $urlObj["state"] = "STATE"."#wechat_redirect";
        $bizString = WxPayCore::formatBizQueryParaMap($urlObj,false);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
    }

    /**
     * 错误代码
     * @param	$code		服务器输出的错误代码
     * return string
     */
    public function error_code( $code ){
        $errList = array(
            'NOAUTH'				=>	'商户未开通此接口权限',
            'NOTENOUGH'				=>	'用户帐号余额不足',
            'ORDERNOTEXIST'			=>	'订单号不存在',
            'ORDERPAID'				=>	'商户订单已支付，无需重复操作',
            'ORDERCLOSED'			=>	'当前订单已关闭，无法支付',
            'SYSTEMERROR'			=>	'系统错误!系统超时',
            'APPID_NOT_EXIST'		=>	'参数中缺少APPID',
            'MCHID_NOT_EXIST'		=>	'参数中缺少MCHID',
            'APPID_MCHID_NOT_MATCH'	=>	'appid和mch_id不匹配',
            'LACK_PARAMS'			=>	'缺少必要的请求参数',
            'OUT_TRADE_NO_USED'		=>	'同一笔交易不能多次提交',
            'SIGNERROR'				=>	'参数签名结果不正确',
            'XML_FORMAT_ERROR'		=>	'XML格式错误',
            'REQUIRE_POST_METHOD'	=>	'未使用post传递参数 ',
            'POST_DATA_EMPTY'		=>	'post数据不能为空',
            'NOT_UTF8'				=>	'未使用指定编码格式',
        );
        if( array_key_exists( $code , $errList ) ){
            return $errList[$code];
        }
    }
}