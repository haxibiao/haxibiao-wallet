<?php

namespace Haxibiao\Wallet\Traits;

use Haxibiao\Wallet\BanUser;
use Haxibiao\Wallet\Gold;

trait GoldRepo
{

    /**
     *  检查账单记录异常用户，今日答题数不正常
     */
    public static function detectBadUser($user)
    {
        $date = today();

        if ($user->profile->answers_count_today > 1000) {
            $reason = "异常日期: {$date->toDateString()} 今日答题数超过1000不正常";
            BanUser::record($user, $reason, false);
        }
    }

    public static function resetGold($user, $remark)
    {
        $gold     = (-1 * $user->gold);
        $goldItem = Gold::create([
            'user_id' => $user->id,
            'gold'    => $gold,
            'remark'  => $remark,
            'balance' => 0,
        ]);

        //确保账户和gold记录余额同步，observer有不触发的情况
        $user->update(['gold' => $goldItem->balance]);
        //检测异常用户
        Gold::detectBadUser($user);
        return $goldItem;
    }

    public static function makeOutcome($user, $gold, $remark)
    {
        $balance  = $user->gold - $gold;
        $goldItem = Gold::create([
            'user_id' => $user->id,
            'gold'    => -$gold,
            'balance' => $balance,
            'remark'  => $remark,
        ]);

        //确保账户和gold记录余额同步，observer有不触发的情况
        $user->update(['gold' => $goldItem->balance]);
        //检测异常用户
        Gold::detectBadUser($user);
        return $goldItem;
    }

    public static function makeIncome($user, $gold, $remark)
    {
        $balance  = $user->gold + $gold;
        $goldItem = Gold::create([
            'user_id' => $user->id,
            'gold'    => $gold,
            'balance' => $balance,
            'remark'  => $remark,
        ]);

        //确保账户和gold记录余额同步，observer有不触发的情况
        $user->update(['gold' => $goldItem->balance]);
        //检测异常用户
        Gold::detectBadUser($user);
        return $goldItem;
    }
}
