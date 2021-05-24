<?php

namespace Haxibiao\Wallet;

use Haxibiao\Breeze\Exceptions\UserException;
use Haxibiao\Breeze\Helpers\Redis\RedisHelper;
use Haxibiao\Breeze\Traits\ModelHelpers;
use Haxibiao\Breeze\User;
use Haxibiao\Wallet\Traits\WalletAttrs;
use Haxibiao\Wallet\Traits\WalletRepo;
use Haxibiao\Wallet\Traits\WalletResolvers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use WalletRepo, WalletResolvers, WalletAttrs, ModelHelpers;

    protected $guarded = [

    ];

    protected $casts = [
        'balance'   => 'double',
        'pay_infos' => 'array',
    ];

    private $cacheable = [
        'totalIncome' => 'totalIncome',
    ];

    //提现资料更改上限
    const PAY_INFO_CHANGE_MAX = 3;

    //钱包类型
    const RMB_WALLET  = 0; //RMB钱包
    const GOLD_WALLET = 1; //金币钱包

    const INVITATION_TYPE = 'INVITATION';
    const LUCKYDRAW_TYPE  = 'LUCKYDRAW';
    const RMB_TYPE        = 'RMB';

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    public function withdraws(): HasMany
    {
        return $this->hasMany(Withdraw::class)->latest();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->latest('id');
    }

    public function golds()
    {
        //FIXME: 修复之前user_id对应的golds流水未对应的钱包的
        return $this->hasMany(Gold::class);
    }

    public function lastWithdraw()
    {
        return $this->hasOne(Withdraw::class)->latest('id');
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRmbType($query)
    {
        return $query->ofType(Wallet::RMB_TYPE);
    }

    public function scopeLeastWithdraw($query, $amount)
    {
        return $query->where('total_withdraw_amount', '>=', $amount);
    }

    public function withdraw($amount)
    {

    }

    public function canWithdraw($amount)
    {
        return $this->balance >= $amount;
    }

    /**
     * 足够支付
     *
     * @param float $amount
     * @return void
     */
    public function availablePay($amount)
    {
        return $this->availableBalance >= $amount;
    }

    /**
     * 可支付的金额
     *
     * @param float $amount
     * @return boolean
     */
    public function canPay($amount)
    {
        return $this->balance >= $amount;
    }

    /**
     * 可用余额
     */
    public function getAvailableBalanceAttribute()
    {
        $availableBalance = $this->balance - $this->withdraws()
            ->wating()
            ->watingPay()
            ->sum('amount');

        return $availableBalance;
    }

    // /**
    //  * RMB余额
    //  */
    // public function getBalanceAttribute()
    // {
    //     $callable = [$this, 'refreshBalance'];
    //     return $this->getCachedAttribute('balance', $callable);
    // }

    /**
     * 刷新金额
     *
     * @return float
     */
    public function refreshBalance()
    {
        $lastTransaction = $this->transactions()->latest('id')->select('balance')->first();
        $balance         = !is_null($lastTransaction) ? $lastTransaction->balance : 0;
        //这里主动更新一下model cache
        $this->setCachedAttribute('balance', $balance);

        return $balance;
    }

    /**
     * 累计成功提现
     */
    public function getSuccessWithdrawSumAmountAttribute()
    {
        return $this->withdraws()->success()->sum('amount');
    }

    public function getAvailablePayInfoChangeCountAttribute()
    {
        $payInfoChangeCount = $this->pay_info_change_count;

        return $payInfoChangeCount >= self::PAY_INFO_CHANGE_MAX ? -1 : $payInfoChangeCount;
    }

    public function getAvaliableWithdrawCountOfToday()
    {
        $maxCount                = Withdraw::DAILY_MAX_WITHDRAW_COUNT;
        $todayWithoutFailedCount = $this->withdraws()->today()->withoutFailed()->count('id');
        return $maxCount - $todayWithoutFailedCount;
    }

    /**
     * 今日提现总数
     *
     * @return int
     */
    public function getTodayWithdrawAmountAttribute()
    {
        return $this->withdraws()
            ->today()
            ->where('status', '>=', 0)
            ->sum('amount');
    }

    /**
     * 今日提现成功笔数
     *
     * @return int
     */
    public function getTodaySuccessWithdrawCountAttribute()
    {
        return $this->withdraws()->today()->success()->count();
    }

    public function getPlatformsAttribute()
    {
        return [
            'alipay' => empty($this->pay_account) ? null : $this->pay_account,
            'wechat' => empty($this->open_id) ? null : $this->open_id,
        ];
    }

    public function todayIncome()
    {
        return $this->transactions()->where('amount', '>', 0)->whereBetWeen('created_at', [today(), today()->addDay()])->sum('amount');
    }

    public function getTotalIncomeAttribute()
    {
        return $this->transactions()->where('amount', '>', 0)->sum('amount');
    }

    public static function findOrCreate($userId, $type = self::RMB_TYPE)
    {
        $wallet = Wallet::firstOrCreate(['user_id' => $userId, 'type' => $type]);

        return $wallet;
    }

    public static function goldWalletOf(User $user): Wallet
    {
        $wallet = self::firstOrCreate([
            'user_id' => $user->id,
            'type'    => 1,
        ]);
        return $wallet;
    }

    public function isCanWithdraw($amount)
    {
        return $this->availableBalance >= $amount;
    }

    public function todayApprenticeBonus()
    {
        $amount = $this->transactions()->today()->where('remark', '徒弟看视频收益')->sum('amount');

        return $amount;
    }

    public function todaySecondApprenticeBonus()
    {
        $amount = $this->transactions()->today()->where('remark', '徒孙看视频收益')->sum('amount');

        return $amount;
    }

    public function getFormatedBalanceAttribute()
    {
        return number_format($this->balance, 4);
    }

    public function isOfRMB()
    {
        return $this->type == Wallet::RMB_TYPE;
    }

    public function isOfInvitation()
    {
        return $this->type == Wallet::INVITATION_TYPE;
    }

    public function isOfLuckyDraw()
    {
        return $this->type == Wallet::LUCKYDRAW_TYPE;
    }

    public function createWithdraw($amount, $platform, $type)
    {
        $wallet = $this;
        $user   = $wallet->user;

        //余额不足，自动提前兑换金币
        if (!$wallet->availablePay($amount)) {
            // 现金钱包,兑换智慧点到余额中
            if ($wallet->isOfRMB()) {
                //兑换现金
                Wallet::exchangeBalance($user, $amount);
                //刷新一下余额,不用刷新整个wallet对象
                $wallet->refreshBalance();
            }
            //兑换余额后,还不能支付提现金额
            if (!$wallet->availablePay($amount)) {
                if ($type == Withdraw::INVITE_ACTIVITY_TYPE) {
                    throw new UserException("您发起活动提现的邀请目标金额未达标哦，快去邀请吧!");
                } else {
                    throw new UserException("账户余额不足,请稍后再试!");
                }
            }
        }

        // 防止并发请求 && 限制每个用户只能提现1次
        $redis    = RedisHelper::redis();
        $cacheKey = sprintf('withdraw:date:%s:user:%s', date('Ymd'), $wallet->user_id);
        if (!is_null($redis)) {
            if (!Withdraw::isWhiteListMemeber($user->id)) {
                //今日提现过其他额度后，不限制京东金融的提现
                if ($platform != Withdraw::JDJR_PLATFORM) {
                    throw_if($redis->incrby($cacheKey, 1) > 1, UserException::class, '提现失败,您今日提次数已达上限!');
                    $redis->expireat($cacheKey, now()->endOfDay()->timestamp);
                }
            }
        }

        // 创建提现记录
        $withdraw = Withdraw::createWithdrawWithWallet($wallet, $amount, $platform, $type);
        // 邀请钱包 || 高额抽奖钱包 && 直接扣款
        if ($wallet->isOfInvitation() || $wallet->isOfLuckyDraw()) {
            $transaction              = Transaction::makeOutcome($wallet, $amount, '活动提现');
            $withdraw->transaction_id = $transaction->id;
            $withdraw->save();
        }
        return $withdraw;

    }

    public function getTotalWithdrawAmountSumAttribute()
    {
        if ($this->user) {
            $this->user->wallets()->sum('total_withdraw_amount') ?? 0;
        }
        return 0;
    }

    public function makeIncome($amount, $relateable, $remark = '智慧点兑换')
    {
        if (is_object($relateable)) {
            return Transaction::makeRelateIncome($this, $amount, $relateable, $remark);
        } else {
            return Transaction::makeIncome($this, $amount, $remark);
        }
    }

    public function makeOutcome($amount, $relateable, $remark = '智慧点兑换')
    {
        if (is_object($relateable)) {
            return Transaction::makeRelateOutcome($this, $amount, $relateable, $remark);
        } else {
            return Transaction::makeOutcome($this, $amount, $remark);
        }
    }

    //repo
    public static function rmbWalletOf($user): Wallet
    {
        $wallet = self::firstOrCreate([
            'user_id' => $user->id,
            'type'    => Wallet::RMB_WALLET,
        ]);
        return $wallet;
    }
}
