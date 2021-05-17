<?php

namespace Haxibiao\Wallet\Traits;

use Haxibiao\Helpers\utils\PayUtils;
use Haxibiao\Wallet\Withdraw;

trait WalletAttrs
{
    /**
     * 今日提现成功笔数
     *
     * @return int
     */
    public function getTodaySuccessWithdrawCountAttribute()
    {
        return $this->withdraws()->today()->success()->count();
    }

    public function getAvailableWithdrawCountAttribute()
    {
        $maxCount      = 1; //提现次数上限
        $withdrawCount = $this->withdraws()
            ->today()
            ->where('status', '>=', 0)
            ->count();
        return $maxCount - $withdrawCount;
    }

    public function getBalanceAttribute()
    {
        $lastTransaction = $this->transactions()->latest('id')->select('balance')->first();
        return $lastTransaction->balance ?? 0;
    }

    public function getAvailableBalanceAttribute()
    {
        $availableBalance = $this->balance - $this->withdraws()
            ->where('status', Withdraw::WAITING_WITHDRAW)
            ->sum('amount');

        return $availableBalance;
    }

    public function getGoldBalanceAttribute()
    {
        $lastTransaction = $this->golds()->latest('id')->select('balance')->first();
        return $lastTransaction->balance ?? 0;
    }

    public function getSuccessWithdrawSumAmountAttribute()
    {
        return $this->withdraws()->where('status', Withdraw::SUCCESS_WITHDRAW)->sum('amount');
    }

    public function getTodayWithdrawAttribute()
    {
        return $this->withdraws()->where('created_at', '>=', today())->first();
    }

    public function getDongdezhuanAttribute()
    {
        $user          = currentUser();
        $dongdezhuanId = $user->oauth()->where('oauth_type', 'dongdezhuan')->first()->oauth_id;

    }

    public function getTodayWithdrawLeftAttribute()
    {
        $count = 10;
        // if (!is_null($this->todayWithdraw)) {
        //     $count = 0;
        // }

        return $count;
    }

    public function getPlatformsAttribute()
    {
        return [
            'alipay' => empty($this->pay_account) ? null : $this->pay_account,
            'wechat' => empty($this->wechat_account) ? null : $this->wechat_account,
        ];
    }

    public function getBindPlatformsAttribute()
    {
        $bindPlatfroms = [];
        $platforms     = ['alipay', 'qq', 'wechat'];
        $oauths        = $this->user->oauths()->ofType($platforms)->get();
        foreach ($platforms as $platform) {
            $bindPlatfroms[$platform] = data_get($oauths->firstWhere('oauth_type', $platform), 'oauth_id');
        }

        return $bindPlatfroms;
    }
}
