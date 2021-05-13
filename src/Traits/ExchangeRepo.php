<?php
namespace Haxibiao\Wallet\Traits;

use App\User;
use Haxibiao\Wallet\Exchange;

trait ExchangeRepo
{
    public static function getExchange($id)
    {
        return Exchange::find($id)->first();
    }

    public static function getExchanges($userId, $limit = 10, $offset = 0)
    {
        return Exchange::where('user_id', $userId)
            ->latest('id')
            ->skip($offset)
            ->take($limit)
            ->get();
    }

    public static function createWallet(User $user)
    {
        if (empty($user->wallet)) {
            $user->wallet()->create(['user_id' => $user->id]);
        }
        $user->load('wallet');
        return $user->wallet;
    }
}
