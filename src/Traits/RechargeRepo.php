<?php

namespace Haxibiao\Wallet\Traits;

use App\Transaction;
use Haxibiao\Wallet\Recharge;
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
        }
        // 有效充值订单
        if ($recharge->status == Recharge::WATING_PAY) {
            $user = $recharge->user;
            // 充值到账户
            Transaction::makeIncome($user->wallet, $amount, $platform . '充值', '充值', '已到账');
            // 更新充值记录状态，保存交易平台回调数据
            $recharge->status = Recharge::RECHARGE_SUCCESS;
            $recharge->data   = $platfrom_data;
            $recharge->save();
            return $recharge;
        }
    }
}
