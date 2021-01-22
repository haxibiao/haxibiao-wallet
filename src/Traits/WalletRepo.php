<?php

namespace Haxibiao\Wallet\Traits;

use App\Jobs\ProcessWithdraw;
use Haxibiao\Breeze\Exceptions\GQLException;
use Haxibiao\Breeze\Exceptions\UserException;
use Haxibiao\Breeze\User;
use Haxibiao\Wallet\Exchange;
use Haxibiao\Wallet\Gold;
use Haxibiao\Wallet\Transaction;
use Haxibiao\Wallet\Wallet;
use Haxibiao\Wallet\Withdraw;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        if (is_null($wallet)) {
            throw new \Exception('兑换失败,请先完善提现信息!');
        }

        if (!$canExchange) {
            throw new UserException('兑换失败,智慧点不足!');
        }

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
            Exchange::exchangeOut($user, $gold);
            //添加流水记录
            Transaction::makeIncome($wallet, $amount, '智慧点兑换');
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

    public function setPayId($openId, $platform = Withdraw::ALIPAY_PLATFORM)
    {
        $field        = $platform == Withdraw::ALIPAY_PLATFORM ? 'pay_account' : 'wechat_account';
        $this->$field = $openId;
    }

    public function getPayId($platform = Withdraw::ALIPAY_PLATFORM)
    {
        $field = $platform == Withdraw::ALIPAY_PLATFORM ? 'pay_account' : 'wechat_account';
        return $this->$field;
    }

}