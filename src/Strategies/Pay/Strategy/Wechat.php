<?php
namespace Haxibiao\Wallet\Strategies\Pay\Strategy;

use Haxibiao\Breeze\Exceptions\ErrorCode;
use Haxibiao\Wallet\Exceptions\PayPlatformBalanceNotEnoughException;
use Haxibiao\Wallet\Strategies\Pay\RequestResult\TransferResult;
use Haxibiao\Wallet\Strategies\Pay\Strategy\PayStrategy;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Yansongda\Pay\Pay;

class Wechat extends PayStrategy
{
    const WITHDRAW_SERVER_IP = '203.195.161.189';

    /**
     * 转账
     *
     * @param array $bizData
     * @param string $bizData['systemBizNo']
     * @param float $bizData['amount']
     * @param string $bizData['pay_id']
     * @param string $bizData['real_name']
     * @param string $bizData['remark']
     * @return TransferResult
     */
    public function transfer(array $bizData): TransferResult
    {
        $transferInfo = Wechat::buildTransferInfo($bizData);
        try {
            $res = Pay::wechat(config('pay.wechat'))->transfer($transferInfo);
        } catch (\Exception $ex) {
            $res = $ex->raw ?? null;
        }

        return $this->makeResult($res);
    }

    public static function buildTransferInfo($bizData)
    {
        //微信平台 amount 单位:分
        $amount = $bizData['amount'] * 100;

        return [
            'partner_trade_no' => $bizData['systemBizNo'],
            'openid'           => $bizData['pay_id'],
            'check_name'       => 'NO_CHECK',
            're_user_name'     => $bizData['realName'] ?? '',
            'amount'           => $amount,
            'desc'             => $bizData['remark'] ?? '',
            'type'             => 'app',
            'spbill_create_ip' => self::WITHDRAW_SERVER_IP,
        ];
    }

    public function makeResult($response)
    {
        $isBalanceNotEnough = Arr::get($response, 'err_code') == 'NOTENOUGH';
        throw_if($isBalanceNotEnough, PayPlatformBalanceNotEnoughException::class, '', ErrorCode::PAY_SYSTEM_BALANCE_NOT_ENOUGH);

        $ret = new TransferResult($response);

        if (isset($response['payment_no'])) {
            $ret->setOrderId($response['payment_no']);
        }

        if (isset($response['err_code_des'])) {
            $msg = $response['err_code_des'];
            switch ($response['err_code']) {
                case 'AMOUNT_LIMIT':
                    $msg = '转账失败,付款金额超出限制!';
                    break;
                case 'PARAM_ERROR':
                    $msg = '转账失败,参数错误!';
                    break;
            }

            // 此处openid不错在,没有指定错误码,暂时通过描述信息去判断
            $msg = Str::contains($response['err_code_des'], 'openid输入有误') ? '微信账户不存在!' : $msg;

            $ret->setMsg($msg);
        }

        return $ret;
    }
}
