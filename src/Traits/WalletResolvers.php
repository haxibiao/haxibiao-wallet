<?php
namespace Haxibiao\Wallet\Traits;

use App\User;
use GraphQL\Type\Definition\ResolveInfo;
use Haxibiao\Breeze\Exceptions\GQLException;
use Haxibiao\Wallet\Wallet;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

trait WalletResolvers
{

    public function resolveTipUser($root, $args, $context, $info)
    {
        //自己
        $user = getUser();
        //打赏对象id
        $target_user_id = $args['user_id'];
        $target_user    = User::findOrFail($target_user_id);
        //打赏金额
        $amount = $args['amount'];
        Wallet::tipUser($user, $target_user, $amount);
    }

    public function resolveSetWalletInfo($root, $args, $context, $info)
    {
        app_track_event("个人中心", "设置钱包信息");
        return Wallet::setInfo(getUser(), $args['input']);
    }

    public function setWalletPayment($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = getUser();

        $real_name = trim($args['real_name']);

        if (!preg_match_all("/([\x{4e00}-\x{9fa5}]+)/u", $real_name)) {
            throw new GQLException('姓名输入不合法,请重新输入~');
        }
        if (!empty($args['pay_account'])) {
            if (is_phone_number($args['pay_account']) == 0) {
                throw new GQLException('目前只支持手机号绑定的支付宝账号哦,请重新输入 ~');
            }
        }

        $wallet              = Wallet::rmbWalletOf($user);
        $wallet->pay_account = $args['pay_account'] ?? null;
        $wallet->real_name   = $args['real_name'];

        //更新一下提现变更记录
        $payInfos          = $wallet->pay_infos;
        $payInfo           = Arr::only($args, ['real_name', 'pay_account']);
        $payInfo['time']   = now()->toDateTimeString();
        $payInfos[]        = $payInfo;
        $wallet->pay_infos = $payInfos;

        $wallet->total_withdraw_amount = 0;
        $wallet->save();

        return $wallet;
    }

    public function setWalletInfo($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user   = getUser();
        $wallet = $user->wallet;
        if (isset($args['real_name'])) {
            $wallet->real_name = $args['real_name'];
        }
        $wallet->save();

        return $wallet;
    }
}
