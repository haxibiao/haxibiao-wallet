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
            if ($user && !$user->isDisable) {
                $today_gold = $user->golds()->where('created_at', '>=', today())->sum('gold') ?? 0;
                if ($today_gold >= 6000) {
                    $reason = "异常日期:" . now() . "日单日智慧点获得数大于6000";
                    BanUser::record($user, $reason);
                }
                if ($gold->remark == "视频观看奖励") {
                    $second = 10;
                    //检查距离上两次记录的时间间隔
                    $pre_datas = $user->golds()
                        ->where('remark', '视频观看奖励')
                        ->latest('id')
                        ->take(3)
                        ->get();

                    if (count($pre_datas) > 2) {
                        //如果两次获得贡献相差 xxs
                        $diffSecond1 = $pre_datas[0]->created_at->diffInSeconds($pre_datas[1]->created_at);
                        $diffSecond2 = $pre_datas[1]->created_at->diffInSeconds($pre_datas[2]->created_at);
                        if ($diffSecond1 <= $second && $diffSecond2 <= $second) {
                            //封禁用户
                            $reason = "异常日期: " . now() . "，连续两次获得智慧点时间相差小于：{$second} 秒";
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
