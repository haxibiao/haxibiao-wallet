<?php
namespace Haxibiao\Wallet\Strategies\Pay\Strategy;

use Haxibiao\Breeze\Exceptions\ErrorCode;
use Haxibiao\Helpers\utils\OAuthUtils;
use Haxibiao\Wallet\Exceptions\PayPlatformBalanceNotEnoughException;
use Haxibiao\Wallet\Strategies\Pay\RequestResult\TransferResult;
use Haxibiao\Wallet\Strategies\Pay\Strategy\PayStrategy;
use Illuminate\Support\Arr;
use Yansongda\Pay\Pay;

class Alipay extends PayStrategy
{

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
        $transferInfo = Alipay::buildTransferInfo($bizData);
        try {
            $res = Pay::alipay(config('pay.alipay'))->transfer($transferInfo);
        } catch (\Exception $ex) {
            $res = $ex->raw ?? null;
        }

        // 结果反馈结果可能是嵌套
        if (isset($res['alipay_fund_trans_uni_transfer_response'])) {
            $res = $res['alipay_fund_trans_uni_transfer_response'];
        }

        return $this->makeResult($res);
    }

    public static function buildTransferInfo($bizData)
    {
        $payId = $bizData['pay_id'];
        return [
            'out_biz_no'   => $bizData['systemBizNo'],
            'biz_scene'    => 'DIRECT_TRANSFER',
            'trans_amount' => $bizData['amount'],
            'product_code' => 'TRANS_ACCOUNT_NO_PWD',
            'payee_info'   => [
                'identity'      => $payId,
                'identity_type' => OAuthUtils::isAlipayOpenId($payId) ? 'ALIPAY_USER_ID' : 'ALIPAY_LOGON_ID',
                'name'          => $bizData['real_name'],
            ],
            'remark'       => $bizData['remark'],
            'order_title'  => $bizData['remark'],
        ];
    }

    public function makeResult($response)
    {
        $isBalanceNotEnough = Arr::get($response, 'sub_code') == 'PAYER_BALANCE_NOT_ENOUGH';
        throw_if($isBalanceNotEnough, PayPlatformBalanceNotEnoughException::class, '', ErrorCode::PAY_SYSTEM_BALANCE_NOT_ENOUGH);

        $ret = new TransferResult($response);
        if (isset($response['order_id'])) {
            $ret->setOrderId($response['order_id']);
        }

        if (isset($response['sub_code'])) {
            $msg = $response['sub_msg'];
            switch ($response['sub_code']) {
                case 'PAYEE_NOT_EXIST':
                    $msg = '支付宝账户不存在!';
                    break;
            }

            $ret->setMsg($msg);
        }

        return $ret;
    }
}
