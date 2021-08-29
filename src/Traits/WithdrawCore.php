<?php

namespace Haxibiao\Wallet\Traits;

use App\OAuth;
use App\Withdraw;
use Exception;
use Haxibiao\Breeze\ErrorLog;
use Haxibiao\Breeze\Exceptions\ErrorCode;
use Haxibiao\Wallet\Events\WithdrawalDone;
use Haxibiao\Wallet\Exceptions\PayPlatformBalanceNotEnoughException;
use Haxibiao\Wallet\Exceptions\WithdrawException;
use Haxibiao\Wallet\Exchange;
use Haxibiao\Wallet\Gold;
use Haxibiao\Wallet\InvitationWithdraw;
use Haxibiao\Wallet\JDJRWithdraw;
use Haxibiao\Wallet\Jobs\ProcessWithdraw;
use Haxibiao\Wallet\LuckyWithdraw;
use Haxibiao\Wallet\Strategies\Pay\PayStrategyMaker;
use Haxibiao\Wallet\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait WithdrawCore
{
    public function hasPay()
    {
        return !empty($this->transaction_id);
    }

    public function convertToChildrenObj()
    {
        if ($this->platformIs(Withdraw::JDJR_PLATFORM)) {
            return JDJRWithdraw::find($this->id);
        }

        if ($this->isInviteActivityType()) {
            return InvitationWithdraw::find($this->id);
        }

        if ($this->isLuckyDrawType()) {
            return LuckyWithdraw::find($this->id);
        }

        return $this;
    }

    public function checkSafe($isThrow = false): bool
    {
        $isSafe = false;
        $user   = $this->user;
        $wallet = $this->wallet;

        if (!is_null($user) && !is_null($wallet)) {
            // 1.用户检查
            $isBadUser = $user->isBad();
            throw_if($isThrow && $isBadUser, WithdrawException::class, ErrorCode::WITHDRAW_USER_IS_DISABLED);

            // 2.钱包余额检查
            $balanceNotEnough = !$this->hasPay() && !$wallet->canPay($this->amount);
            throw_if($isThrow && $balanceNotEnough, WithdrawException::class, ErrorCode::WALLET_BALANCE_NOT_ENOUGH);

            // 3.处理后置并发提现请求
            $todayHasSuccessWithdraw = $wallet->todaySuccessWithdrawCount > 0;
            throw_if($isThrow && $todayHasSuccessWithdraw, WithdrawException::class, ErrorCode::WALLET_TODAY_LIMITED);

            $isSafe = !$isBadUser || !$balanceNotEnough || !$todayHasSuccessWithdraw;
        }

        return $isSafe;
    }

    public function retry($async = true)
    {
        return $async ? dispatch(new ProcessWithdraw($this)) : dispatch_now(new ProcessWithdraw($this));
    }

    public function process()
    {
        $isSafe = false;
        // fixed:先暂时限制住10元及以上的提现,必须人工审核
        if ($this->amount >= 10 && empty($this->reviewed_at)) {
            return;
        }

        if ($this->isWaiting()) {
            try {
                $isSafe = $this->checkSafe(true);
            } catch (Exception $ex) {
                $this->markFailed($ex->getMessage() ?? '未知异常');
            }

            if ($isSafe) {
                try {
                    $transferResult  = $this->makingTransfer();
                    $transferOrderId = $transferResult->getOrderId();
                    if (!empty($transferOrderId)) {
                        $this->settleSuccess($transferOrderId);
                    } else {
                        $this->settleFailed($transferResult);
                    }
                    $this->pushBoardcastEvent();
                } catch (Exception $ex) {
                    $msg = $ex instanceof PayPlatformBalanceNotEnoughException ? sprintf('【%s】支付平台余额不足!!!', $this->to_platform) : '';
                    ErrorLog::error($ex, $msg);
                    Withdraw::balanceSpendSmsNotice($this->to_platform, $msg);

                }
            }
        }

        return $this;
    }

    protected function makingTransfer()
    {
        $wallet                                  = $this->wallet;
        $user                                    = $this->user;
        $platform                                = $this->to_platform;
        list($siteName, $siteDomain, $isOurSite) = Withdraw::getPlatformInfo($platform);

        if (!$isOurSite) {
            // 支付宝、微信、QQ等提现策略业务参数
            $transferPaymentInfo = [
                'systemBizNo' => $this->biz_no,
                'amount'      => $this->amount,
                'pay_id'      => $this->to_account,
                'real_name'   => $wallet->real_name,
                'remark'      => sprintf('【%s】提现', config('app.name_cn')),
            ];

            // FIXME:微信授权因为混串app_id使用,所以每次支付需要携带appid进去支付.
            if ($this->platformIs(Withdraw::WECHAT_PLATFORM)) {
                $appId = data_get(OAuth::with('appId')
                        ->ofType(OAuth::WECHAT_TYPE)
                        ->where('oauth_id', $this->to_account)
                        ->select('app_id')
                        ->first(), 'appId.value');
                if (!empty($appId)) {
                    $transferPaymentInfo['appid'] = $appId;
                }
            }
        } else {
            // 内部站点服务群:答妹、懂得赚提现策略业务参数
            $transferPaymentInfo = [
                'uuid'                    => $user->uuid,
                'transfer_to_domain'      => $siteDomain,
                'transfer_to_site_userid' => $this->to_account,
                'system_userid'           => $this->user_id,
                'amount'                  => $this->amount,
            ];
        }

        $strategy = $platform == 'qq' ? 'QPay' : ($isOurSite ? 'HashSitePay' : $platform);
        return PayStrategyMaker::setStrategy($strategy)->transfer($transferPaymentInfo);
    }

    /**
     * 提现退款
     *
     * @param boolean $forceRefund
     * @return boolean
     */
    public function refund($forceRefund = false): bool
    {
        $isRefundSuccess = false;
        if ($this->isFailed() || $forceRefund) {
            $user   = $this->user;
            $wallet = $this->wallet;
            $amount = $this->amount;
            $gold   = Exchange::computeGold($amount);
            if (!is_null($wallet) && !is_null($user)) {
                // 现金钱包采取 扣款 && 退回智慧点
                $transaction = Transaction::makeOutcome($wallet, $amount, '提现失败');
                Gold::makeIncome($user, $gold, '提现失败退款');
                Exchange::exhangeIn($user, $gold);
                $isRefundSuccess = true;
            }
        }

        return $isRefundSuccess;
    }

    /**
     * 强制退款
     *
     * @return bool
     */
    public function forceRefund(): bool
    {
        return $this->refund(true);
    }

    public function markFailed($remark, $isSave = true)
    {
        $this->status = Withdraw::FAILED_STATUS;
        $this->remark = $remark;

        if ($remark == "非实名用户账号不可发放") {
            $this->remark = "您的微信还没有实名认证哦，快去认证吧";
        }

        if ($isSave) {
            $this->save();
        }

        return $this;
    }

    public function markSuccess($tradeNo, $isSave = true)
    {
        $this->status   = Withdraw::SUCCESS_STATUS;
        $this->trade_no = $tradeNo;
        if ($isSave) {
            $this->save();
        }

        return $this;
    }

    public function settleFailed($transferResult)
    {
        $failedMsg = $transferResult->getMsg();
        $withdraw  = null;
        DB::beginTransaction(); //开启事务
        try {
            $withdraw = static::lockForUpdate()->find($this->id);
            if (!is_null($withdraw)) {
                $withdraw->transfer_raw_result = $transferResult->getResult();
                $withdraw->markFailed($failedMsg);
                $withdraw->refund();
            }
            DB::commit();
        } catch (Exception $ex) {
            Log::error($ex);
            DB::rollback(); //数据回滚
        }

        return $withdraw;
    }

    public function settleSuccess($tradeNo)
    {
        $withdraw = null;
        DB::beginTransaction();
        try {
            $withdraw = static::lockForUpdate()->find($this->id);
            if (!is_null($withdraw)) {
                $withdraw->markSuccess($tradeNo, false);
                // 如果没有扣款的话,进行扣款支付！
                if (!$withdraw->hasPay()) {
                    $transaction              = Transaction::makeOutcome($this->wallet, $withdraw->amount, '提现');
                    $withdraw->transaction_id = $transaction->id;
                }
                $withdraw->save();
            }
            DB::commit();
        } catch (Exception $ex) {
            Log::error($ex);
            DB::rollback(); //数据回滚
        }

        return $withdraw;
    }

    public function syncData()
    {
        $wallet = $this->wallet;
        if (!is_null($wallet)) {
            //更新钱包交易总额
            $sucessWithdrawAmount          = $wallet->success_withdraw_sum_amount;
            $wallet->total_withdraw_amount = $sucessWithdrawAmount;
            $wallet->save();
        }
    }

    public function pushBoardcastEvent()
    {
        return event(new WithdrawalDone($this));
    }

}
