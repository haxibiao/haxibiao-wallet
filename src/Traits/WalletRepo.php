<?php

namespace Haxibiao\Wallet\Traits;

use App\User;
use Haxibiao\Breeze\Exceptions\ErrorCode;
use Haxibiao\Breeze\Exceptions\GQLException;
use Haxibiao\Breeze\Exceptions\UserException;
use Haxibiao\Breeze\OAuth;
use Haxibiao\Wallet\Exchange;
use Haxibiao\Wallet\Gold;
use Haxibiao\Wallet\Jobs\ProcessWithdraw;
use Haxibiao\Wallet\Transaction;
use Haxibiao\Wallet\Wallet;
use Haxibiao\Wallet\Withdraw;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait WalletRepo
{

    public static function exchangeBalance(User $user, $amount)
    {
        // 攻击提现接口时，延迟提现有可能堵塞php-fpm 并发能力，也降低用户秒提现体验了
        // sleep(1);

        $gold        = $amount * Exchange::RATE;
        $goldBalance = $user->gold - $gold;
        $canExchange = $goldBalance >= 0 && $gold > 0;

        $wallet = $user->wallet;
        throw_if(is_null($wallet), UserException::class, '兑换失败,请先完善提现信息!');
        throw_if(!$canExchange, UserException::class, '兑换失败,智慧点不足!', ErrorCode::GOLD_NOT_ENOUGH);

        /**
         * 开启事务、锁住智慧点记录
         */
        DB::beginTransaction();
        //兑换状态
        $exchangeStatus = 0;
        try {
            //扣除智慧点
            Gold::makeOutcome($user, $gold, "兑换余额");
            //添加兑换记录
            $exchange = Exchange::exchangeOut($user, $gold);
            //添加流水记录
            $wallet->makeIncome($amount, $exchange, '智慧点兑换');
            //变更兑换状态
            $exchangeStatus = 1;
            DB::commit();
        } catch (\Exception $ex) {
            DB::rollBack(); //数据库回滚
            Log::error($ex);
        }

        return $exchangeStatus;
    }

    //FIXME: rmb钱包对象负责： 收入,提现
    //TODO: 充值
    public function changeRMB($amount, $remark)
    {
        $balance = $this->balance + $amount;
        return Transaction::create([
            'user_id'   => $this->user_id,
            'wallet_id' => $this->id,
            'amount'    => $amount,
            'balance'   => $balance,
            'remark'    => $remark,
        ]);
    }

    public function withdraw($amount, $to_account = null, $to_platform = Withdraw::ALIPAY_PLATFORM, $totalRate = null): Withdraw
    {
        if ($this->available_balance < $amount) {
            throw new GQLException('余额不足');
        }
        $withdraw = Withdraw::create([
            'wallet_id'   => $this->id,
            'amount'      => $amount,
            'to_account'  => $to_account ?? $this->getPayId($to_platform),
            'to_platform' => $to_platform,
        ]);

        $isImmediatePayment = is_null($totalRate);

        if ($isImmediatePayment) {
            dispatch(new ProcessWithdraw($withdraw->id))->onQueue('withdraws');
        } else {
            $withdraw->rate = $totalRate;
            $withdraw->save();
        }
        return $withdraw;
    }

    //FIXME: 金币钱包对象负责： 收入，兑换
    //TODO: 转账(仅限打赏，付费问答时)

    public function changeGold($gold, $remark)
    {
        $goldBalance = $this->goldBalance + $gold;
        $gold        = Gold::create([
            'user_id'   => $this->user_id,
            'wallet_id' => $this->id,
            'gold'      => $gold,
            'balance'   => $goldBalance,
            'remark'    => $remark,
        ]);
        //更新user表上的冗余字段
        $this->user->update(['gold' => $goldBalance]);
        return $gold;
    }

    //金币钱包，兑换rmb
    public function exchange($rmb)
    {
        $gold = Exchange::computeGold($rmb);

        //扣除gold
        $this->changeGold(-$gold, "兑换");
        //记录兑换
        $exchange = Exchange::exchangeOut($this->user, $gold);

        //钱包收入
        $wallet = $this->user->wallet; //默认钱包
        $wallet->changeRMB($exchange->rmb, '兑换收入');
    }

    public static function rmbWalletOf(User $user): Wallet
    {
        $wallet = self::firstOrCreate([
            'user_id' => $user->id,
            'type'    => 0,
        ]);
        return $wallet;
    }

    public static function goldWalletOf(User $user): Wallet
    {
        $wallet = self::firstOrCreate([
            'user_id' => $user->id,
            'type'    => 1,
        ]);
        return $wallet;
    }

    public function updatePayInfo()
    {
        $payInfos = $this->pay_infos ?? [];
        //当以下字段被更新时
        $isChange = $this->isDirty('pay_account') || $this->isDirty('open_id') || $this->isDirty('real_name');
        if ($isChange) {
            $payInfos[] = [
                'pay_account' => $this->pay_account,
                'open_id'     => $this->open_id,
                'real_name'   => $this->real_name,
                'time'        => now()->toDateTimeString(),
            ];
            $this->pay_infos = $payInfos;
        }
    }

    public function setPayId($openId, $platform = Withdraw::ALIPAY_PLATFORM)
    {
        //TODO::部分项目还是用wechat_account
        $field        = $platform == Withdraw::ALIPAY_PLATFORM ? 'pay_account' : 'open_id';
        $this->$field = $openId;
    }

    public function getPayId($platform = Withdraw::ALIPAY_PLATFORM)
    {
        if (in_array($platform, Withdraw::OUR_SITE)) {
            list($siteName, $siteDomain, $isOurSite) = Withdraw::getPlatformInfo($platform);
            $user                                    = $this->user;
            $oauth                                   = OAuth::bindSite($user, $platform, $siteDomain, $siteName);
            $value                                   = $oauth->oauth_id;
        } else {
            if ($platform == Withdraw::JDJR_PLATFORM) {
                $user  = $this->user;
                $value = $user->account;
            } else {
                $oauth = OAuth::select('oauth_id')->where('user_id', $this->user_id)->OfType($platform)->first();
                $value = data_get($oauth, 'oauth_id');
            }
        }

        return $value;
    }

    public function removePlatform($platform)
    {
        $this->setPayId(null, $platform);
        $this->save();

        return $this;
    }

    public static function isAlipayOpenId($payId)
    {
        return Str::startsWith($payId, '2088') && !is_email($payId);
    }

    public static function setInfo($user, array $data)
    {
        $wallet = Wallet::firstOrNew(['user_id' => $user->id]);
        $wallet->fill($data);
        $wallet->save();

        return $wallet;
    }
}
