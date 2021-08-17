<?php

namespace Haxibiao\Wallet;

use App\UserStageInvitation;
use Haxibiao\Wallet\Strategies\Pay\WithdrawStrategyMaker;

class InvitationWithdraw extends Withdraw
{
    protected $table = 'withdraws';

    public function refund($forceRefund = false): bool
    {
        $isRefundSuccess = false;
        if ($this->isFailed() || $forceRefund) {
            $user   = $this->user;
            $wallet = $this->wallet;
            $amount = $this->amount;
            if (!is_null($wallet) && !is_null($user)) {
                // 邀请钱包采取 退款
                $wallet->makeIncome($amount, $this, '提现失败退款');
                $isRefundSuccess = true;
            }
        }

        return $isRefundSuccess;
    }

    public static function canWithdraw($user, $amount, $platform, $type)
    {
        //取出默认唯一的钱包(确保不空) && 检查钱包绑定
        $wallet = $user->wallet;
        self::checkWalletBind($wallet, $platform);
        $canWithdraw = !Withdraw::isWhiteListMemeber($user->id);
        if (!$canWithdraw) {
            //检查提现版本
            self::checkWithdrawVersion();
            //检查提现开放时间
            self::checkWithdrawTime();
            self::checkWithdrawType($type, $platform);
            // 检查提现平台
            self::checkWithdrawPlatform($user, $platform);
            // 检查刷子 && 账户异常 && 提示刷子用户,暂时只能提现到答妹
            self::checkShuaZi($user, $platform, $type);
            // 检查提现次数
            self::checkWithdrawCount($user, $wallet);
            //检查提现状态
            self::checkWithdrawStatus($wallet, $amount);
            // 先答10道题
            self::checkAnswerCountsToday($user, $amount);
            //预防新用户快速请求提现，和一日重复提现
            // self::checkLastWithdrawTime($wallet);
            //检查账户禁用和禁言
            $user->checkRules();
            // 检查提现金额和等级
            self::checkWithdrawAmountAndLevel($user, $amount, $platform, $type);
            //检查提现贡献点
            self::checkWithdrawContribute($user, $amount, $type);

            $canWithdraw = true;
        }

        return $canWithdraw;
    }

    public static function checkWithdrawAmountAndLevel($user, $amount, $platform, $type)
    {
        $isRandomWithdraw = $type == Withdraw::RANDOM_TYPE;
        // 非随机提现
        if (!$isRandomWithdraw) {
            //提现允许范围
            throw_if(!Withdraw::isEffectiveAmount($amount, $type), UserException::class, '金额选择错误,请选择有效金额!');

            //小额提现
            if ($amount < 1) {
                throw_if($amount == 0.1 && $platform != Withdraw::ALIPAY_PLATFORM, UserException::class, '0.1元只可提现至支付宝!');
                throw_if($amount == 0.3 && $platform == Withdraw::DM_PLATFORM, UserException::class, '答妹暂不支持提现0.3元，请选择微信或支付宝提现哦！');
            }
        }

        // 邀请活动提现,需要检查当前提现的阶段,根据阶段判断金额,防止刷接口.
        $userInvitationStage = UserStageInvitation::findOrCreate($user->id);
        $invitationWallet    = $userInvitationStage->wallet;

        if (!is_null($invitationWallet)) {
            throw_if($invitationWallet->total_withdraw_amount >= $amount, UserException::class, '提现失败,您的提现阶段已超出该金额!');
        }
    }

    public static function checkWithdrawPlatform($user, $platform)
    {
        throw_if(!in_array($platform, [Withdraw::ALIPAY_PLATFORM, Withdraw::WECHAT_PLATFORM]), UserException::class, '提现失败,邀请活动只支持提现至微信或支付宝账户中!');
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
