<?php

namespace Haxibiao\Wallet;

use Haxibiao\WalletInvitation\LuckyDrawAttrs;
use Haxibiao\WalletInvitation\LuckyDrawRepo;
use Haxibiao\WalletInvitation\LuckyDrawResolvers;
use Illuminate\Database\Eloquent\Model;

class LuckyDraw extends Model
{
    use LuckyDrawResolvers;
    use LuckyDrawRepo;
    use LuckyDrawAttrs;
    public $fillable = [
        'user_id',
        'amount',
        'status',
        'created_at',
        'updated_at',
    ];

    //提现奖励档位
    const REWARD_AMOUNT = [1, 5, 10, 20];
    //奖励名额
    const REWARD_COUNT = 30 * 3;
    //报名限制
    const JOIN_ANSWER_COUNT = 20; //答题20
    const JOIN_DRAW_COUNT   = 2; //看有趣小视频2
    //提现状态
    const STATUS_WAIT = 0; //已报名
    const STATUS_WIN  = 1; //已中奖
    const STATUS_FAIL = -1; //未中奖

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }
}
