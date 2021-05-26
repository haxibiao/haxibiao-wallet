<?php

namespace Haxibiao\Wallet\Traits;

use Haxibiao\Breeze\OAuth;
use Haxibiao\Wallet\Recharge;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait PlayWithWallet
{
    /**
     * 充值记录
     */
    public function recharges(): HasMany
    {
        return $this->hasMany(Recharge::class);
    }

    /**
     * 登录授权
     */
    public function oauths(): HasMany
    {
        return $this->hasMany(OAuth::class);
    }

}
