<?php

namespace Haxibiao\Wallet\Events;

use Haxibiao\Wallet\Withdraw;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class WithdrawalDone implements ShouldBroadcast
{
    public $withdraw = null;

    use Dispatchable, InteractsWithSockets;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Withdraw $withdraw)
    {
        $this->withdraw = $withdraw;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
		if(in_array(config('app.name'),['haxibiao','yinxiangshipin'])){
			return new PrivateChannel(config('app.name').'.User.' . $this->withdraw->user_id);
		}
        return new PrivateChannel('App.User.' . $this->withdraw->user_id);
    }

    public function broadcastWith()
    {
        $withdraw = $this->withdraw;

        return [
            'title'   => '提现提醒',
            'content' => sprintf('您的提现订单:%s,%s', $withdraw->bizNo, $withdraw->isSuccess() ? '提现成功,请注意查收!' : '提现失败,请注意查看提现失败详情'),
            'icon'    => 'icon',
            'id'      => $withdraw->id,
        ];
    }
}
