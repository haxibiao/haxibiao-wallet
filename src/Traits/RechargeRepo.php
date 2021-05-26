<?php

namespace Haxibiao\Wallet\Traits;

use Haxibiao\Wallet\Recharge;
use Haxibiao\Wallet\Transaction;
use Illuminate\Support\Facades\DB;
use Yansongda\Pay\Log;

trait RechargeRepo
{
    /**
     * 创建支付交易
     */
    public static function createRecharge($user_id, $trade_no, $title, $amount, $platform)
    {
        return Recharge::create([
            'user_id'  => $user_id,
            'trade_no' => $trade_no,
            'title'    => $title,
            'amount'   => $amount,
            'platform' => $platform,
        ]);
    }

    /**
     * 完成支付交易
     */
    public static function completeRecharge($trade_no, $platform, $amount, $platfrom_data)
    {
        $recharge = Recharge::whereTradeNo($trade_no)->first();
        // 不知名充值订单
        if (empty($recharge)) {
            Log::info("{$platform} 未知充值订单", func_get_args());
            return;
        }
        // 有效充值订单
        if ($recharge->status == Recharge::WATING_PAY) {
            $user = $recharge->user;

            //充值成功后的逻辑
            if (env("APP_NAME") == 'dazhuan_game') {
                //答题赚钱小游戏，充值1块钱恢复120体力
                $user->update(['ticket' => DB::raw('ticket +' . $amount)]);
            } else if (env("APP_NAME") == 'chutizhuanqian_ios') {
                //体力全恢复
                if ($amount == -1) {
                    $user->ticket = $user->level->ticket_max;
                    $user->save();
                }
            } else {
                Transaction::makeIncome($user->wallet, $amount, $platform . '充值', '充值', '已到账');
            }

            // 更新充值记录状态，保存交易平台回调数据
            $recharge->status = Recharge::RECHARGE_SUCCESS;
            $recharge->data   = $platfrom_data;
            $recharge->save();
            return $recharge;
        }
    }

    /**
     * 苹果商品表，ios支付特有
     */
    public static function appleProductMap()
    {
        return [
            'com.bianxiandaxue_01' => [
                'name'   => '6学币',
                'amount' => 6,
            ],
            'com.bianxiandaxue_10' => [
                'name'   => '68学币',
                'amount' => 68,
            ],
            'com.bianxiandaxue_28' => [
                'name'   => '188学币',
                'amount' => 188,
            ],
            '123321'               => [
                'name'   => '120体力值',
                'amount' => 120,
            ],
            'com.tiantianchuti_01' => [
                'name'   => '全体力恢复',
                'amount' => -1,
            ],
            'com.tiantianchuti_03' => [
                'name'   => '全体力恢复',
                'amount' => -1,
            ],
        ];
    }
}
