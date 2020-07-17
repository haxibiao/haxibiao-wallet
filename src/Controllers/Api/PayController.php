<?php

namespace Haxibiao\Wallet\Controllers\Api;

use App\Http\Controllers\Controller;
use Haxibiao\Wallet\Recharge;
use Illuminate\Http\Request;
use Yansongda\Pay\Log;
use Yansongda\Pay\Pay;

class PayController extends Controller
{
    /**
     * 没用到此回调，但签名生成需要此接口
     */
    public function alipayReturn(Request $request)
    {
        Log::info("=============alipay return============");
        Log::info('alipay return', json_encode($request->all()));
    }

    /**
     * 支付宝交易结束回调处
     */
    public function alipayNotify()
    {
        $alipay    = Pay::alipay(config('pay.alipay'));
        $data      = $alipay->verify();
        $payStatus = data_get($data, 'trade_status');
        // 是否交易成功
        if ($payStatus == 'TRADE_SUCCESS') {
            $trade_no = data_get($data, 'out_trade_no');
            $amount   = data_get($data, 'buyer_pay_amount');
            // 充值
            Recharge::completeRecharge($trade_no, 'alipay', $amount, $data);
        } else {
            Log::error('Alipay notify', $data->all());
        }
        return $alipay->success();
    }

    /**
     * 微信交易结束回调处
     */
    public function wechatNotify()
    {
        $wechat = Pay::wechat(config('pay.wechat'));
        $data   = $wechat->verify();
        if (data_get($data, 'result_code') == 'SUCCESS') {
            $trade_no = data_get($data, 'out_trade_no');
            // 微信金额单位为分
            $amount = data_get($data, 'total_fee') * 100;
            // 充值
            Recharge::completeRecharge($trade_no, 'wechat', $amount, $data);
        } else {
            Log::error('Wechat notify', $data->all());
        }
        return $wechat->success();
    }

    public function appleNotify(Request $request)
    {
        Log::info('apple', $request->all());
        $receipt  = $request->post('receipt');
        $trade_no = $request->post('trade_no');
        return Recharge::ResolverVerifyApplePay($receipt, $trade_no);
    }
}
