<?php
namespace Haxibiao\Wallet\Strategies\Pay\Strategy;

use Exception;
use Haxibiao\Breeze\Exceptions\ErrorCode;
use Haxibiao\Helpers\utils\SiteUtils;
use Haxibiao\Wallet\Exceptions\PayPlatformBalanceNotEnoughException;
use Haxibiao\Wallet\Strategies\Pay\RequestResult\TransferResult;
use Haxibiao\Wallet\Strategies\Pay\Strategy\PayStrategy;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Yansongda\Pay\Pay;

class HashSitePay extends PayStrategy
{
    protected $payInstance;

    /**
     * 转账
     *
     * @param array $bizData
     * @param string $bizData['uuid]
     * @param string $bizData['transfer_to_domain]
     * @param string $bizData['transfer_to_site_userid']
     * @param integer $bizData['system_userid']
     * @param float $bizData['amount']
     * @return TransferResult
     */
    public function transfer($bizData): TransferResult
    {
        $uuid      = $bizData['uuid'];
        $result    = [];
        $siteUtils = new SiteUtils($bizData['transfer_to_domain']);
        //先同步一下双方UUID
        try {
            if (!empty($uuid)) {
                $siteUtils->syncUserUUID($bizData['transfer_to_site_userid'], $uuid);
            }
        } catch (Exception $ex) {
            Log::error($ex);
        }
        //转账 -> 懂得赚账户
        $res = $siteUtils->transfer($bizData['transfer_to_site_userid'], $bizData['system_userid'], $bizData['amount']);

        return $this->makeResult($res);
    }

    public function makeResult($response)
    {
        $isBalanceNotEnough = Arr::get($response, 'err_code') == 'NOTENOUGH';
        throw_if($isBalanceNotEnough, PayPlatformBalanceNotEnoughException::class, '', ErrorCode::PAY_SYSTEM_BALANCE_NOT_ENOUGH);

        $ret = new TransferResult($response);

        if (isset($response['receipt'])) {
            $ret->setOrderId($response['receipt']);
        }

        if (isset($response['message'])) {
            $ret->setMsg($response['message']);
        }

        return $ret;
    }
}
