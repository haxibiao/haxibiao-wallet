<?php

namespace Haxibiao\Wallet\Traits;

use GraphQL\Type\Definition\ResolveInfo;
use Haxibiao\Wallet\Exchange;

trait ExchangeResolvers
{
    // FIXME::没有该方法
    public function resolveExchangeCash($root, $args, $context, ResolveInfo $info)
    {
        app_track_event("提现", "金币兑换余额");

        $user = getUser();

        return $user->exchangeCash($args['gold']);
    }

    public function resolveExchange($root, $args, $context, ResolveInfo $info)
    {
        app_track_event("提现", "查询兑换详情");
        return Exchange::getExchange($args['id']);
    }

    public function resolveExchanges($root, $args, $context, ResolveInfo $info)
    {
        app_track_event("提现", "查询兑换列表");
        return Exchange::where('user_id', getUserId())->latest('id');
    }
}
