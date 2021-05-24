<?php

namespace Haxibiao\Wallet;

class LuckyWithdraw extends Withdraw
{
    protected $table = 'withdraws';

    public static function canWithdraw($user, $amount, $platform, $type)
    {
        //取出默认唯一的钱包(确保不空) && 检查钱包绑定
        $wallet = $user->wallet;
        self::checkWalletBind($wallet, $platform);

        return true;
    }
}
