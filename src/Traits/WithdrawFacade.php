<?php

namespace Haxibiao\Wallet\Traits;

use App\User;
use Haxibiao\Breeze\Exceptions\GQLException;
use Haxibiao\Breeze\OAuth;
use Haxibiao\Wallet\Exchange;
use Haxibiao\Wallet\Jobs\ProcessWithdraw;
use Haxibiao\Wallet\Wallet;
use Haxibiao\Wallet\Withdraw;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

trait WithdrawFacade
{
    /**
     * 提现记录
     */
    public static function listWithdraws($offset, $limit)
    {
        $user      = getUser();
        $wallet    = $user->wallet;
        $withdraws = [];

        if (!is_null($wallet)) {
            $withdraws = Withdraw::getWithdraws($wallet, $offset, $limit);
        }
        return $withdraws;
    }

    /**
     * 从某个钱包提现
     */
    public static function createWithdrawWithWallet($wallet, $amount, $platform, $type = Withdraw::FIXED_TYPE)
    {
        return Withdraw::create([
            'user_id'     => $wallet->user_id,
            'wallet_id'   => $wallet->id,
            'amount'      => $amount,
            // fix:后面需要优化掉这个梗
            'to_account'  => $wallet->getPayId($platform) ?: $wallet->user->wallet->getPayId($platform),
            'host'        => gethostname(),
            'to_platform' => $platform,
            'type'        => $type,
        ]);
    }

    public static function checkWalletInfo($wallet, $platform)
    {
        throw_if(empty($wallet), GQLException::class, '您还没有绑定支付宝或微信哦!快去绑定吧!');

        $payId = $wallet->getPayId($platform);

        switch ($platform) {
            case Withdraw::ALIPAY_PLATFORM:
                $brand = '支付宝';
                break;
            case Withdraw::WECHAT_PLATFORM:
                $brand = '微信';
                break;
            default:
                $brand = '';
                break;
        }
        $errorMsg = sprintf('%s提现信息未绑定!', $brand);

        throw_if(empty($payId), GQLException::class, $errorMsg);
    }

    public static function getAllowAmount()
    {
        return [0.1, 0.3, 0.5, 0.7, 1, 3, 5, 10];
    }

    /**
     * 主要提现接口
     */
    public static function createWithdraw($user, $amount, $platform, $type)
    {
        // 所有提现逻辑检测逻辑,已全部整合到CanWithdraw Trait中
        Withdraw::canWithdraw($user, $amount, $platform, $type);
        if (in_array($platform, Withdraw::OUR_SITE)) {
            //提现去自己旗下其他平台
            list($siteName, $siteDomain) = Withdraw::getPlatformInfo($platform);
            //授权账户关联
            OAuth::bindSite($user, $platform, $siteDomain, $siteName);
        }

        $isFixedWithdraw  = $type == Withdraw::FIXED_TYPE;
        $isRandomWithdraw = $type == Withdraw::RANDOM_TYPE;
        // 提现提现,随机抽取金额
        if ($isRandomWithdraw) {
            $amount = Withdraw::randomAmount($user);
        }

        // 动态获取:正常提现取RMB钱包,邀请活动取邀请活动钱包
        $wallet   = $user->withdrawWallet($type);
        $withdraw = $wallet->createWithdraw($amount, $platform, $type);
        //这里更新 省了3次SQL操作
        $user->withdrawAt();

        //限量抢成功了，扣除限量抢额度
        if ($amount > 0.5 && $isFixedWithdraw) {
            $decrement = $amount > $user->withdraw_lines ? $user->withdraw_lines : $amount;
            $user->decrement('withdraw_lines', $decrement);
            //加入延时1小时提现队列
            dispatch(new ProcessWithdraw($withdraw))->delay(now()->addMinutes(rand(50, 60))); //不再手快者得
        } else {
            //加入秒提现队列
            dispatch(new ProcessWithdraw($withdraw));
        }

        return $withdraw;
    }

    public static function getWithdraws($wallet, $offset = 0, $limit = 10)
    {
        return $wallet->withdraws()->skip($offset)
            ->take($limit)
            ->orderByDesc('id')
            ->get();
    }

    public static function getPlatformInfo($platform)
    {
        $siteName   = '答妹';
        $siteDomain = 'xiaodamei.com';

        $isOurSite = false;
        switch ($platform) {
            case Withdraw::DDZ_PLATFORM:
                $siteName   = '懂得赚';
                $siteDomain = 'dongdezhuan.com';
                $isOurSite  = true;
                break;
            case Withdraw::DM_PLATFORM:
                $siteName   = '答妹';
                $siteDomain = 'xiaodamei.com';
                $isOurSite  = true;
                break;
        }

        return array($siteName, $siteDomain, $isOurSite);
    }

    public static function withdrawCount($userId, $platform = '')
    {
        $withdraw    = new Withdraw;
        $queryResult = $withdraw->select(DB::raw('count(1) as withdraw_count'))->whereExists(function ($query) use ($userId, $withdraw, $platform) {
            $walleTable    = (new Wallet)->getTable();
            $withdrawTable = $withdraw->getTable();
            $query->select(DB::raw('1'))
                ->from($walleTable)
                ->where('user_id', $userId)
                ->where('type', Wallet::RMB_TYPE)
                ->where($withdrawTable . '.wallet_id', DB::raw($walleTable . '.id'));
            // 指定平台查询的话
            if (!empty($platform)) {
                $query->where('to_platform', $platform);
            }
        })->first();

        return $queryResult->withdraw_count;
    }

    public static function successWithdrawCount($userId)
    {
        $withdraw    = new Withdraw;
        $queryResult = $withdraw->select(DB::raw('count(1) as withdraw_count'))->whereExists(function ($query) use ($userId, $withdraw) {
            $walleTable    = (new Wallet)->getTable();
            $withdrawTable = $withdraw->getTable();
            $query->select(DB::raw('1'))
                ->from($walleTable)
                ->where('user_id', $userId)
                ->where('type', Wallet::RMB_TYPE)
                ->where($withdrawTable . '.wallet_id', DB::raw($walleTable . '.id'));
        })->success()->first();

        return $queryResult->withdraw_count;
    }

    public static function successWithdrawCountWithPlatform($userId, array $platforms)
    {
        $withdraw    = new Withdraw;
        $queryResult = $withdraw->select(DB::raw('count(1) as withdraw_count'))->whereExists(function ($query) use ($userId, $withdraw) {
            $walleTable    = (new Wallet)->getTable();
            $withdrawTable = $withdraw->getTable();
            $query->select(DB::raw('1'))
                ->from($walleTable)
                ->where('user_id', $userId)
                ->where('type', Wallet::RMB_TYPE)
                ->where($withdrawTable . '.wallet_id', DB::raw($walleTable . '.id'));
        })->ofPlatform($platforms)->success()->first();

        return $queryResult->withdraw_count;
    }

    public static function hasWithdraw($userId)
    {
        $withdrawCount = Withdraw::withdrawCount($userId);
        return $withdrawCount > 0;
    }

    public static function randomAmount($user)
    {
        /**
         * 0.01元：30%
         * 0.2元：30%
         * 0.3元：30%
         * 0.4元：5%
         * 0.5元：5%
         */

        $maxAmount = bcdiv($user->gold, Exchange::RATE);
        $seed      = mt_rand(1, 100);
        if ($seed >= 95 && $maxAmount >= 0.5) {
            $randomAmount = 0.5;
        } else if ($seed >= 90 && $maxAmount >= 0.4) {
            $randomAmount = 0.4;
        } else if ($seed >= 60 && $maxAmount >= 0.3) {
            $randomAmount = 0.3;
        } else if ($seed >= 30 && $maxAmount >= 0.2) {
            $randomAmount = 0.2;
        } else {
            $randomAmount = 0.1;
        }

        return $randomAmount;
    }

    public static function makeWithdrawOption($amount, $disable = false, $optionConfig = [])
    {
        $needContributes = is_numeric($amount) ? User::getAmountNeedDayContributes($amount) : 0;
        $option          = [
            'disable'         => $disable,
            'amount'          => $amount,
            'needContributes' => Arr::get($optionConfig, 'needContributes', $needContributes),
            'description'     => Arr::get($optionConfig, 'description', is_numeric($amount) ? Exchange::computeGold($amount) . '智慧点' : ''),
            'tips'            => Arr::get($optionConfig, 'tips', '秒到账'),
            'fontColor'       => Arr::get($optionConfig, 'fontColor', '#A0A0A0'),
            'bgColor'         => Arr::get($optionConfig, 'bgColor', '#FFBB04'),
            'rule'            => Arr::get($optionConfig, 'rule'),
            'leftTime'        => Arr::get($optionConfig, 'leftTime', 0),
            'platform'        => Arr::get($optionConfig, 'platform', 'all'),
            'type'            => Arr::get($optionConfig, 'type', Withdraw::FIXED_TYPE),
        ];
        $option['label'] = Arr::get($optionConfig, 'label', $option['amount']);

        return $option;
    }

    public static function isEffectiveAmount($amount, $type)
    {
        $amountArr = [0.1, 0.3, 0.5, 0.7, 1, 3, 5, 10];
        if ($type == Withdraw::INVITE_ACTIVITY_TYPE) {
            $amountArr = [1, 20];
        }

        return in_array($amount, $amountArr);
    }

}
