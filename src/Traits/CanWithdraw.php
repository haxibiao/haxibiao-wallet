<?php

namespace Haxibiao\Wallet\Traits;

use App\User;
use Haxibiao\Breeze\Exceptions\ErrorCode;
use Haxibiao\Breeze\Exceptions\UserException;
use Haxibiao\Breeze\UserProfile;
use Haxibiao\Task\Contribute;
use Haxibiao\Wallet\InvitationWithdraw;
use Haxibiao\Wallet\JDJRWithdraw;
use Haxibiao\Wallet\LuckyWithdraw;
use Haxibiao\Wallet\Withdraw;

trait CanWithdraw
{
    public static function canWithdraw($user, $amount, $platform, $type)
    {
        if ($platform == Withdraw::JDJR_PLATFORM) {
            return JDJRWithdraw::canWithdraw($user, $amount, $platform, $type);
        }

        if ($type == Withdraw::LUCKYDRAW_TYPE) {
            return LuckyWithdraw::canWithdraw($user, $amount, $platform, $type);
        }

        if ($type == Withdraw::INVITE_ACTIVITY_TYPE) {
            return InvitationWithdraw::canWithdraw($user, $amount, $platform, $type);
        }

        return Withdraw::checkBaseCondition($user, $amount, $platform, $type);
    }

    public static function checkBaseCondition($user, $amount, $platform, $type)
    {
        //取出默认唯一的钱包(确保不空) && 检查钱包绑定
        $wallet = $user->wallet;
        Withdraw::checkWalletBind($wallet, $platform);
        $canWithdraw = Withdraw::isWhiteListMemeber($user->id);
        if (!$canWithdraw) {
            //检查提现版本
            Withdraw::checkWithdrawVersion();
            //检查提现开放时间
            Withdraw::checkWithdrawTime();
            // 检查时间和限量抢限流控制并发(邀请活动提现,不受此限制)
            Withdraw::checkHighWithdraw($user, $amount);
            Withdraw::checkWithdrawType($type, $platform);
            // 检查刷子 && 账户异常 && 提示刷子用户,暂时只能提现到答妹
            Withdraw::checkShuaZi($user, $platform, $type);
            // 检查提现次数
            Withdraw::checkWithdrawCount($user, $wallet);
            // 先答10道题
            Withdraw::checkAnswerCountsToday($user, $amount);
            //预防新用户快速请求提现，和一日重复提现
            // Withdraw::checkLastWithdrawTime($wallet);
            //检查账户禁用和禁言
            $user->checkRules();
            // 检查提现金额和等级
            Withdraw::checkWithdrawAmountAndLevel($user, $amount, $platform, $type);
            //检查提现贡献点
            Withdraw::checkWithdrawContribute($user, $amount, $type);

            $canWithdraw = true;
        }

        return $canWithdraw;
    }

    public static function checkWithdrawVersion($version = null)
    {
        $version = $version ?: getAppVersion();
        if (!is_local_env()) {
            throw_if($version < '3.0.3', UserException::class, '版本过低,请升级最新版本提现!', ErrorCode::VERSION_TOO_LOW);
        }
    }

    public static function checkWithdrawTime()
    {
        $hour = now()->hour;
        if (($hour < 10 || $hour >= 23)) {
            throw new UserException('提现时间段：10:00-23:00');
        }
    }

    public static function checkWithdrawCount($user, $wallet)
    {
        //成功提现次数限制
        if (!is_testing_env()) {
            // throw_if($user->withdraw_at && $user->withdraw_at > today(), UserException::class, '今日提次数已达上限!');
            throw_if($wallet->getAvaliableWithdrawCountOfToday() < 1, UserException::class, '今日提次数已达上限!');
        }
    }

    public static function checkWithdrawStatus($wallet, $amount)
    {
        //待处理中
        $withdraw = WithDraw::where('wallet_id', $wallet->id)->where('amount', $amount)->latest('id')->first();
        throw_if($withdraw->status == WithDraw::WATING_STATUS, UserException::class, '提现正在处理中，请耐心等待，如有疑问请提交反馈哦');
    }

    // 风控手段: 限制允许发起限量抢提现的时间
    public static function checkHighWithdraw($user, $amount)
    {
        //高额提现
        if ($amount > 0.5) {
            $hour   = now()->hour;
            $minute = now()->minute;

            //不限制：白名单(开发,测试,hotfix才用)
            if (is_prod_env()) {
                if (in_array($user->id, Withdraw::getUserIdWhiteList())) {
                    return;
                }
            }

            //新注册3小时内的用户不能高额提现，防止撸毛
            if (!now()->diffInHours($user->created_at) >= 3) {
                throw new UserException('当前限量抢额度已被抢光了,下个时段再试吧');
            }

            // 老用户 工作时间才可以提现
            if (($hour < 10 || $hour >= 18 || $minute >= 10)) {
                throw new UserException('限量抢时间段：10:00-18:00，请在每个小时开始的0-10分钟内开抢哦');
            }

            //提现额度逻辑因为邀请下线，已弃用... 改为限制限量抢额度
            //每人默认最高10元限量抢额度，新版本开放提额玩法,先简单防止老刷子账户疯狂并发提现...
            $withdrawLines = $user->withdraw_lines;
            if ($withdrawLines < $amount) {
                throw new UserException('限量抢额度已被抢光了,下个时段再试吧');
            }

            /**
             * 限流:
             * 每时段前10分钟，比如10:00 - 10:10 限制流量,避免DB SERVER 负载压力100%
             * 限制几率 95%
             * 时间超出过,恢复正常!
             */
            if ($minute < 10) {
                $rand = mt_rand(1, 100);
                throw_if($rand <= 95, UserException::class, '目前人数过多,请您下个时段(' . ($hour + 1) . '点)再试!');
            }

        }
    }

    public static function checkAnswerCountsToday($user, $amount)
    {
        $profile         = UserProfile::firstOrCreate(['user_id' => $user->id]);
        $todayAnswerSum  = $profile->answers_count_today + $user->today_user_category_sum_answer;
        $needCountAnswer = 10 - $todayAnswerSum;
        if (!is_testing_env()) {
            if (!$user->isWhiteListUser) {
                throw_if($needCountAnswer > 0, UserException::class, "请去先{$needCountAnswer}答题道题热热身");
            }
        }
    }

    //预防新用户快速请求, 预防一日重复提现
    public static function checkLastWithdrawTime($wallet)
    {
        $lastWithdraw = $wallet->lastWithdraw()->select(['created_at'])->first();
        if ($lastWithdraw && $lastWithdraw->created_at) {
            //测试UT时无需卡5秒
            if (!is_testing_env()) {
                $validSecond    = 5 - now()->diffInSeconds($lastWithdraw->created_at);
                $canNotWithdraw = now() > $lastWithdraw->created_at && $validSecond < 0;
                if (!$canNotWithdraw) {
                    throw new UserException(sprintf('您的提现速度过快,请%s秒后再试!', $validSecond));
                }
            }

            if ($lastWithdraw->created_at > today()) {
                throw new UserException("您今日已提交过提现请求");
            }
        }

        return true;
    }

    public static function checkWalletBind($wallet, $platform)
    {
        $errorCode = ErrorCode::USER_NO_WALLET;
        throw_if(is_null($wallet), UserException::class, '您还没有绑定支付宝或微信哦!快去绑定吧!', $errorCode);
        $payId = $wallet->getPayId($platform);
        switch ($platform) {
            case Withdraw::ALIPAY_PLATFORM:
                $brand     = '支付宝';
                $errorCode = ErrorCode::USER_NO_BIND_ALIPAY;
                break;
            case Withdraw::WECHAT_PLATFORM:
                $brand     = '微信';
                $errorCode = ErrorCode::USER_NO_BIND_WECHAT;
                break;
            case Withdraw::DDZ_PLATFORM:
                $brand     = '懂得赚';
                $errorCode = ErrorCode::USER_NO_BIND_DDZ;
                break;
            case Withdraw::DM_PLATFORM:
                $brand     = '小答妹';
                $errorCode = ErrorCode::USER_NO_BIND_DM;
                break;
            case Withdraw::QQ_PLATFORM:
                $brand     = 'QQ';
                $errorCode = ErrorCode::USER_NO_BIND_DM;
                break;
            default:
                $brand = '';
                break;
        }
        $errorMsg = sprintf('%s提现信息未绑定!', $brand);

        throw_if(empty($payId), UserException::class, $errorMsg, $errorCode);
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

        $isActivityWithdraw = $type == Withdraw::INVITE_ACTIVITY_TYPE;
        //高额度政策（1元以上都算，目前日提0.5了）,邀请活动提现用户不受此限制
        if ($amount >= 1 && !$isActivityWithdraw) {
            //高额度等级小于3
            throw_if($user->level_id < 3, UserException::class, '限量抢提现需要3级以上!');
            //限制总额度3000元
            Withdraw::checkTodayWithdrawAmount($amount);
            //5元提现
            throw_if($amount >= 5 && $user->level_id < 5, UserException::class, '等级不足,5级以上用户才可发起!', ErrorCode::LEVEL_NOT_ENOUGH);
            //10元提现
            throw_if($amount >= 10 && $user->level_id < 10, UserException::class, '等级不足,10级以上用户才可发起!', ErrorCode::LEVEL_NOT_ENOUGH);
            //当天注册,禁止提现3元以上
            throw_if($user->created_at >= today(), UserException::class, '今日提现已达上限!');
        }
        throw_if($platform == Withdraw::JDJR_PLATFORM && $amount != 0.3, UserException::class, '提现失败,该平台只可提现0.3元!');

    }

    public static function checkWithdrawContribute($user, $amount, $type)
    {
        //贡献点检查
        $isRandomWithdraw   = $type == Withdraw::RANDOM_TYPE;
        $isActivityWithdraw = $type == Withdraw::INVITE_ACTIVITY_TYPE;

        //提现成功0.3元以上的，不再无门槛
        if ($user->successWithdrawAmount >= 0.3) {

            if ($isRandomWithdraw) {
                //随机提现固定36贡献
                $needContributes = Contribute::RANDOM_WITHDRAW_CONTRIBUTE;
            } else if ($isActivityWithdraw) {
                // 首次邀请奖励提现1元需要36个贡献点
                $needContributes = $amount > 1 ? 0 : 36;
            } else {
                $needContributes = User::getAmountNeedDayContributes($amount);
            }
            $leftContributes = $needContributes - $user->today_contributes;
            //无法完成该额度提现，提示需要的贡献值
            if ($leftContributes > 0) {
                //FIXME: ios上架临时处理
                if (isAndroidApp()) {
                    $remark = sprintf('还差%s日贡献, 您可下载新APP答妹，新人提现无门槛', $leftContributes);
                    throw new UserException($remark);
                }
            }
        }
    }

    public static function checkTodayWithdrawAmount($amount)
    {
        //总额（总提现金额，含队列内未成功的）
        $todayWithdrawAmount = Withdraw::today()->sum('amount');
        if ($todayWithdrawAmount >= Withdraw::MAX_WITHDRAW_SUM_AMOUNT) {
            throw new UserException('今日提现总名额已用完,请明日10点再来哦');
        }

        //控制额度上限
        $todayAmountGroup = Withdraw::selectRaw('amount,count(*) as count')->today()->groupBy('amount')->get();
        foreach ($todayAmountGroup as $todayAmount) {
            if ($amount == $todayAmount->amount) {
                if ($todayAmount->amount == 3 && $todayAmount->count >= 30) {
                    throw new UserException('3元限量额度已抢完,请提现其他额度哦!');
                }

                if ($todayAmount->amount == 5 && $todayAmount->count >= 10) {
                    throw new UserException('5元限量额度已抢完,请提现其他额度哦!');
                }

                if ($todayAmount->amount == 10 && $todayAmount->count >= 5) {
                    throw new UserException('10元限量额度已抢完,请提现其他额度哦!');
                }
            }
        }
    }

    public static function checkWithdrawType($type, $platform)
    {
        throw_if($type == Withdraw::RANDOM_TYPE && $platform != Withdraw::ALIPAY_PLATFORM, UserException::class, '随机提现,仅支持支付宝哦!');
    }

    public static function checkShuaZi(User $user, $platform, $type)
    {
        //只有老用户才能提现到答赚
        throw_if($user->success_withdraw_amount <= 20 && $platform == Withdraw::DM_PLATFORM, UserException::class, '新用户暂不支持提现到答妹哦！');

        throw_if($user->isShuaZi && $user->account != User::DEFAULT_TEST_ACCOUNT, UserException::class, '账户异常,请联系官方QQ群735220029');

        // $isRandomWithdraw   = $type == Withdraw::RANDOM_TYPE;
        // $isActivityWithdraw = $type == Withdraw::INVITE_ACTIVITY_TYPE;
        // $wallet             = $user->wallet;
        // // 提示刷子用户,暂时只能提现到答妹
        // if ($wallet->total_withdraw_amount >= 20 && !is_local_env() && !$isRandomWithdraw) {

        //     //hotfix环境还是允许白名单测试吧,不然上线新版本不太方便操作
        //     if (is_hotfix_env() && Withdraw::isWhiteListMemeber($user->id)) {
        //         return;
        //     }

        //     if ($platform != Withdraw::DM_PLATFORM) {
        //         //华为 或 安卓10 提现到旗下平台需要先绑定手机号
        //         if (isHuawei() || isAndroid10()) {
        //             $bindPhone = is_phone_number($user->account) || is_phone_number($user->phone);
        //             if (!$bindPhone || empty($user->name) || $user->name == User::DEFAULT_USER_NAME) {
        //                 throw new UserException('请先绑定手机号和更新昵称');
        //             }

        //         }

        //         if (!$isActivityWithdraw) {
        //             throw new UserException('此额度为新人专享福利，您可以提现到答妹，领更多奖励');
        //         }
        //     }
        // }
    }

}
