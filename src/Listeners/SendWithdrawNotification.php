<?php

namespace Haxibiao\Wallet\Listeners;

use Haxibiao\Breeze\Notifications\WithdrawNotification;
use Haxibiao\Wallet\Events\WithdrawalDone;
use Haxibiao\Wallet\Withdraw;

class SendWithdrawNotification
{
    // public $queue = 'listeners';
    public $delay = 10;

    protected $withdraw = null;
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
     * @param  WithdrawalDone  $event
     * @return void
     */
    public function handle(WithdrawalDone $event)
    {
        $this->withdraw = $event->withdraw;
        $user           = $this->withdraw->user;

        if ($user) {
            //提现成功or失败通知
            if ($this->withdraw->status != Withdraw::WAITING_WITHDRAW) {
                return $user->notify(new WithdrawNotification($this->withdraw));
            }
        }
    }
}
