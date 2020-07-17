<?php

namespace Haxibiao\Wallet\Traits;

use Haxibiao\Wallet\Recharge;

trait RechargeAttrs
{
    public function getStatusMsgAttribute()
    {
        // 只要我们没有收到钱，就是充值失败
        if ($this->status != Recharge::RECHARGE_SUCCESS) {
            return '充值失败';
        }
        return '充值成功';
    }

    public function getPlatformMsgAttribute()
    {
        return Recharge::getPayPlatfroms()[$this->platform];
    }
}
