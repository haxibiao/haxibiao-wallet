<?php

namespace Haxibiao\Wallet\Traits;

use Haxibiao\Wallet\BanUser;
use Haxibiao\Wallet\Gold;

trait GoldRepo
{

    /**
     *  检查账单记录异常用户
     */
    public static function detectBadUser($gold)
    {
        if (config('app.name') == "datizhuanqian") {
            $user = $gold->user;
            if ($user) {
                $today_gold = $user->golds()->where('created_at', '>=', today())->sum('gold') ?? 0;
                if ($today_gold >= 6000) {
                    $reason = "异常日期:" . now() . "日单日智慧点获得数大于600";
                    BanUser::record($user, $reason);
                }
                if ($gold->remark == "视频观看奖励" && !$user->isDisable) {
                    //检查距离上一次记录的时间间隔
                    $pre_data = $user->golds()
                        ->where('created_at', '>=', today())
                        ->where('remark', '视频观看奖励')
                        ->latest('id')
                        ->first();

                    if ($pre_data) {
                        //如果两次获得贡献相差 xxs
                        $diffSecond = $pre_data->created_at->diffInSeconds(now());
                        if ($diffSecond < 29) {
                            $reason = "异常日期: " . now() . "，两次获得智慧点时间相差：{$diffSecond} 秒";
                            BanUser::record($user, $reason);
                        }
                    }
                }
            }
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
            'user_id'   => $user->id,
            'wallet_id' => $user->goldWallet->id,
            'gold'      => -$gold,
            'balance'   => $balance,
            'remark'    => $remark,
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
            'user_id'   => $user->id,
            'wallet_id' => $user->goldWallet->id,
            'gold'      => $gold,
            'balance'   => $balance,
            'remark'    => $remark,
        ]);

        //确保账户和gold记录余额同步，observer有不触发的情况
        $user->update(['gold' => $goldItem->balance]);
        //检测异常用户
        Gold::detectBadUser($user);
        return $goldItem;
    }
}
