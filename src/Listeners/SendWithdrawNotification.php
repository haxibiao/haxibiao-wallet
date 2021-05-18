<?php

namespace Haxibiao\Wallet\Listeners;

use Haxibiao\Wallet\Events\WithdrawalDone;
use Haxibiao\Wallet\Notifications\WithdrawFailureNotification;
use Haxibiao\Wallet\Notifications\WithdrawSuccessNotification;

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
            //成功提现
            if ($this->withdraw->isSuccess()) {
                return $user->notify(new WithdrawSuccessNotification($this->withdraw));
            }
            //失败提现
            if ($this->withdraw->isFailed()) {
                return $user->notify(new WithdrawFailureNotification($this->withdraw));
            }
        }
    }
}
