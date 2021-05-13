<?php

namespace Haxibiao\Wallet\Traits;

use App\User;
use GraphQL\Type\Definition\ResolveInfo;
use Haxibiao\Task\Contribute;
use Haxibiao\Wallet\Wallet;
use Haxibiao\Wallet\Withdraw;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

trait WithdrawResolvers
{
    public function resolverSetWalletInfo($root, $args, $context, $info)
    {
        app_track_event("个人中心", "设置钱包信息");
        return Wallet::setInfo(getUser(), $args['input']);
    }

    public function resolveWithdraws($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        app_track_event("提现列表", 'list_withdraws', getUserId());
        return Withdraw::orderBy('id', 'desc')->where('wallet_id', $args['wallet_id']);
    }

    public function resolveWithdraw($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        app_track_event("提现详情", 'show_withdraw', $args['id']);
        return Withdraw::find($args['id']);
    }

    /**
     *
     * 1.新人首次提现0.3元：
     * 前端：新人未提现前展示0.3/1/3/5，提现1次后展示1/3/5/10
     * 2.用户第二次提现引导下载懂得赚：
     * 后端：用户首次可提现0.3（无门槛），第二次触发提现，强制下载懂得赚，并到懂得赚上才能提现（配合前端弹窗）。
     * @param $rootValue
     * @param array $args
     * @param GraphQLContext $context
     * @param ResolveInfo $resolveInfo
     * @return array
     */
    public function getWithdrawAmountList($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {

        $user = checkUser();

        $contribute       = Contribute::WITHDRAW_DATE;
        $isWithdrawBefore = $user ? $user->isWithdrawBefore() : false;
        //  工厂内是否提现 || 懂得赚上是否提现
        if ($isWithdrawBefore) {
            $minAmount = 1;
        } else {
            $minAmount = 0.3;
        }
        $tenTimesHighWithdrawCardsCount  = 0;
        $fiveTimesHighWithdrawCardsCount = 0;
        $doubleHighWithdrawCardsCount    = 0;
        if ($user) {
            $tenTimesHighWithdrawCardsCount  = $user->tenTimesHighWithdrawCardsCount;
            $fiveTimesHighWithdrawCardsCount = $user->fiveTimesHighWithdrawCardsCount;
            $doubleHighWithdrawCardsCount    = $user->doubleHighWithdrawCardsCount;
        }

        //非网赚项目,提现可比例放大倍数
        $withdraw_rate = config('withdraw.rate') ?? 1;

        $withdrawInfo = [
            [
                'amount'                => $minAmount * $withdraw_rate,
                'description'           => '新人福利',
                'tips'                  => '秒到账',
                'fontColor'             => '#FFA200',
                'bgColor'               => '#EF514A',
                'highWithdrawCardsRate' => null,
            ],
            [
                'amount'                => 0.5 * $withdraw_rate,
                'description'           => $contribute * 0.5 . '日活跃',
                'tips'                  => '秒到账',
                'fontColor'             => '#A0A0A0',
                'bgColor'               => '#FFBB04',
                'highWithdrawCardsRate' => null,
            ],
            [
                'amount'                => 1 * $withdraw_rate,
                'description'           => $contribute * 1 . '日活跃',
                'tips'                  => '限量抢',
                'fontColor'             => '#A0A0A0',
                'bgColor'               => '#FFBB04',
                'highWithdrawCardsRate' => $doubleHighWithdrawCardsCount,

            ],
            [
                'amount'                => 3 * $withdraw_rate,
                'description'           => $contribute * 3 . '日活跃',
                'tips'                  => '限量抢',
                'fontColor'             => '#A0A0A0',
                'bgColor'               => '#FFBB04',
                'highWithdrawCardsRate' => $fiveTimesHighWithdrawCardsCount,
            ],
            [
                'amount'                => 5 * $withdraw_rate,
                'description'           => $contribute * 5 . '日活跃',
                'tips'                  => '限量抢',
                'fontColor'             => '#A0A0A0',
                'bgColor'               => '#FFBB04',
                'highWithdrawCardsRate' => $tenTimesHighWithdrawCardsCount,
            ],
        ];

        //        去掉头或尾部数据
        if (count($withdrawInfo) > 4) {
            if ($isWithdrawBefore) {
                array_shift($withdrawInfo);
            } else {
                array_pop($withdrawInfo);
            }
        }

        return $withdrawInfo;
    }

    public function resolveCreateWithdraw($root, $args, $context, $info)
    {
        $user     = \getUser();
        $amount   = Arr::get($args, 'amount', 0);
        $platform = $args['platform'];
        $type     = $args['type'] ?? Withdraw::FIXED_TYPE;
        $withdraw = Withdraw::createWithdraw($user, $amount, $platform, $type);
        return (int) !is_null($withdraw); //兼容老版本，返回int
    }

    public function resolveCreateNewWithdraw($root, $args, $context, $info)
    {
        $user     = \getUser();
        $amount   = Arr::get($args, 'amount', 0);
        $platform = $args['platform'];
        $type     = $args['type'] ?? Withdraw::FIXED_TYPE;
        $withdraw = Withdraw::createWithdraw($user, $amount, $platform, $type);

        return $withdraw;
    }

    public function resolverWithdraws($root, $args, $context, $info)
    {
        $user = \getUser();
        return $user->withdraws();
    }

    public function resolveExchangeBalance(User $user, $gold)
    {
        //TODO: fix this

    }

    public function resolveWithdrawOptions($root, $args, $context, $info)
    {
        $user = getUser();

        return $user->withdrawOptions;
    }

    public function resolveDailyWithdrawNotice($root, $args, $context, $info)
    {
        $limit    = $args['limit'];
        $data     = [];
        $profiles = Withdraw::with('user')
            ->where('amount', '>', 0.5)
            ->latest('id')
            ->take($limit)
            ->get()
            ->each(function ($item) use (&$data) {
                $user = $item->user;
                if (!is_null($user)) {
                    $account = $this->subStrCut($user->account);
                    $data[]  = sprintf('用户%s今日成功提现%s元', $account, $item->amount);
                }
            });

        shuffle($data);

        return $data;
    }

    public function subStrCut($user_name)
    {
        $strlen   = mb_strlen($user_name, 'utf-8');
        $firstStr = mb_substr($user_name, 0, 1, 'utf-8');
        $lastStr  = mb_substr($user_name, -1, 1, 'utf-8');
        return $strlen == 2 ? $firstStr . str_repeat('*', mb_strlen($user_name, 'utf-8') - 1) : $firstStr . str_repeat("*", $strlen - 2) . $lastStr;
    }

}
