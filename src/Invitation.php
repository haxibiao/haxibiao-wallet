<?php

namespace Haxibiao\Wallet;

use Haxibiao\Breeze\Traits\ModelHelpers;
use Haxibiao\Wallet\Traits\InvitationAttrs;
use Haxibiao\Wallet\Traits\InvitationRepo;
use Haxibiao\Wallet\Traits\InvitationResolvers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    use InvitationAttrs;
    use InvitationRepo;
    use InvitationResolvers;
    use ModelHelpers;

    protected $fillable = [
        'account',
        'user_id',
        'invited_in',
        'invited_user_id',
        'rewarded',
        'patriarch_id',
    ];

    protected $casts = [
        'invited_in' => 'datetime',
    ];

    const UPDATED_AT = null;

    //智慧点和贡献点奖励
    const GOLD_REWARD        = 600;
    const CONTRIBUTES_REWARD = 36;

    protected static function booted()
    {
        //过滤掉被删除的用户
        static::addGlobalScope('hasInvitedUser', function (Builder $builder) {
            return $builder->has('invitedUser');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedUser()
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'id', 'relate_id')->where('type', 'invitations');
    }

    public function scopeActive($query)
    {
        return $query->whereNotNull('invited_in');
    }

    public function scopeInactive($query)
    {
        return $query->whereNull('invited_in');
    }

    public function getRewardAmountAttribute()
    {
        return data_get($this->transaction, 'amount', 0);
    }
}
