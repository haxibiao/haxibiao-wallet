<?php

namespace Haxibiao\Wallet;

use Haxibiao\Wallet\Strategies\Pay\WithdrawStrategyMaker;
use Haxibiao\Wallet\Withdraw;

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

    protected function makingTransfer()
    {
        $user                                    = $this->user;
        $platform                                = $this->to_platform;
        list($siteName, $siteDomain, $isOurSite) = Withdraw::getPlatformInfo($platform);
        if (!$isOurSite) {
            // 支付宝、微信、QQ等提现策略业务参数
            $transferPaymentInfo = [
                'systemBizNo' => $this->biz_no,
                'amount'      => $this->amount,
                'pay_id'      => $this->to_account,
                'real_name'   => data_get($user->wallet, 'real_name', ''),
                'remark'      => sprintf('【%s】提现', config('app.name_cn')),
            ];
        } else {
            // 内部站点服务群:答妹、懂得赚提现策略业务参数
            $transferPaymentInfo = [
                'uuid'                    => $user->uuid,
                'transfer_to_domain'      => $siteDomain,
                'transfer_to_site_userid' => $this->to_account,
                'system_userid'           => $this->user_id,
                'amount'                  => $this->amount,
            ];
        }

        $strategy = $platform == 'qq' ? 'QPay' : ($isOurSite ? 'HashSitePay' : $platform);
        return WithdrawStrategyMaker::setStrategy($strategy)->transfer($transferPaymentInfo);
    }
}
