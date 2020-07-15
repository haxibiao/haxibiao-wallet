<?php
namespace Haxibiao\Wallet\Traits;

use Haxibiao\Wallet\Recharge;
use Yansongda\Pay\Pay;

trait RechargeResolvers
{
    /**
     * 发起充值请求,获取交易平台提供的签名唤起app
     * 签名包含: 充值金额,内部交易标识,充值标题
     */
    public static function rsolverGetRechargeSignature($amount, $platform)
    {
        $user     = getUser();
        $title    = sprintf('%s充值%s', config('app.name_cn'), $amount);
        $trade_no = \str_random(30);
        if ($platform == 'ALIPAY') {
            // 支付数据数组的key不能变!
            $order = [
                'total_amount' => $amount,
                'out_trade_no' => $trade_no,
                'subject'      => $title,
            ];
            $signature = Pay::alipay(config('pay.alipay'))->app($order)->getContent();

        } else if ($platform == 'WECHAT') {
            $order = [
                'out_trade_no' => $trade_no,
                'body'         => $title,
                // 微信支付单位是分
                'total_fee'    => $amount * 100,
            ];
            $signature = Pay::wechat(config('pay.wechat'))->app($order)->getContent();
        }

        // 创建充值记录
        Recharge::createRecharge($user->id, $trade_no, $title, $amount, $platform);
        return [
            $platform => $signature,
        ];
    }
}
