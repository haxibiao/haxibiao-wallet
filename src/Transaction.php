<?php

namespace Haxibiao\Wallet;

use Haxibiao\Breeze\Model;
use Haxibiao\Breeze\User;

//FIXME: 也许需要修复WalletTransaction表的数据过来
class Transaction extends Model
{
    protected $fillable = [
        'wallet_id',
        'user_id',
        'from_user_id',
        'to_user_id',
        'relate_id',
        'type',
        'remark',
        'log',
        'amount',
        'status',
        'balance',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        //dd("bbbb");
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function tip()
    {
        return $this->belongsTo(Tip::class, 'relate_id');
    }

    //repo
    public static function makeIncome($wallet, $amount, $remark = '智慧点兑换'): Transaction
    {
        $balance = $wallet->balance + $amount;
        return Transaction::create([
            'type'      => '兑换',
            'status'    => '已兑换',
            'wallet_id' => $wallet->id,
            'amount'    => $amount,
            'balance'   => $balance,
            'remark'    => $remark,
        ]);
    }

    public static function makeOutcome($wallet, $amount, $user_id, $remark = '提现'): Transaction
    {
        $amount  = -1 * $amount;
        $balance = $wallet->balance + $amount;
        return Transaction::create([
            'type'      => '提现',
            'status'    => '已支付',
            'wallet_id' => $wallet->id,
            'amount'    => $amount,
            'balance'   => $balance,
            'remark'    => $remark,
            'user_id'   => $user_id,
        ]);
    }
}