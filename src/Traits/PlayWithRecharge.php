<?php

namespace Haxibiao\Wallet\Traits;

use Haxibiao\Wallet\Recharge;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait PlayWithRecharge
{
    public function recharges(): HasMany
    {
        return $this->hasMany(Recharge::class);
    }
}
