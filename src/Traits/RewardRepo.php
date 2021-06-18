<?php
/**
 * @Author  guowei<gongguowei01@gmail.com>
 * @Data    2020/5/19
 * @Version
 */

namespace Haxibiao\Wallet\Traits;

use App\User;
use Haxibiao\Breeze\Exceptions\UserException;
use Haxibiao\Wallet\Gold;
use Haxibiao\Wallet\TimeReward;

trait RewardRepo
{
    /**
     * 时段奖励
     *
     * @param User $user
     * @return array
     * @throws UserException
     */
    public static function hourReward(User $user)
    {
        $rewardPoint = 5;
        $now         = now();
        $minute      = $now->minute;
        $result      = [
            'gold_reward'       => 0,
            'ticket_reward'     => 0,
            'contribute_reward' => 0,
        ];

        //10分钟级误差
        if ($minute > 10 && !is_testing_env()) {
            throw new UserException('还没到领取时间呢,请在倒计时结束时领取时段奖励哦');
        }

        $latestReward = $user->timeRewards()->latest('id')->first();
        if (!is_null($latestReward)) {
            $diffMinute = $now->diffInMinutes($latestReward->created_at, false);
            //时间范围超出
            if ($diffMinute > 0) {
                throw new UserException('领取失败,系统错误!');
            }
            //领取间隔相差2分钟之内
            if ($diffMinute >= -10) {
                throw new UserException('领取失败,请勿重复领取!');
            }
        }

        $reward = (new TimeReward)->fill(['user_id' => $user->id]);
        // JIRA:DZ-873:随机奖励5精力点或5智慧点
        $randomValue = mt_rand(0, 1);
        if ($randomValue >= 1) {
            Gold::makeIncome($user, $rewardPoint, '时段奖励');
            $reward->reward_type   = 'gold';
            $result['gold_reward'] = $rewardPoint;
        } else {
            $user->increment('ticket', $rewardPoint);
            $reward->reward_type     = 'ticket';
            $result['ticket_reward'] = $rewardPoint;
        }
        $reward->reward_value = $rewardPoint;
        $reward->created_at   = $now;
        $reward->save();

        return $result;
    }
}
