<?php
namespace Haxibiao\Wallet\Console;

use App\Notice;
use App\Withdraw;
use Illuminate\Console\Command;

class DealWaitingWithdraw extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deal:waiting_withdraw';

    /**
     * The console command description.
     * PM: https://pm.haxifang.com/browse/YXSP-207
     * @var string
     */
    protected $description = '处理等待中的提现';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        //查询出超过24小时的提现待处理订单
        Withdraw::where('status', Withdraw::WAITING_WITHDRAW)->where('created_at', '>=', now()->subDay(1))
            ->chunk(100, function ($withdraws) use (&$count) {
                foreach ($withdraws as $withdraw) {
                    //创建交易记录，金币原路退回
                    $remark = '系统错误,提现失败,请重新尝试！';
                    $withdraw->processingFailedWithdraw($remark);
                    $this->info('Withdraw Id:' . $withdraw->id . ' 提现');
                    info('Withdraw Id:' . $withdraw->id . ' push queue success');

                    //发送系统消息提醒提现失败
                    Notice::addNotice(
                        [
                            'title'      => '提现失败通知',
                            'content'    => "您好，当前时段提现通道异常，技术人员正在紧急修复中，金币已原路退回，请稍后重试",
                            'to_user_id' => $withdraw->wallet->user,
                            'user_id'    => 1,
                            'type'       => Notice::DEDUCTION,
                        ]
                    );
                }
            });

    }
}
