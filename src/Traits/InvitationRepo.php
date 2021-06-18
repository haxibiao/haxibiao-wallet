<?php

namespace Haxibiao\Wallet\Invitation;

use App\BanUser;
use App\Exceptions\UserException;
use App\Invitation;
use App\Transaction;
use App\User;
use App\UserProfile;
use App\UserStageInvitation;
use App\Wallet;

trait InvitationRepo
{
    /**
     * 保存邀请关系
     */
    public static function connect(User $user, $account)
    {
        $userExisted = User::where('account', $account)->exists();

        if ($userExisted) {
            throw new UserException('邀请失败,该用户已注册!');
        }

        if (str_contains($account, "@")) {
            throw new UserException('暂时不支持邮箱注册,请用手机号注册');
        }

        $invitation = Invitation::firstOrNew(['account' => $account]);

        if (isset($invitation->id)) {
            if (!is_testing_env()) {
                throw new UserException('邀请失败,请勿重复邀请!');
            }
        }

        $invitation->fill(['user_id' => $user->id])->save();

        return $invitation;
    }

    public static function invitations(User $user)
    {
        $invitations = Invitation::select('account')->where('user_id', $user->id)
            ->whereNotNull('invited_in')
            ->get();
        $users = [];
        if ($invitations->count() > 0) {
            $users = User::whereIn('account', $invitations->pluck('account'))->get();
        }

        return $users;
    }

    public static function invitationRewards($limit = 10)
    {
        $data = [];

        //取前面100提现量较高的用户
        $profiles = UserProfile::select(['user_id', 'transaction_sum_amount'])->with('user')
            ->latest('transaction_sum_amount')
            ->take($limit)
            ->get()
            ->each(function ($item) use (&$data) {
                $user   = $item->user;
                $data[] = sprintf('%s又邀请了一位好友,累计提现%s元', $user->name, $item->transaction_sum_amount);
            });

        shuffle($data);

        return $data;
    }

    public static function isInviteUser($account)
    {

        $userExisted = User::where('account', $account)->exists();

        if ($userExisted) {
            throw new UserException('该账户已注册,请进行登录哦!');
        }

        return Invitation::where('account', $account)->whereNull('invited_in')->exists();
    }

    public function isInvitedNewUser()
    {
        $invitedUser          = $this->invitedUser;
        $invitedUserCreatedAt = data_get($invitedUser, 'created_at');
        $isInvitedNewUser     = false;
        if (!empty($invitedUserCreatedAt)) {
            //24小时内注册的才算邀请新用户
            $isInvitedNewUser = $invitedUserCreatedAt->diffInHours(now()) < 24;
        }

        return $isInvitedNewUser;
    }

    public function inviteCodeBind(User $user, $code)
    {
        // 这里可能是邀请口令
        preg_match_all('#【.*?】#', $code, $match);
        if (isset($match[0][1])) {
            $code = $match[0][1];
        }

        $hasInvitation = Invitation::where('account', $user->account)
            ->orWhere('invited_user_id', $user->id)
            ->first();

        throw_if($hasInvitation, UserException::class, '绑定失败,您的账号已绑定过邀请!');
        throw_if($user->created_at->diffInHours(now()) >= 24, UserException::class, '绑定失败,您的账号已注册超过24小时');
        $mentorUserId = User::deInviteCode($code);
        throw_if(!is_numeric($mentorUserId), UserException::class, '绑定失败,邀请码错误!');

        $patriarchId = data_get(Invitation::select('user_id')->where('invited_user_id', $mentorUserId)->first(), 'user_id', 0);

        //检测邀请刷子，直接封禁
        //邀请了两个及以上的用户created_at在同一时间（秒）
        $invitationExisted = Invitation::where('user_id', $mentorUserId)->where('created_at', $user->created_at)->exists();
        if ($invitationExisted) {
            BanUser::record($user, '小号恶意刷取邀请活动');
            throw_if(true, UserException::class, '绑定失败,请稍后再试!');
        }

        throw_if($mentorUserId == $user->id, UserException::class, '绑定失败,不能绑定自己的邀请码!');
        $invitation = Invitation::create([
            'account'         => $user->account,
            'invited_user_id' => $user->id,
            'user_id'         => $mentorUserId,
            'patriarch_id'    => $patriarchId,
        ]);

        // 如果是已经提现的用户 && 直接发放奖励,邀请成功
        $user->inviteReward();

        return $invitation;
    }

    public static function adReward(User $user)
    {
        //师傅
        $mentor = $user->mentor;
        //师傅的师傅
        $patriarch = data_get($mentor, 'mentor');
        $adRevenue = 0.01;

        if (!is_null($mentor)) {
            $mentorWallet = Wallet::findOrCreate($mentor->id, Wallet::INVITATION_TYPE);
            $mentorSatge  = UserStageInvitation::findOrCreate($mentor->id);
            $reawrdRate   = $mentorSatge->stage->reward_rate;
            Transaction::makeInCome($mentorWallet, bcmul($adRevenue, $reawrdRate, 4), '徒弟看视频收益');
        }

        if (!is_null($patriarch)) {
            $patriarchWallet = Wallet::findOrCreate($patriarch->id, Wallet::INVITATION_TYPE);
            $patriarchSatge  = UserStageInvitation::findOrCreate($patriarch->id);
            $reawrdRate      = $patriarchSatge->stage->reward_rate;
            // 徒孙收益 50%
            $adRevenue /= 2;
            Transaction::makeInCome($patriarchWallet, bcmul($adRevenue, $reawrdRate, 4), '徒孙看视频收益');
        }
    }
}
