<?php

namespace Haxibiao\Wallet;

use Haxibiao\Breeze\User;
use Haxibiao\Wallet\Traits\ExchangeRepo;
use Haxibiao\Wallet\Traits\ExchangeResolvers;
use Illuminate\Database\Eloquent\Model;

class Exchange extends Model
{
    use ExchangeRepo;
    use ExchangeResolvers;
    protected $fillable = [
        'user_id',
        'gold',
        'rmb',
        'exchange_rate',
        'gold_balance',
        'created_at',
        'updated_at',
    ];

    //兑换汇率
    const RATE = 600;

    const MIN_RMB = 1;

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    public static function computeAmount($gold)
    {
        return $gold / Exchange::RATE;
    }

    public static function computeGold($amount)
    {
        return $amount * Exchange::RATE;
    }

    // 兑入
    public static function exhangeIn($user, $gold): Exchange
    {
        $rmb = self::computeAmount($gold);
        return Exchange::create([
            'user_id'       => $user->id,
            'gold'          => $gold,
            'gold_balance'  => $user->gold,
            'rmb'           => $rmb,
            'exchange_rate' => Exchange::RATE,
        ]);
    }

    // 兑出
    public static function exchangeOut($user, $gold): Exchange
    {
        $rmb  = self::computeAmount($gold);
        $gold = -1 * $gold;
        return Exchange::create([
            'user_id'       => $user->id,
            'gold'          => $gold,
            'gold_balance'  => $user->gold,
            'rmb'           => $rmb,
            'exchange_rate' => Exchange::RATE,
        ]);
    }

    public static function changeToWallet($user, $gold, $wallet)
    {
        if ($gold <= 0) {
            throw new \Exception('兑换失败，当前账户没有智慧点');
        }

        $amount = self::computeAmount($gold);

        //添加兑换记录
        Exchange::exchangeOut($user, $gold);

        //扣除智慧点
        Gold::makeOutcome($user, $gold, '兑换余额');

        //添加流水记录
        Transaction::makeIncome($wallet, $amount);
    }
}
