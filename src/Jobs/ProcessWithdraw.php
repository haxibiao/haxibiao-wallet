<?php

namespace Haxibiao\Wallet\Jobs;

use Haxibiao\Wallet\Withdraw;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWithdraw implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $description = "提现队列，走数据库job";
    public $tries          = 1;
    protected $withdraw;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Withdraw $withdraw)
    {
        $this->withdraw = $withdraw;
        $this->onQueue('withdraws');
        $this->onConnection('database');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // 应转换为对应的实体类去调用process函数,因为部分类型的提现处理和正常现金提现逻辑处理存在差异
        $childrenObj = $this->withdraw->convertToChildrenObj();
        if (!is_null($childrenObj)) {
            $childrenObj->process();
        }
    }
}
