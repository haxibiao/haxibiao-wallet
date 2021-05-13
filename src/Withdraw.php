<?php

namespace Haxibiao\Wallet;

use Haxibiao\Breeze\Traits\ModelHelpers;
use Haxibiao\Wallet\Traits\CanWithdraw;
use Haxibiao\Wallet\Traits\WithdrawCore;
use Haxibiao\Wallet\Traits\WithdrawFacade;
use Haxibiao\Wallet\Traits\WithdrawRepo;
use Haxibiao\Wallet\Traits\WithdrawResolvers;
use Haxibiao\Wallet\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Nova\Actions\Actionable;

class Withdraw extends Model
{
    use WithdrawResolvers;
    use WithdrawRepo;
    use WithdrawFacade;

    use Actionable;

    use CanWithdraw;
    use WithdrawCore;
    use ModelHelpers;

    protected $fillable = [
        'user_id',
        'wallet_id',
        'host',
        'status',
        'transaction_id',
        'amount',
        'remark',
        'trade_no',
        'to_account',
        'to_platform',
        'created_at',
        'updated_at',
        'type',
        'reviewed_at',
    ];

    protected $casts = [
        'amount' => 'double',
    ];

    //提现平台
    const ALIPAY_PLATFORM = 'alipay';
    const WECHAT_PLATFORM = 'wechat';
    const DDZ_PLATFORM    = 'dongdezhuan';
    const DM_PLATFORM     = 'damei';
    const DZ_PLATFORM     = 'datizhuanqian';
    const QQ_PLATFORM     = 'qq';
    const JDJR_PLATFORM   = 'jdjr';
    // 我们自己的平台
    const OUR_SITE = [self::DDZ_PLATFORM, self::DM_PLATFORM, self::DZ_PLATFORM];

    //状态:提现成功 提现失败 待处理提现
    const SUCCESS_WITHDRAW = 1;
    const FAILURE_WITHDRAW = -1;
    const WAITING_WITHDRAW = 0;

    const WITHDRAW_MAX = 1;

    //状态:提现成功 提现失败 待处理提现
    const SUCCESS_STATUS = 1;
    const FAILED_STATUS  = -1;
    const WATING_STATUS  = 0;

    const MAX_WITHDRAW_SUM_AMOUNT = 3000;

    //提现类型
    const FIXED_TYPE            = 0;
    const RANDOM_TYPE           = 1;
    const INVITE_ACTIVITY_TYPE  = 2;
    const TENCENT_QB_TYPE       = 3;
    const TELEPHONE_CHARGE_TYPE = 4;
    const LUCKYDRAW_TYPE        = 5;

    //成功提现类型
    const SUCCESS_WITHDRAWS_1   = 1; // 成功提现 1 次
    const SUCCESS_WITHDRAWS_2_7 = 2; // 成功提现 2-7 次
    const SUCCESS_WITHDRAWS_7   = 3; // 成功提现 7 次以上

    const DAILY_MAX_WITHDRAW_COUNT = 1;

    // public function getUserAttribute()
    // {
    //     return $this->wallet->user;
    // }

    public function transaction()
    {
        return $this->belongsTo(\App\Transaction::class);
    }

    public function getUserRealNameAttribute()
    {
        return data_get($this->wallet, 'real_name');
    }

    public function getBizNoAttribute()
    {
        //拼接格式 年月日时分秒 + 提现订单号
        return $this->created_at->format('YmdHis') . $this->id;
    }

    public function scopeSuccess($query)
    {
        return $query->where($this->getTable() . '.status', self::SUCCESS_STATUS);
    }

    public function scopeWating($query)
    {
        return $query->where($this->getTable() . '.status', self::WATING_STATUS);
    }

    public function scopeFailed($query)
    {
        return $query->where($this->getTable() . '.status', self::FAILED_STATUS);
    }

    public function scopeRandomType($query)
    {
        return $query->where($this->getTable() . '.type', self::RANDOM_TYPE);
    }

    public function scopeInviteActivityType($query)
    {
        return $query->where($this->getTable() . '.type', self::INVITE_ACTIVITY_TYPE);
    }

    public function scopeLuckDrawType($query)
    {
        return $query->where($this->getTable() . '.type', self::LUCKYDRAW_TYPE);
    }

    public function scopeWatingPay($query)
    {
        return $query->whereNull('transaction_id');
    }

    public function scopeOfPlatform($query, $value)
    {
        return is_array($value) ? $query->whereIn('to_platform', $value) : $query->where('to_platform', $value);
    }

    public function scopeWithoutFailed($query)
    {
        return $query->where($this->getTable() . '.status', '>=', self::WATING_STATUS);
    }

    public function getMorphClass()
    {
        return 'withdraws';
    }

    public function scopeToday($query, $column = 'created_at')
    {
        return $query->where($column, '>=', today());
    }

    public function user()
    {
        return $this->wallet->user();
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    // attrs

    public function isSuccessWithdraw()
    {
        return $this->status == self::SUCCESS_WITHDRAW;
    }

    public function isWaitingWithdraw()
    {
        return $this->status == self::WAITING_WITHDRAW;
    }

    public function isWaiting()
    {
        return $this->status == self::WAITING_WITHDRAW;
    }

    public function isFailureWithdraw()
    {
        return $this->status == self::FAILURE_WITHDRAW;
    }

}
