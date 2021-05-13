<?php

namespace Haxibiao\Wallet\Traits;

use GraphQL\Type\Definition\ResolveInfo;

trait GoldResolvers
{
    public function resolveGolds($root, $args, $context, ResolveInfo $info)
    {
        //matomo 跟踪
        app_track_event("提现", "查看账单");
        $user = getUser();
        return $user->golds()->latest('id');
    }
}
