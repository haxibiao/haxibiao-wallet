<?php

namespace Haxibiao\Wallet;

use App\OAuth;
use App\Withdraw;
use Haxibiao\Wallet\Strategies\Pay\PayStrategyMaker;

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

            // FIXME:微信授权因为混串app_id使用,所以每次支付需要携带appid进去支付.
            if ($this->platformIs(Withdraw::WECHAT_PLATFORM)) {
                $appId = data_get(OAuth::with('appId')
                        ->ofType(OAuth::WECHAT_TYPE)
                        ->where('oauth_id', $this->to_account)
                        ->select('app_id')
                        ->first(), 'appId.value');
                if (!empty($appId)) {
                    $transferPaymentInfo['appid'] = $appId;
                }
            }
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
        return PayStrategyMaker::setStrategy($strategy)->transfer($transferPaymentInfo);
    }
}
