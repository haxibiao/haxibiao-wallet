<?php

namespace Haxibiao\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use Haxibiao\Wallet\Recharge;
use Illuminate\Http\Request;
use Yansongda\Pay\Log;
use Yansongda\Pay\Pay;

class PayController extends Controller
{
    public function index()
    {
        if (request('test') == 'wechat') {
            return redirect()->to("/pay/wechat");
        }
        if (request('test') == 'alipay') {
            return redirect()->to("/pay/alipay");
        }

        dd("支付系统调试入口，详细请阅读breeze文档");
    }

    public function alipay()
    {
        $order = [
            'out_trade_no' => time(),
            'total_fee'    => '100', // **单位：分**
            'body'         => 'test body - 测试',
            // 'openid'       => 'ocgJe6udpv1EBi6k7fOLf7jP5K48',
        ];

        $config = config('pay.alipay');
        $pay    = Pay::wechat($config)->wap($order);
        return $pay;

        // $pay->appId
        // $pay->timeStamp
        // $pay->nonceStr
        // $pay->package
        // $pay->signType
    }

    public function wechat()
    {
        $order = [
            'out_trade_no' => time(),
            'total_fee'    => '100', // **单位：分**
            'body'         => 'test body - 测试',
            // 'openid'       => 'ocgJe6udpv1EBi6k7fOLf7jP5K48',
        ];

        $config = config('pay.wechat');
        // PC 场景扫码支付
        // $pay    = Pay::wechat($config)->scan($order);
        $pay = Pay::wechat($config)->wap($order);

        // dd($pay);
        return $pay;
    }

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
            $amount = data_get($data, 'total_fee') / 100;
            // 充值
            Recharge::completeRecharge($trade_no, 'wechat', $amount, $data);
        } else {
            Log::error('Wechat notify', $data->all());
        }
        return $wechat->success();
    }

}
