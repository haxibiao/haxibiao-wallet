# haxibiao-wallet

> haxibiao-wallet 是哈希表基于微信、支付宝、apple 开发的交易扩展包
> 欢迎大家提交代码或提出建议

## 安装步骤

1. `composer.json`改动如下：
   在`repositories`中添加 vcs 类型远程仓库指向
   `http://code.haxibiao.cn/packages/haxibiao-cms`
2. 执行`composer require haxibiao/cms`
3. 执行 `php artisan wallet:install`
4. 配置 env 文件以下几个参数值：

```
ALIPAY_PAY_APPID=
WECHAT_APPID=
WECHAT_SECRET=
WECHAT_PAY_KEY=
WECHAT_PAY_MCH_ID=
```

5. 配置`cert/alipay` 与 `cert/wechat` 相关支付配置信息，文件结构如下：

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

## GQL 接口说明

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

## Api 接口说明

|          路由          | 方式 |            说明            |
| :--------------------: | :--: | :------------------------: |
| /api/pay/alipay-notify | any  | 支付宝交易结束回调（常用） |
| /api/pay/wechat-notify | any  |  微信交易结束回调（常用）  |

## 其他说明

此包还在持续开发中，后续将引入 提现与兑换相关逻辑和模型，欢迎大家一起参与建设
