<?php

namespace Haxibiao\Wallet\Traits;

use App\User;
use Carbon\Carbon;
use Haxibiao\Breeze\ErrorLog;
use Haxibiao\Helpers\utils\PayUtils;
use Haxibiao\Wallet\Exchange;
use Haxibiao\Wallet\Gold;
use Haxibiao\Wallet\Transaction;
use Haxibiao\Wallet\Wallet;
use Haxibiao\Wallet\Withdraw;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait WithdrawRepo
{

    public function store(Wallet $wallet, $amount, $platform, $to_account)
    {
        return Withdraw::create([
            'wallet_id'   => $wallet->id,
            'amount'      => $amount,
            'to_account'  => $to_account,
            'host'        => gethostname(),
            'to_platform' => $platform,
        ]);
    }

    //FIXME: 如下的一般来说都被废弃了

    /**
     *
     * 处理不同额度 [3, 5, 10]的限量抢逻辑
     *
     * @param $withdraws
     * @param $withdrawThree
     */
    public static function progressWithdrawLimit($withdraws, $withdrawThree)
    {

        if (!$withdraws) {
            return;
        }

        $amountMapping = [
            '3.00'  => 3,
            '5.00'  => 5,
            '10.00' => 10,
        ];
        $amount = $amountMapping[$withdrawThree];

        $successWithdrawsCount = Withdraw::whereDate('created_at', Carbon::yesterday())
            ->where('status', Withdraw::SUCCESS_WITHDRAW)
            ->where('amount', $amount)
            ->count();

        //当前额度没有用户提现成功则选中一位幸运儿
        $luckWithdrawId = null;
        if ($successWithdrawsCount == 0) {
            $plucked        = $withdraws->pluck('rate', 'id')->all();
            $luckWithdrawId = getRand($plucked);
        }

        foreach ($withdraws as $withdraw) {

            $currentId = $withdraw->id;

            $isLuckUser = !is_null($luckWithdrawId) && $currentId === $luckWithdrawId;
            if ($isLuckUser) {
                $withdraw->processDongdezhuan();
                continue;
            } else {
                $withdraw->processingFailedWithdraw('限量抢失败');
            }
        }
    }

    public function transferPayPlatform($outBizNo, $payId, $realName, $amount, $remark, $platform)
    {
        $result = [];
        //转账
        $payUtils = new PayUtils($platform);
        try {
            $transferResult = $payUtils->transfer($outBizNo, $payId, $realName, $amount, $remark);
        } catch (\Exception $ex) {
            $transferResult = $ex->raw ?? null;
        }

        Log::channel('withdraws')->info($transferResult);

        //处理支付响应

        if ($platform == Withdraw::WECHAT_PLATFORM) {
            //微信余额不足
            if (Arr::get($transferResult, 'err_code') != 'NOTENOUGH') {
                $result['order_id']   = $transferResult['payment_no'] ?? null;
                $result['failed_msg'] = $transferResult['err_code_des'] ?? null;
            }
        } else if ($platform == Withdraw::ALIPAY_PLATFORM) {
            //支付宝余额不足、转账失败
            if (isset($transferResult['alipay_fund_trans_uni_transfer_response'])) {
                $transferResult = $transferResult['alipay_fund_trans_uni_transfer_response'];
            }

            if (Arr::get($transferResult, 'sub_code') != 'PAYER_BALANCE_NOT_ENOUGH') {
                $result['order_id']   = $transferResult['order_id'] ?? null;
                $result['failed_msg'] = $transferResult['sub_msg'] ?? null;
            }
        }

        return $result;
    }

    private function writeWithdrawStorage()
    {
        $withdraw = $this;

        $log = 'Withdraw ID:' . $withdraw->id . ' 账号:' . $withdraw->to_account . '  ';

        if ($withdraw->isSuccessWithdraw()) {
            $log .= '提现成功(交易单号:' . $withdraw->trade_no . ')';
        } else if ($withdraw->isFailureWithdraw()) {
            $log .= '提现失败(' . $withdraw->remark . ')';
        } else {
            return;
        }

        //写入到文件中记录 格式 withdraw/2018-xx-xx
        $file = 'withdraw/' . Carbon::now()->toDateString();

        if (!Storage::exists($file)) {
            Storage::makeDirectory('withdraw');
        }

        Storage::disk('local')->append($file, $log);
    }

    public function processDongdezhuan()
    {
        $wallet = $this->wallet;
        $user   = $this->wallet->user;

        //提现是否等待中
        if (!$this->isWaitingWithdraw()) {
            return;
        }

        //判断余额
        if ($wallet->balance < $this->amount) {
            return $this->illegalWithdraw('余额不足,非法订单！');
        }

        $result = $this->makeDongdezhuanTransfer($user);

        if (isset($result['success'])) {
            //转账成功
            $this->processingSucceededWithdraw($result['success'], 'dongdezhuan', '转账到懂得赚成功');
        } else {
            //转账失败
            $remark = $result['error'] ?? '系统错误,提现失败,请重新尝试！';
            $this->processingFailedWithdraw($remark);
        }
    }

    private function makeDongdezhuanTransfer(User $user)
    {
        //重构到DDZ下面
        return \DDZUser::withdraw($user, $this->amount, Withdraw::getOrderNum());
    }

    /**
     * 简单获取订单号
     * @return string
     */
    public static function getOrderNum()
    {
        $date = date('Ymd');
        $rand = substr(implode(null, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 12);
        return $date . $rand;
    }

    /**
     * 处理成功提现
     *
     * @param string $orderId
     * @param string $to_platform
     * @param string $remark
     * @return void
     * @throws \Exception
     */
    protected function processingSucceededWithdraw($orderId, $to_platform = 'Alipay', $remark = '提现成功')
    {
        //重新查询锁住该记录更新
        $withdraw = Withdraw::lockForUpdate()->find($this->id);
        $wallet   = $withdraw->wallet;

        DB::beginTransaction(); //开启事务
        try {
            //1.更改提现记录
            $withdraw->status   = Withdraw::SUCCESS_WITHDRAW;
            $withdraw->trade_no = $orderId;
            $withdraw->remark   = $remark;

            //2.创建流水记录
            $transaction              = Transaction::makeOutcome($wallet, $withdraw->amount, $remark);
            $withdraw->transaction_id = $transaction->id;
            $withdraw->save();

            //更新交易总额
            $wallet->total_withdraw_amount = $wallet->success_withdraw_sum_amount;
            $wallet->save();

            DB::commit(); //事务提交
        } catch (\Exception $ex) {
            DB::rollback(); //数据回滚
        }
    }

    /**
     * 处理失败提现
     *
     * @param string $remark
     * @return void
     * @throws \Exception
     */
    public function processingFailedWithdraw($remark = "")
    {
        //重新查询锁住该记录更新
        $withdraw = Withdraw::lockForUpdate()->find($this->id);
        //最后检查
        if (!$withdraw->isWaiting()) {
            return;
        }
        $wallet = $this->wallet;
        $user   = $wallet->user;

        DB::beginTransaction(); //开启事务
        try {
            //1.更改提现记录
            $withdraw->status = Withdraw::FAILURE_WITHDRAW;
            $withdraw->remark = $remark;
            $withdraw->save();

            //金额兑换智慧点
            $amount = $withdraw->amount;
            $gold   = Exchange::computeGold($amount);

            //2.创建流水记录
            $transaction = Transaction::makeOutcome($wallet, $amount, '提现失败');

            // 3.退回智慧点
            Gold::makeIncome($user, $gold, '提现失败退款');

            //4.创建兑换记录
            Exchange::exhangeIn($user, $gold);

            DB::commit(); //事务提交
        } catch (\Exception $ex) {
            Log::error($ex);
            DB::rollback(); //数据回滚
        }
    }

    /**
     * 非法订单,余额不足
     *
     * @param string $remark
     * @return void
     */
    private function illegalWithdraw($remark)
    {
        $withdraw         = $this;
        $withdraw->status = Withdraw::FAILURE_WITHDRAW;
        $withdraw->remark = $remark;
        $withdraw->save();
    }

    /**
     * 写入提现日志
     *
     * @return void
     */
    private function writeWithdrawLog()
    {
        $withdraw = $this->refresh();

        $log = 'Withdraw ID:' . $withdraw->id . ' 账号:' . $withdraw->to_account . '  ';

        if ($withdraw->isSuccessWithdraw()) {
            $log .= '提现成功(交易单号:' . $withdraw->trade_no . ')';
        } else if ($withdraw->isFailureWithdraw()) {
            $log .= '提现失败(' . $withdraw->remark . ')';
        } else {
            return;
        }

        //写入到文件中记录 格式 withdraw/2018-xx-xx
        $file = 'withdraw/' . Carbon::now()->toDateString();

        if (!Storage::exists($file)) {
            Storage::makeDirectory('withdraw');
        }

        Storage::disk('local')->append($file, $log);
    }

    public static function getNovaFilterAmount()
    {
        return [0.3, 0.5, 1, 3, 5, 10, 20];
    }

    public static function isWhiteListMemeber($userId)
    {
        return in_array($userId, Withdraw::getUserIdWhiteList());
    }

    public function notifyToArray()
    {
        return ['withdraw_id' => $this->id];
    }

    public function isSuccess()
    {
        return $this->status == Withdraw::SUCCESS_STATUS;
    }

    public function isWaiting()
    {
        return $this->status == Withdraw::WATING_STATUS;
    }

    public function isFailed()
    {
        return $this->status == Withdraw::FAILED_STATUS;
    }

    public static function getStatuses()
    {
        return [
            Withdraw::SUCCESS_STATUS => '提现成功',
            Withdraw::FAILED_STATUS  => '提现失败',
            Withdraw::WATING_STATUS  => '待处理',
        ];
    }

    public static function getTypes()
    {
        return [
            Withdraw::RANDOM_TYPE          => '随机金额提现',
            Withdraw::FIXED_TYPE           => '固定金额提现',
            Withdraw::INVITE_ACTIVITY_TYPE => '邀请活动金额提现',
            Withdraw::LUCKYDRAW_TYPE       => '高额抽奖活动金额提现',
        ];
    }

    public static function getPlatformEnumTypes()
    {
        return [
            Withdraw::ALIPAY_PLATFORM => [
                'value'       => Withdraw::ALIPAY_PLATFORM,
                'description' => '支付宝',
            ],
            Withdraw::WECHAT_PLATFORM => [
                'value'       => Withdraw::WECHAT_PLATFORM,
                'description' => '微信',
            ],
            Withdraw::DDZ_PLATFORM    => [
                'value'       => Withdraw::DDZ_PLATFORM,
                'description' => '懂得赚',
            ],
            Withdraw::DM_PLATFORM     => [
                'value'       => Withdraw::DM_PLATFORM,
                'description' => '答妹',
            ],
        ];
    }

    /**
     * 用户白名单(暂时硬编码)
     *
     * @return void
     */
    public static function getUserIdWhiteList(): ?array
    {
        // 从白名单中获取账号
        $accounts = \App\WhiteUser::query()->select('account')->get()->pluck('account')->toArray();
        $userIDs  = User::whereIn('account', $accounts)->select('id')->get()->pluck('id')->toArray();
        return $userIDs;
    }

    public function platformIs($platform)
    {
        return $this->to_platform == $platform;
    }

    public function isInviteActivityType()
    {
        return $this->type == Withdraw::INVITE_ACTIVITY_TYPE;
    }

    public static function balanceSpendSmsNotice($to_platform, $msg)
    {
        //qq、微信、支付宝
        if (in_array($to_platform, [Withdraw::ALIPAY_PLATFORM, Withdraw::QQ_PLATFORM, Withdraw::WECHAT_PLATFORM])) {
            $waitWithdrawCount = Withdraw::query()->wating()->where('to_platform', $to_platform)->count();
            //每一个因为【余额不足】提现失败都发送信息太烦人了，隔50个发一次
            if ($waitWithdrawCount % 50 == 0) {
                ErrorLog::errorSms(($msg));
            }
        }
    }
}
