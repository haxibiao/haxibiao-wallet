<?php

namespace Haxibiao\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use Haxibiao\Wallet\Recharge;
use Illuminate\Http\Request;
use Yansongda\Pay\Log;
use Yansongda\Pay\Pay;

class PayController extends Controller
{
    public function test()
    {
        if (request('type') == 'wechat') {
            // return redirect()->to("/pay/wechat");
            return view('go_wechat');
        }
        if (request('type') == 'alipay') {
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

    /**
     * 网页打赏
     */
    public function tip()
    {
        $amount  = request('amount');
        $message = urldecode(request('message'));
        $type    = request('type');
        $user    = request()->user();
        if ($user && $user->balance > $amount) {
            //打赏文章
            if (request('article_id')) {
                $user       = getUser();
                $article    = \App\Article::findOrFail(request('article_id'));
                $tip        = $article->tip($amount, $message);
                $log_mine   = '向' . $article->user->link() . '的' . $article->link() . '打赏' . $amount . '元';
                $log_theirs = $user->link() . '向您的' . $article->link() . '打赏' . $amount . '元';
                $user->transfer($amount, $article->user, $log_mine, $log_theirs, $tip->id);

            }
            //网页钱包
            return redirect()->to('/wallet');
        } else {
            //未登录或者不够钱，直接支付宝
            $realPayUrl = '/alipay/wap/pay?amount=' . $amount . '&type=' . $type;
            if (request('article_id')) {
                $realPayUrl .= '&article_id=' . request('article_id');
            }
            //赞赏留言传过去
            if (request('message')) {
                session(['last_tip_message' => request('message')]);
            }
            return redirect()->to($realPayUrl);
        }
    }
}
