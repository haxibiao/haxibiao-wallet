<?php

namespace Haxibiao\Wallet\Traits;

use App\User;
use GraphQL\Type\Definition\ResolveInfo;
use Haxibiao\Breeze\Exceptions\UserException;
use Haxibiao\Wallet\Invitation;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

trait InvitationResolvers
{
    //邀请用户
    public function resolveInviteUser($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        app_track_event("邀请", "邀请用户对接");
        $user    = User::find($args['user_id']);
        $account = data_get($args, 'account');
        if (is_null($user)) {
            throw new UserException('邀请人不存在,请重试!');
        }
        return Invitation::connect($user, $account);
    }

    //邀请列表
    public function resolveInvitationUsers($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        if (isset($args['user_id'])) {
            $user = User::find($args['user_id']);
        } else {
            $user = getUser();
        }

        if (is_null($user)) {
            throw new UserException('获取失败,用户不存在!');
        }

        return Invitation::invitations($user, $args);
    }

    //邀请奖励列表
    public function resolveInvitationRewards($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        return Invitation::invitationRewards(data_get($args, 'limit', null));
    }

    //是否邀请用户
    public function resolveIsInviteUser($root, $args, $context, ResolveInfo $info)
    {
        return Invitation::isInviteUser($args['account']);
    }

    //绑定邀请，邀请码/口令
    public function resolveBindInvitation($root, $args, $context, ResolveInfo $info)
    {
        $inviteCode = $args['invite_code'];

        return Invitation::inviteCodeBind(getUser(), $inviteCode);
    }

    public static function resolveInvitations($root, $args, $context, ResolveInfo $info)
    {
        $status = $args['status'];
        $user   = getUser();

        if ($status == 'ACTIVE') {
            $qb = $user->invitations()->active();
        } else if ($status == 'INACTIVE') {
            $qb = $user->invitations()->inactive();
        } else {
            $qb = $user->secondaryApprentices();
        }

        $qb->with('transaction');

        return $qb;
    }
}
