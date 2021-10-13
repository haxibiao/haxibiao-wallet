<?php

namespace Haxibiao\Wallet;

use Haxibiao\Breeze\User;
use Haxibiao\Wallet\Traits\GoldRepo;
use Haxibiao\Wallet\Traits\GoldResolvers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gold extends Model
{
    use GoldRepo;
    use GoldResolvers;

    protected $guarded = [
    ];

    const NEW_USER_REWARD_REASON = '新用户注册奖励';
    //    点击 DRAW 广告的金币额度,
    //    const DRAW_GOLD_AMOUNT = 0;

    //    点击 激励视频 广告的金币额度
    const REWARD_VIDEO_GOLD = 7;

    const REWARD_GOLD   = 10;
    const NEW_USER_GOLD = 300;
    const NEW_YEAR_GOLD = 50;

//当日通过观看视频获取的最高奖励
    const TODAY_VIDEO_PLAY_MAX_GOLD = 50;

    protected $table = 'gold';

    public static function boot()
    {
        //防治刷子恶意刷智慧点
        parent::boot();

        static::saving(function ($model) {
            if (config('app.name') == "datizhuanqian") {
                $user       = $model->user;
                $today_gold = $user->golds()->where('created_at', '>=', today())->sum('gold') ?? 0;
                if ($today_gold >= 6000) {
                    $reason = "异常日期:" . now() . "日单日智慧点获得数大于600";
                    //封禁用户
                    BanUser::record($user, $reason);
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    public function scopeIncome($query)
    {
        return $query->where('gold', '>', 0);
    }
}
