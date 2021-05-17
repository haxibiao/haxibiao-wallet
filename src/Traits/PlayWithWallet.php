<?php

namespace Haxibiao\Wallet\Traits;

use Haxibiao\Breeze\OAuth;
use Haxibiao\Wallet\Recharge;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait PlayWithWallet
{
    public function recharges(): HasMany
    {
        return $this->hasMany(Recharge::class);
    }
    function oauths(): HasMany
    {
        return $this->hasMany(OAuth::class);
    }
}
