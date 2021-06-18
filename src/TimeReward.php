<?php

namespace Haxibiao\Wallet;

use Haxibiao\Wallet\Traits\RewardRepo;
use Haxibiao\Wallet\Traits\RewardResolvers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeReward extends Model
{
    use RewardRepo, RewardResolvers;

    protected $fillable = [
        'user_id',
        'reward_type',
        'reward_value',
        'created_at',
    ];

    public function user(): belongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getNextHourRewardTime()
    {
        $now      = now();
        $nextHour = $now;
        if ($now->minute > 0) {
            $nextHour = $now->startOfHour()->addHour();
        }

        return $nextHour;
    }
}
