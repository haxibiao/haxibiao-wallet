<?php
namespace Haxibiao\Wallet\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Haxibiao\Breeze\Exceptions\GQLException;
use Haxibiao\Wallet\Recharge;
use Yansongda\Pay\Log;
use Yansongda\Pay\Pay;

trait RechargeResolvers
{
    /**
     * 发起充值请求,获取交易平台提供的签名唤起app
     * 签名包含: 充值金额,内部交易标识,充值标题
     */
    public static function rsolveGetRechargeSignature($root, array $args, $context, $info)
    {
        $amount   = $args['amount'];
        $platform = $args['platform'];
        $remark   = $args['remark'] ?? null;
        $trade_no = $args['trade_no'] ?? null;

        $user     = getUser();
        $title    = $remark ?? sprintf('%s充值%s', config('app.name_cn'), $amount);
        $trade_no = $trade_no ?? \str_random(30);
        if ($platform == 'ALIPAY') {
            // 支付数据数组的key不能变!
            $order = [
                'total_amount' => $amount,
                'out_trade_no' => $trade_no,
                'subject'      => $title,
            ];
            $signature = Pay::alipay(config('pay.alipay'))->app($order)->getContent();

        } else if ($platform == 'WECHAT') {
            $order = [
                'out_trade_no' => $trade_no,
                'body'         => $title,
                // 微信支付单位是分
                'total_fee'    => $amount * 100,
            ];
            $signature = Pay::wechat(config('pay.wechat'))->app($order)->getContent();
        } else if ($platform == 'APPLE') {
            // 苹果不需要签名唤起支付程序
            $signature = null;
        }

        // 创建充值记录
        $recharge = Recharge::createRecharge($user->id, $trade_no, $title, $amount, $platform);
        return [
            'recharge' => $recharge,
            $platform  => $signature,
            'trade_no' => $recharge->trade_no,
        ];
    }

    /**
     * 效验苹果支付状态
     */
    public static function ResolverVerifyApplePay($receipt, $trade_no, $isSandBox)
    {
        $sendData = "{\"receipt-data\":\"$receipt\"}";
        $url      = $isSandBox ? RechaRge::APPLE_BUY_SANDBOX_URL : RechaRge::APPLE_BUY_URL;
        try {
            $client = new Client();
            $result = $client->request('post', $url, [
                'body' => $sendData,
            ])->getBody()->getContents();
            $data = json_decode($result, true);
            // 购买成功
            if ($data['status'] == 0) {
                $appleProductId = data_get($data, 'receipt.in_app')[0]['product_id'];
                $product        = Recharge::appleProductMap()[$appleProductId];
                // 完成充值
                $recharge = Recharge::completeRecharge($trade_no, Recharge::APPLE_PLATFORM, $product['amount'], $data);
                return $recharge;
            } else {
                throw new GQLException('未支付成功,请稍后再试!');
            }
        } catch (GuzzleException $e) {
            $errorMsg = 'Apple 服务端验证支付失败';
            Log::error($errorMsg, func_get_args());
            throw new GQLException($errorMsg);
        } catch (\Exception $e) {
            $errorMsg = 'apple 支付处理异常';
            Log::error($errorMsg, func_get_args());
            throw new GQLException($errorMsg);
        }
    }
}
