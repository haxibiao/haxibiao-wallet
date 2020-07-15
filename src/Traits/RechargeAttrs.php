<?php

namespace Haxibiao\Wallet\Traits;

use Haxibiao\Wallet\Recharge;

trait RechargeAttrs
{
    public function getStatusMsgAttribute()
    {
        return Recharge::getPayStatuses()[$this->status];
    }

    public function getPlatformMsgAttribute()
    {
        return Recharge::getPayPlatfroms()[$this->platform];
    }
}
