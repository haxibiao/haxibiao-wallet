<?php
namespace Haxibiao\Wallet\Traits;

use App\Exceptions\GQLException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Haxibiao\Wallet\Recharge;
use Yansongda\Pay\Log;
use Yansongda\Pay\Pay;

trait RechargeResolvers
{
    /**
     * 发起充值请求,获取交易平台提供的签名唤起app
     * 签名包含: 充值金额,内部交易标识,充值标题
     */
    public static function rsolverGetRechargeSignature($amount, $platform)
    {
        $user     = getUser();
        $title    = sprintf('%s充值%s', config('app.name_cn'), $amount);
        $trade_no = \str_random(30);
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
        }

        // 创建充值记录
        $recharge = Recharge::createRecharge($user->id, $trade_no, $title, $amount, $platform);
        return [
            $platform  => $signature,
            'trade_no' => $recharge->trade_no,
        ];
    }

    /**
     * 效验苹果支付状态
     */
    public function ResolverVerifyApplePay($receipt, $trade_no)
    {
        $sendData = '{"receipt-data":"' . $receipt . '"}';
        try {
            $client = new Client();
            $result = $client->request('post', "https://buy.itunes.apple.com/verifyReceipt", [
                'body' => $sendData,
            ])->getBody()->getContents();
            $data = json_decode($result, true);
            info($data);
            // 判断是否购买成功
            if ($data['status'] == 0) {
                return $data;
            } else {
                throw new GQLException('未支付成功,请稍后再试~');
            }
        } catch (GuzzleException $e) {
            //网络请求异常,重新处理
            if ($this->verify <= 3) {
                Log::warning('Apple 验证支付失败，重试' . $this->verify . '次', func_get_args());
                $this->verify += 1;
                $this->verifyApplePay($receipt);
            }
        } catch (\Exception $e) {
            Log::warning('apple 支付处理异常', func_get_args());
            return false;
        }
    }
}
