<?php
/**
 * @Author  guowei<gongguowei01@gmail.com>
 * @Data    2020/5/19
 * @Version
 */

namespace Haxibiao\Wallet\Traits;

use Haxibiao\Game\Game;
use Haxibiao\Question\Question;
use Haxibiao\Wallet\TimeReward;
use Illuminate\Support\Arr;

trait RewardResolvers
{
    public function resolveTimeReward($root, array $args, $context, $info)
    {
        $user       = getUser();
        $rewardType = Arr::get($args, 'reward_type', '');
        app_track_event("奖励", "时段奖励");
        if ($rewardType == 'HOUR_REWARD') {
            return self::hourReward($user);
        }
    }

    public static function SystemConfigResolver($root, array $args, $context, $info)
    {
        //整点返回当前时间段 非整点返回hour
        $now      = now();
        $nextHour = TimeReward::getNextHourRewardTime();
        $result   = [
            'time'                  => $now->toDateTimeString(),
            'time_unix'             => $now->timestamp,
            'server_name'           => gethostname(),
            'name'                  => config('app.name'),
            'next_time_hour_reward' => [
                'time'      => $nextHour->toDateTimeString(),
                'time_unix' => $nextHour->timestamp,
            ],
            'modules'               => [
                'game'                     => [
                    'status'               => Game::gameSwitch(),
                    'ticket_ad_reward'     => Game::GAME_TICKET_REWARD,
                    'gold_ad_reward'       => Game::GAME_LOSER_GOLD_REWARD,
                    'contribute_ad_reward' => 0,
                    'gold_loss'            => Game::GOLD_LOSS,
                    'ticket_loss'          => Game::TICKET_LOSS,
                    'match_time_ms'        => Game::MATCH_TIME_MS,
                ],
                'question_checkpoint_mode' => [
                    'status' => Question::passCheckPointModeABTest(getUser(false)),
                ],
            ],
        ];

        return $result;
    }
}
