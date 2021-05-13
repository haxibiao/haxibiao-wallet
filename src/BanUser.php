<?php

namespace Haxibiao\Wallet;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BanUser extends Model
{

    protected $guarded = [];

    public const STATUS_PROCESSED = 1;
    public const STATUS_WAITING   = 0;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_WAITING   => '待处理',
            self::STATUS_PROCESSED => '已处理',
        ];
    }

    /**
     * 记录有问题可以禁用的用户
     */
    public static function record(User $user, $reason, $ban = true)
    {
        $item = BanUser::firstOrCreate([
            'user_id' => $user->id,
            'reason'  => $reason,
        ]);
        if ($ban) {
            $item->status = BanUser::STATUS_PROCESSED;
            $item->save();

            //直接禁用用户
            $user->muteOrBan(User::DISABLE_STATUS, $reason, __CLASS__);
        }
    }
}
