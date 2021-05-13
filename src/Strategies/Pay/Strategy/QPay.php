<?php
namespace Haxibiao\Wallet\Strategies\Pay\Strategy;

use Haxibiao\Breeze\Exceptions\ErrorCode;
use Haxibiao\Helpers\utils\QPayUtils;
use Haxibiao\Wallet\Exceptions\PayPlatformBalanceNotEnoughException;
use Haxibiao\Wallet\Strategies\Pay\RequestResult\TransferResult;
use Haxibiao\Wallet\Strategies\Pay\Strategy\PayStrategy;
use Illuminate\Support\Arr;
use Yansongda\Pay\Pay;

class QPay extends PayStrategy
{
    protected $payInstance;

    /**
     * 转账
     *
     * @param array $bizData
     * @param string $bizData['systemBizNo']
     * @param float $bizData['amount']
     * @param string $bizData['pay_id']
     * @param string $bizData['remark']
     * @return TransferResult
     */
    public function transfer(array $bizData): TransferResult
    {
        $transferInfo      = QPay::buildTransferInfo(...func_get_args());
        $this->payInstance = is_null($this->payInstance) ? new QPayUtils : $this->payInstance;
        $res               = $this->payInstance->transfer($transferInfo);

        return $this->makeResult($res);
    }

    public static function buildTransferInfo($bizData)
    {
        //QQ钱包 amount 单位:分
        $amount = $bizData['amount'] * 100;
        return [
            'total_fee' => $amount,
            'outBizNo'  => $bizData['systemBizNo'],
            'openid'    => $bizData['pay_id'],
            'memo'      => $bizData['remark'] ?? '',
        ];
    }

    public function makeResult($response)
    {
        $isBalanceNotEnough = Arr::get($response, 'err_code') == 'NOTENOUGH';
        throw_if($isBalanceNotEnough, PayPlatformBalanceNotEnoughException::class, '', ErrorCode::PAY_SYSTEM_BALANCE_NOT_ENOUGH);

        $ret = new TransferResult($response);

        if (isset($response['transaction_id'])) {
            $ret->setOrderId($response['transaction_id']);
        }

        if (isset($response['err_code_des'])) {
            switch ($response['err_code_des']) {
                case 'APPID_OPENID_ERROR':
                    $msg = 'QQ账户不存在!';
                    break;
                case 'TRANSFER_FAIL':
                    $msg = 'QQ账户异常!';
                    break;
                case 'REALNAME_CHECK_ERROR':
                    $msg = 'QQ账户未实名!';
                    break;
                case 'NOTENOUGH':
                    $msg = '支付系统余额不足!';
                    break;
                default:
                    $msg = '未知错误';
                    break;
            }

            $ret->setMsg($msg);
        }

        return $ret;
    }
}
