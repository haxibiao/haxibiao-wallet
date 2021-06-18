<?php

namespace Haxibiao\WalletInvitation;

use Haxibiao\Wallet\LuckyDraw;

trait LuckyDrawAttrs
{
    public function getStatusStringAttribute()
    {
        if ($this->status == LuckyDraw::STATUS_WIN) {
            return "已中奖";
        } else if ($this->status == LuckyDraw::STATUS_WAIT) {
            return "已报名";
        } else if ($this->status == LuckyDraw::STATUS_FAIL) {
            return "未中奖";
        }
    }

}
