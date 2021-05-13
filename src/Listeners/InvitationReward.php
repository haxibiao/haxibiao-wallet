<?php

namespace Haxibiao\Wallet\Listeners;

use Haxibiao\Wallet\Events\WithdrawalDone;

class InvitationReward
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(WithdrawalDone $event)
    {
        //发放邀请奖励
        $withdraw = $event->withdraw;
        $user     = $withdraw->user;
        if (!is_null($user)) {
            $user->inviteReward();
        }
    }
}
