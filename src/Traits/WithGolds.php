<?php

namespace Haxibiao\Wallet\Traits;

use App\User;
use Haxibiao\Task\Contribute;
use Haxibiao\Wallet\Gold;

trait WithGolds
{
    //FIXME: 旧版本任务奖励调用这个奖励看激励视频的奖励...
    public function rewardVideo($adClicked = false)
    {
        $user = $this;
        if ($adClicked) {
            Contribute::rewardUserClickAd($user, User::VIDEO_REWARD_CONTRIBUTE);
            Gold::makeIncome($user, User::VIDEO_REWARD_GOLD, '[查看激励视频]');
        }

        $user->ticket += USER::VIDEO_REWARD_TICKET;
        $user->save();

        return 1;
    }
}
