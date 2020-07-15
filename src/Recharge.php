<?php

namespace Haxibiao\Wallet;

use Haxibiao\Wallet\Traits\RechargeAttrs;
use Haxibiao\Wallet\Traits\RechargeRepo;
use Haxibiao\Wallet\Traits\RechargeResolvers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recharge extends Model
{

    use RechargeRepo, RechargeResolvers, RechargeAttrs;

    protected $guarded = [];

    /**
     * 充值成功 1
     * 充值失败 -1
     * 等待支付 0（默认值）
     */
    public const RECHARGE_SUCCESS = 1;
    public const RECHARGE_FAIL    = -1;
    public const WATING_PAY       = 0;

    /**
     * 交易平台
     */
    public const ALIPAY_PLATFORM = 'alipay';
    public const WECHAT_PLATFORM = 'wechat';

    protected $casts = [
        'data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getPayStatuses()
    {
        return [
            Recharge::RECHARGE_SUCCESS => '充值成功',
            Recharge::RECHARGE_FAIL    => '等待支付',
            Recharge::WATING_PAY       => '充值失败',
        ];
    }

    public static function getPayPlatfroms()
    {
        return [
            Recharge::ALIPAY_PLATFORM => '支付宝',
            Recharge::WECHAT_PLATFORM => '微信',
        ];
    }

}
