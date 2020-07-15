# haxibiao-wallet

> haxibiao-lwallet是哈希表基于微信、支付宝、apple 开发的交易扩展包
> 欢迎大家提交代码或提出建议


## 安装步骤
1. `App/User`模型需要增加Trait: `use App\Traits\PlayWithWallet`;
2. 执行 `git submodule add http://code.haxibiao.cn/packages/haxibiao-wallet packages/haxibiao/wallet`
3. 将 `Haxibiao\Wallet\WalletServiceProvider::class,` 添加到 config/app.php
4. 执行 `composer dump`
5. 注意，请确保 config/pay.php 没有特殊的支付相关信息，否则请手动 merge 一下改动
6. 执行 `php artisan wallet:install`
7. 配置env文件以下几个参数值：
```
ALIPAY_PAY_APPID=
WECHAT_APPID=
WECHAT_SECRET=
WECHAT_PAY_KEY=
WECHAT_PAY_MCH_ID=
```
8. 配置`cert/alipay` 与 `cert/wechat` 相关支付配置信息，文件结构如下：
```
.
├── alipay
│   ├── pem
│   │   ├── private.pem
│   │   └── public.pem
│   ├── private_key
│   └── public_key
└── wechat
    ├── apiclient_cert.pem
    └── apiclient_key.pem
```

## GQL接口说明

#### Query

用户充值记录

```json
{
	recharges(user_id:8){
    data{
      id
      title
      amount
      status
      platform
    }
  }
}
```

#### Mutation

获取交易平台签名

```json
mutation{
  getRechargeSignature(amount:0.01,platform:ALIPAY){
    ALIPAY
    WECHAT
  }
}
```

交易平台（platform）参数为支付宝，返回签名即为支付宝使用的签名，微信同理

请注意：微信和支付宝的签名格式不同

## Api接口说明

|          路由          | 方式  |            说明            |
| :--------------------: | :---: | :------------------------: |
| /api/pay/alipay-notify |  any  | 支付宝交易结束回调（常用） |
| /api/pay/wechat-notify |  any  |  微信交易结束回调（常用）  |

## 其他说明

此包还在持续开发中，后续将引入 提现与兑换相关逻辑和模型，欢迎大家一起参与建设