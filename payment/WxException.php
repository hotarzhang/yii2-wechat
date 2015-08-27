<?php
/**
 * Created by PhpStorm.
 * User: hoter
 * Date: 15-8-27
 * Time: 下午10:48
 */

namespace hoter\wechat\payment;


class WxException extends \Exception
{
    public function errorMessage()
    {
        return $this->getMessage();
    }

}