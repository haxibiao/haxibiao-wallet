<?php

namespace Haxibiao\Wallet;

use Haxibiao\Breeze\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gold extends Model
{
    protected $fillable = [
        'user_id',
        'gold',
        'wallet_id',
        'balance',
        'remark',
        'created_at',
        'updated_at',
    ];

//    点击 DRAW 广告的金币额度,
    //    const DRAW_GOLD_AMOUNT = 0;

    //    点击 激励视频 广告的金币额度
    const REWARD_VIDEO_GOLD = 7;

    const REWARD_GOLD   = 10;
    const NEW_USER_GOLD = 30;

    //当日通过观看视频获取的最高奖励
    const TODAY_VIDEO_PLAY_MAX_GOLD = 50;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    //repo
    public static function makeOutcome($user, $gold, $remark)
    {

        $goldBalance = $user->gold - $gold;
        $gold        = Gold::create([
            'user_id'   => $user->id,
            'wallet_id' => $user->goldWallet->id,
            'gold'      => -$gold,
            'balance'   => $goldBalance,
            'remark'    => $remark,
        ]);
        //更新user表上的冗余字段
        User::where('id', $user->id)->update(['gold' => $goldBalance]);

        return $gold;
    }

    public static function makeIncome($user, $gold, $remark)
    {
        $goldBalance = $user->gold + $gold;
        $gold        = Gold::create([
            'user_id'   => $user->id,
            'wallet_id' => $user->goldWallet->id,
            'gold'      => $gold,
            'balance'   => $goldBalance,
            'remark'    => $remark,
        ]);
        //更新user表上的冗余字段
        User::where('id', $user->id)->update(['gold' => $goldBalance]);

        return $gold;
    }

    public function resolveGolds($rootValue, array $args, $context, $resolveInfo)
    {
        app_track_event('用户', "查看账单");
        return Gold::orderBy('id', 'desc')->where('user_id', $args['user_id']);
    }
}