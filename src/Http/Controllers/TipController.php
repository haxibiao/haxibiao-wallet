<?php

namespace Haxibiao\Wallet\Http\Controllers;

use App\Article;
use App\Http\Controllers\Controller;
use App\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yansongda\Pay\Pay;

class TipController extends Controller
{
    /**
     * 网页打赏
     */
    public function wapPay()
    {
        $amount = 0.01;
        if (!empty($_GET['amount'])) {
            $amount = $_GET['amount'];
        }
        $type = '充值';
        if (request('type') == 'tip') {
            $type = '打赏';
        }
        $subject = $type;

        if (Auth::check()) {
            $user = Auth::user();
            if ($type == '打赏' && request('article_id')) {
                $article = Article::with('user')->find(request('article_id'));
                if ($article) {
                    $subject = $type . $article->title;
                    //自己账户准备个交易记录
                    $transaction = Transaction::create([
                        'user_id'      => $user->id,
                        'from_user_id' => $user->id,
                        'to_user_id'   => $article->user_id,
                        'type'         => $type,
                        'remark'       => '向' . $article->user->link() . '的文章' . $article->link() . '打赏' . $amount . '元',
                        'amount'       => $amount,
                        'status'       => '未支付',
                        'balance'      => $user->balance,
                    ]);
                    $tran_id1 = $transaction->id;
                    //对方账户准备个交易记录
                    $transaction = Transaction::create([
                        'user_id'      => $article->user->id,
                        'from_user_id' => $user->id,
                        'to_user_id'   => $article->user->id,
                        'type'         => $type,
                        'remark'       => $user->link() . '向您的文章' . $article->link() . '打赏' . $amount . '元',
                        'amount'       => $amount,
                        'status'       => '未支付',
                        'balance'      => $article->user->balance,
                    ]);
                    $tran_id2 = $transaction->id;

                    //打赏 - 到账后两个人钱包交易要更新
                    $out_trade_no = $this->encodeOutTradeNo($type, request('article_id') . '.' . $tran_id1 . '-' . $tran_id2);
                }
            } else {
                //充值　　－－　到账后只更新自己个人钱包
                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'type'    => $type,
                    'remark'  => '充值',
                    'amount'  => $amount,
                    'status'  => '未支付',
                    'balance' => $user->balance,
                ]);
                $tran_id1     = $transaction->id;
                $out_trade_no = $this->encodeOutTradeNo($type, '.' . $tran_id1);
            }

        } else {
            //未登录游客直接打赏
            $out_trade_no = $this->encodeOutTradeNo('匿名', request('article_id'));
        }

        $response = $this->gateway()->purchase()->setBizContent([
            'subject'      => $subject,
            'out_trade_no' => $out_trade_no,
            'total_amount' => $amount,
            'product_code' => 'FAST_INSTANT_TRADE_PAY',
        ])->send();

        $url = $response->getRedirectUrl();
        return redirect($url);
    }

    /**
     * 打赏支付完回调
     */
    public function wapReturn()
    {
        $request = $this->gateway()->completePurchase();
        $request->setParams(array_merge($_POST, $_GET)); //Don't use $_REQUEST for may contain $_COOKIE

        try {
            $response = $request->send();
            if ($response->isPaid()) {
                $amount       = array_get($response->getData(), 'amount');
                $out_trade_no = array_get($response->getData(), 'out_trade_no');

                //匿名直接打赏
                if (str_contains($out_trade_no, '匿名')) {
                    $article_id = intval($this->decodeOutTradeNo($out_trade_no));
                    $this->justTipForArticleUser($article_id, $response);
                    return redirect()->to("/article/$article_id");
                } else {
                    //否充值，或者登录用户打赏完成后，返回钱包查看消费记录
                    if ($this->makePaymentProcess($response)) {
                        return redirect()->to('/wallet');
                    }
                }
            } else {
                /**
                 * Payment is not successful
                 */
                die('fail'); //The notify response
            }
        } catch (Exception $e) {
            /**
             * Payment is not successful
             */
            die('fail'); //The notify response
        }
    }

    /**
     * 打赏支付 - 异步通知
     */
    public function wapNotify()
    {
        $request = $this->gateway()->completePurchase();
        $request->setParams(array_merge($_POST, $_GET)); //Don't use $_REQUEST for may contain $_COOKIE

        /**
         * @var AopTradeAppPayResponse $response
         */

        try {
            $response = $request->send();

            if ($response->isPaid()) {
                $process      = false;
                $out_trade_no = array_get($response->getData(), 'out_trade_no');
                $out_trade_no = $this->decodeOutTradeNo($out_trade_no);

                //匿名直接打赏
                if (str_contains($out_trade_no, '匿名')) {
                    $article_id = intval($this->decodeOutTradeNo($out_trade_no));
                    $process    = $this->justTipForArticleUser($article_id, $response);
                } else {
                    $process = $this->makePaymentProcess($response);
                }

                if ($process) {
                    die('success');
                } else {
                    die('fail');
                }
                //The notify response should be 'success' only
            } else {
                /**
                 * Payment is not successful
                 */
                die('fail'); //The notify response
            }
        } catch (Exception $e) {
            /**
             * Payment is not successful
             */
            die('fail'); //The notify response
        }
    }

    public function justTipForArticleUser($article_id, $response)
    {
        $article = Article::find($article_id);
        if ($article) {
            $amount    = array_get($response->getData(), 'total_amount');
            $user_link = Auth::check() ? Auth::user()->link() : '未登录用户打赏您的文章';

            //登录用户来打赏了，给作者发个消息
            if (Auth::check()) {

            }
            return Transaction::create([
                'user_id' => $article->user->id,
                'type'    => '打赏',
                'remark'  => $user_link . $article->link(),
                'amount'  => $amount,
                'status'  => '已到账',
                'balance' => $article->user->balance + $amount,
            ]);
        }
        return false;
    }

    public function encodeOutTradeNo($type, $out_trade_no)
    {
        return $type . date('YmdH') . $out_trade_no;
    }

    public function decodeOutTradeNo($out_trade_no)
    {
        $out_trade_no = str_replace('充值', '', $out_trade_no);
        $out_trade_no = str_replace('打赏', '', $out_trade_no);
        $out_trade_no = str_replace('匿名', '', $out_trade_no);
        return str_replace(date('YmdH'), '', $out_trade_no);
    }

    public function makePaymentProcess($response)
    {
        $out_trade_no = array_get($response->getData(), 'out_trade_no');
        $ids          = $this->decodeOutTradeNo($out_trade_no);
        if (str_contains($out_trade_no, '充值')) {
            //充值, 只传过来个人充值交易记录tran1的id
            $tran_id1       = str_replace('.', '', $ids);
            $tran1          = Transaction::find($tran_id1);
            $tran1->status  = '已到账';
            $tran1->balance = $tran1->balance + $tran1->amount;
            $tran1->save();
            return true;
        } else {
            //打赏，需要逐步提取article_id, ２个交易的tran_id;
            list($article_id, $tran_ids) = explode('.', $ids);
            list($tran_id1, $tran_id2)   = explode('-', $tran_ids);
            $tran1                       = Transaction::find($tran_id1);
            $tran2                       = Transaction::find($tran_id2);
            if ($tran1 && $tran2) {

                //已登录用户，打赏成功, 记录文章赞赏数，给文章作者发赞赏消息提醒
                $article = \App\Article::findOrFail($article_id);
                $tip     = $article->tip($tran1->amount, session('last_tip_message'));

                //事务保证账户金钱数据完整
                DB::transaction(function () use ($tran1, $tran2, $tip) {
                    $tran1->relate_id = $tip->id;
                    //更新这笔账户状态
                    $tran1->status = '已到账';
                    $tran1->remark = $tran1->remark . '(支付宝)';
                    //这是直接充钱的，不扣现有账户余额...
                    // $tran1->balance = $tran1->balance - $tran1->amount;
                    $tran1->save();

                    //给对方账户到账
                    if ($tran2->status != '已到账') {
                        $tran2->relate_id = $tip->id;
                        $tran2->status    = '已到账';
                        $tran2->balance   = $tran2->balance + $tran2->amount;
                        $tran2->save();
                    }
                });

                return true;
            }
        }

        return false;
    }

}
