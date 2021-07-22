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
        $trade_no   = request('trade_no') ?? time();
        $amount     = request('amount') ?? 1;
        $subject    = request('subject') ?? "测试一笔";
        $return_url = request()->url();

        if (request('type') == 'wechat') {
            return view('pay.wechat_go')
                ->with('return_url', $return_url)
                ->with('trade_no', $trade_no)
                ->with('amount', $amount)
                ->with('subject', $subject);
        }
        if (request('type') == 'alipay') {
            return redirect()->to("/pay/alipay");
        }

        return view('pay.test')
            ->with('trade_state', request('trade_state'))
            ->with('trade_no', request('trade_no'));
    }

    public function alipay()
    {
        $trade_no = request('trade_no') ?? time();
        $amount   = request('amount') ?? 1;
        $subject  = request('subject') ?? "测试一笔";

        $order = [
            'out_trade_no' => $trade_no,
            'total_fee'    => $amount * 100, // **单位：分**
            'body'         => $subject,
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
        $trade_no = request('trade_no') ?? time();
        $amount   = request('amount') ?? 1;
        $subject  = request('subject') ?? "测试一笔";

        $trade_state = session('trade_state');
        //已查询支付成功的回调返回，返回到原支付场景URL
        if ($trade_state == 'SUCCESS') {
            // 返回已支付成功状态
            $referer = request('return_url') ?? request()->headers->get('referer');
            $with    = http_build_query(['trade_state' => 'SUCCESS', 'trade_no' => $trade_no]);
            if (parse_url($referer, PHP_URL_QUERY)) {
                $referer .= "&" . $with;
            } else {
                $referer .= "?" . $with;
            }
            return redirect()->to($referer);
        }

        $order = [
            'out_trade_no' => $trade_no,
            'total_fee'    => $amount * 100, // **单位：分**
            'body'         => $subject,
        ];

        $config = config('pay.wechat');
        // PC 场景扫码支付
        if (isDesktop()) {
            $pay      = Pay::wechat($config)->scan($order);
            $code_url = $pay->code_url;
            return view('pay.wechat_scan')
                ->with('trade_state', $trade_state)
                ->with('code_url', $code_url)
                ->with('order', $order);
        } else {
            $pay = Pay::wechat($config)->wap($order);
        }
        return $pay;
    }

    /**
     * 支付宝返回地址
     */
    public function alipayReturn(Request $request)
    {
        Log::info("=============alipay return============");
        Log::info('alipay return', json_encode($request->all()));
    }

    /**
     * 微信扫码支付验证地址
     */
    public function wechatReturn(Request $request)
    {
        if ($trade_no = request('trade_no')) {
            $order = [
                'out_trade_no' => $trade_no,
            ];
            $wechat = Pay::wechat(config('pay.wechat'));
            $data   = $wechat->find($order);
            // 支付成功
            if ($data) {
                if ($data->trade_state == 'SUCCESS') {
                    $trade_no = data_get($data, 'out_trade_no');
                    // 微信金额单位为分
                    $amount = data_get($data, 'total_fee') / 100;
                    // 充值
                    Recharge::completeRecharge($trade_no, 'wechat', $amount, $data);
                }
                if (!request()->ajax()) {
                    // 跳转返回已支付成功状态
                    $with    = ['trade_state' => $data->trade_state];
                    $referer = request('return_url') ?? request()->headers->get('referer');
                    return redirect()->to($referer)->with($with);
                }
            }
            return $data;

        } else {
            dd('缺少参数 trade_no');
        }
    }

    /**
     * 支付宝交易结束回调处
     */
    public function alipayNotify()
    {
        $alipay = Pay::alipay(config('pay.alipay'));
        $data   = $alipay->verify();

        $payStatus = data_get($data, 'trade_status');
        // 是否交易成功
        if ($payStatus == 'TRADE_SUCCESS') {
            $trade_no = data_get($data, 'out_trade_no');
            $amount   = data_get($data, 'buyer_pay_amount');
            // 充值
            Recharge::completeRecharge($trade_no, 'alipay', $amount, $data);
        } else {
            Log::error(' === alipay notify', $data->all());
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

        \info(' === wechat notify data');
        if (data_get($data, 'result_code') == 'SUCCESS') {
            $trade_no = data_get($data, 'out_trade_no');
            // 微信金额单位为分
            $amount = data_get($data, 'total_fee') / 100;
            // 充值
            Recharge::completeRecharge($trade_no, 'wechat', $amount, $data);
        } else {
            Log::error(' === wechat notify error: ', $data->all());
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
