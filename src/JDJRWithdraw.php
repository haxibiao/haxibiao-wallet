<?php
namespace Haxibiao\Wallet;

use Haxibiao\Breeze\Exceptions\ErrorCode;
use Haxibiao\Breeze\Exceptions\UserException;
use Haxibiao\Wallet\JDJR;
use Haxibiao\Wallet\Withdraw;

class JDJRWithdraw extends Withdraw
{
    protected $table = 'withdraws';

    public function process()
    {
        // 京东金融需要通过 web hook 来确定提现状态,暂时不需要在此处理
        return $this;
    }

    public static function canWithdraw($user, $amount, $platform, $type)
    {
        //取出默认唯一的钱包(确保不空) && 检查钱包绑定
        $wallet = $user->wallet;
        self::checkWalletBind($wallet, $platform);

        // 检查提现去第三方平台
        return self::checkWithdrawPlatform($user, $platform);
    }

    public static function checkWithdrawPlatform($user, $platform)
    {
        // 京东金融
        $jdjr = JDJR::init($user->id, $user->account);
        throw_if(is_null($jdjr), UserException::class, '提现失败,请先绑定手机号!');

        if (!$jdjr->isNewUser()) {
            $withdrawCount = Withdraw::withdrawCount($user->id, $platform);
            $errorMsg      = $withdrawCount > 0 ? '提现失败,该平台只可提现一次!' : '提现失败,此为京东未注册新用户提现!';
            $errorCode     = $withdrawCount > 0 ? ErrorCode::JDJR_SYSTEM_HAS_WITHDRAW : ErrorCode::JDJR_SYSTEM_REGISTERED_USER;
            throw_if(true, UserException::class, $errorMsg, $errorCode);
        }
    }
}
