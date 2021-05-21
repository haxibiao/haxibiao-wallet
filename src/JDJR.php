<?php
namespace Haxibiao\Wallet;

use Haxibiao\Wallet\Helpers\JDJRHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class JDJR extends Model
{
    protected $table = 'jingdong_jingrong';

    protected $fillable = [
        'user_id',
        'status',
        'did',
        'report_time',
    ];

    const UN_REGISTERED_STATUS_CODE    = 0;
    const REGISTERED_STATUS_CODE       = -1;
    const REWARD_SUCCESS_STATUS_CODE   = 1;
    const REWARD_FAIL_STATUS_CODE      = 2;
    const WITHDRAW_SUCCESS_STATUS_CODE = 3;
    const WITHDRAW_FAIL_STATUS_CODE    = 4;
    const REWARD_OVERDUE_STATUS_CODE   = 5;
    const FIRST_LOGIN_STATUS_CODE      = 6;

    // 表示引流过去的状态码(虚拟的)
    const DZ_REGISTERED_STATUS_CODE = 9999;

    const CODE_MSG = [
        JDJR::UN_REGISTERED_STATUS_CODE    => '未注册用户',
        JDJR::REGISTERED_STATUS_CODE       => '已注册用户(非引流注册)',
        JDJR::DZ_REGISTERED_STATUS_CODE    => '已注册用户(答赚引流过去的)',
        JDJR::REWARD_SUCCESS_STATUS_CODE   => '奖励发放成功',
        JDJR::REWARD_FAIL_STATUS_CODE      => '奖励发放失败',
        JDJR::WITHDRAW_SUCCESS_STATUS_CODE => '核销成功',
        JDJR::WITHDRAW_FAIL_STATUS_CODE    => '核销失败',
        JDJR::REWARD_OVERDUE_STATUS_CODE   => '奖励过期',
        JDJR::FIRST_LOGIN_STATUS_CODE      => '首次登录',
    ];

    const JDJR_CALLBACK_API = 'http://gz023.datizhuanqian.com/api/hook/jdjr';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeQueryDid($query, $value)
    {
        return is_array($value) ? $query->whereIn('did', $value) : $query->where('did', $value);
    }

    public static function init($userId, $phone)
    {
        if (is_phone_number($phone)) {
            $did       = JDJR::encodeDid($phone);
            $status    = data_get(JDJR::select('status')->queryDid($did)->first(), 'status');
            $hasReport = !is_null($status);

            // 没有上报,则初始化上报
            if (!$hasReport) {
                $jdjrHelper = JDJRHelper::setDid($did);
                $jdjrHelper->report(JDJR::JDJR_CALLBACK_API);
                $status    = $jdjrHelper->isNewUser() ? JDJR::UN_REGISTERED_STATUS_CODE : JDJR::REGISTERED_STATUS_CODE;
                $hasReport = !is_null($status);
            }

            // 更新到数据库记录
            if ($hasReport) {
                $jdjr = JDJR::firstOrNew(['did' => $did]);
                if (!isset($jdjr->id)) {
                    $jdjr->user_id     = $userId;
                    $jdjr->status      = $status;
                    $jdjr->report_time = now();
                } else {
                    if ($jdjr->status == JDJR::REGISTERED_STATUS_CODE) {
                        $jdjr->status = $status;
                    }
                }
                $jdjr->report(false, true);

                return $jdjr;
            }
        }
    }

    public function isUnRegisteredUser()
    {
        return $this->status == JDJR::UN_REGISTERED_STATUS_CODE;
    }

    public function isRegisteredUser()
    {
        return $this->status == JDJR::REGISTERED_STATUS_CODE;
    }

    public function isNewUser(): bool
    {
        // 新用户的定义是: 未提现过的用户 || 未注册的用户
        return !$this->isOldUser();
    }

    public function isOldUser(): bool
    {
        return in_array($this->status, [JDJR::REGISTERED_STATUS_CODE, JDJR::WITHDRAW_SUCCESS_STATUS_CODE]);
    }

    public function statusEquals($status)
    {
        return $this->status == $status;
    }

    public function hasReport()
    {
        return !empty($this->report_time);
    }

    public function report($forceReport = false, $isSave = false)
    {
        if (!$this->hasReport() || $forceReport) {
            JDJRHelper::setDid($this->did)->report(JDJR::JDJR_CALLBACK_API);
            $this->report_time = now();
        }

        if ($isSave) {
            $this->save();
        }
    }

    public function setStatus($status)
    {
        return $this->status = $status;
    }

    public function syncWithdrawIsSuccess()
    {
        $user = $this->user;
        if (!is_null($user)) {
            $user->withdraws()->ofPlatform(Withdraw::JDJR_PLATFORM)->wating()->get()->each(function ($item) {
                $orderId = Str::orderedUuid();
                $item->settleSuccess($orderId);
            });
        }
    }

    public function scopeStatus($query, $status)
    {
        $status = is_array($status) && count($status) == 1 ? $status[0] : $status;

        if ($status == self::DZ_REGISTERED_STATUS_CODE) {
            return $query->where('status', '>', self::UN_REGISTERED_STATUS_CODE);
        }

        return is_array($status) ? $query->whereIn('status', $status) : $query->where('status', $status);
    }

    public static function findByDid($did)
    {
        return JDJR::queryDid($did)->first();
    }

    public static function encodeDid($phone)
    {
        return strtoupper(md5($phone));
    }
}
