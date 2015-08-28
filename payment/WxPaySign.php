<?php
/**
 * Created by PhpStorm.
 * User: hoter.zhang
 * Date: 2015/8/28
 * Time: 10:56
 */

namespace hoter\wechat\payment;


class WxPaySign
{
    /**
     * 	作用：生成签名
     */
    public static function getSign($Obj,$key)
    {
        foreach ($Obj as $k => $v)
        {
            $Parameters[$k] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = WxPayCore::formatBizQueryParaMap($Parameters, false);
        //echo '【string1】'.$String.'</br>';
        //签名步骤二：在string后加入KEY
        $String = $String."&key=".$key;
        //echo "【string2】".$String."</br>";
        //签名步骤三：MD5加密
        $String = md5($String);
        //echo "【string3】 ".$String."</br>";
        //签名步骤四：所有字符转为大写
        $result_ = strtoupper($String);
        //echo "【result】 ".$result_."</br>";
        return $result_;
    }
}