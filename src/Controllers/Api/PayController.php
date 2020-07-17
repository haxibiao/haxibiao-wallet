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
        Log::info('apple', "=====================================================================");
        Log::info('apple', $request->all());
    }

    /**
     * 验证AppStore内付
     * @param  string $receipt_data 付款后凭证
     * @return array                验证是否成功
     */
    protected function validate_apple_pay($receipt_data, $ios_sandBox)
    {
        /**
         * 21000 App Store不能读取你提供的JSON对象
         * 21002 receipt-data域的数据有问题
         * 21003 receipt无法通过验证
         * 21004 提供的shared secret不匹配你账号中的shared secret
         * 21005 receipt服务器当前不可用
         * 21006 receipt合法，但是订阅已过期。服务器接收到这个状态码时，receipt数据仍然会解码并一起发送
         * 21007 receipt是Sandbox receipt，但却发送至生产系统的验证服务
         * 21008 receipt是生产receipt，但却发送至Sandbox环境的验证服务
         */
        $POSTFIELDS = '{"receipt-data":"' . $receipt_data . '"}';
        if ($ios_sandBox) {
            $data = $this->httpRequest('https://sandbox.itunes.apple.com/verifyReceipt', $POSTFIELDS);
        } else {
            // 请求验证
            $data = $this->httpRequest('https://buy.itunes.apple.com/verifyReceipt', $POSTFIELDS);
        }
        return $data;
    }

    protected function httpRequest($url, $postData = array(), $json = true)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($postData) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        $data = curl_exec($ch);
        curl_close($ch);
        if ($json) {
            return json_decode($data, true);
        } else {
            return $data;
        }
    }
}
