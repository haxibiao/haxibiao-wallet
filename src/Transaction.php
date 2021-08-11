<?php

namespace Haxibiao\Wallet;

use Haxibiao\Breeze\Model;
use Haxibiao\Breeze\Traits\ModelHelpers;

//FIXME: 也许需要修复WalletTransaction表的数据过来
class Transaction extends Model
{
    use ModelHelpers;

    protected $guarded = [];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function scopeIncome($query)
    {
        return $query->where('amount', '>', 0);
    }

    public static function makeIncome($wallet, $amount, $remark = '智慧点兑换', $otherData = []): Transaction
    {
        $balance = $wallet->balance + $amount;
        $data    = array_merge([
            'wallet_id' => $wallet->id,
            'amount'    => $amount,
            'balance'   => $balance,
            'remark'    => $remark,
        ], $otherData);
        return Transaction::create($data);
    }

    public static function makeOutcome($wallet, $amount, $remark = '提现'): Transaction
    {
        $amount  = -1 * $amount;
        $balance = $wallet->balance + $amount;
        return Transaction::create([
            'wallet_id' => $wallet->id,
            'amount'    => $amount,
            'balance'   => $balance,
            'remark'    => $remark,
        ]);
    }

    public static function makeRelateIncome($wallet, $amount, $relateable, $remark = '智慧点兑换')
    {
        // 简单处理下脏读导致的坏账:通过type+relate_id排重来避免脏读导致的重复扣款或进账。
        $balance = $wallet->balance + $amount;
        return Transaction::firstOrCreate([
            'wallet_id' => $wallet->id,
            'amount'    => $amount,
            'type'      => $relateable->getMorphClass(),
            'relate_id' => $relateable->id,
        ], [
            'balance' => $balance,
            'remark'  => $remark,
        ]);
    }

    public static function makeRelateOutcome($wallet, $amount, $relateable, $remark = '提现')
    {
        // 简单处理下脏读导致的坏账:通过type+relate_id排重来避免脏读导致的重复扣款或进账。
        $amount  = -1 * $amount;
        $balance = $wallet->balance + $amount;
        return Transaction::firstOrCreate([
            'wallet_id' => $wallet->id,
            'amount'    => $amount,
            'type'      => $relateable->getMorphClass(),
            'relate_id' => $relateable->id,
        ], [
            'balance' => $balance,
            'remark'  => $remark,
        ]);
    }

    public function getFormatedAmountAttribute()
    {
        return number_format($this->amount, 4);
    }

    public function getFormatedBalanceAttribute()
    {
        return number_format($this->balance, 4);
    }

    public static function resolveTransactions($root, array $args, $context, $info)
    {
        return Transaction::thisWeek()->latest('id');
    }
}
