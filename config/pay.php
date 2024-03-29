<?php

return [
    'alipay' => [
        'app_id'              => env('ALIPAY_PAY_APPID'),
        'notify_url'          => env('APP_URL') . '/api/pay/alipay-notify',
        'return_url'          => env('APP_URL') . '/api/pay/alipay-return',
        'private_key'         => @file_get_contents('/etc/alipay/private_key'), //需要提前支付宝部署自己证书到服务器
        'ali_public_key'   => '/etc/alipay/alipayCertPublicKey_RSA2.crt',
        'app_cert_public_key' => '/etc/alipay/appCertPublicKey_2021001172621778.crt', //应用公钥证书路径
        'alipay_root_cert' => '/etc/alipay/alipayRootCert.crt',
        'log'                 => [
            'file'     => storage_path('logs/pay/alipay.log'),
            'level'    => 'info',
            'type'     => 'daily',
            'max_file' => 30,
        ],
        'http'                => [
            'timeout'         => 30,
            'connect_timeout' => 30,
        ],
        'mode'                => 'normal',
    ],
    'wechat' => [
        // 公众号 APPID - 三种APPID提供一种就可以
        'app_id'      => env('WECHAT_APPID', ''),
        // 小程序 APPID
        'miniapp_id'  => env('WECHAT_APPID', ''),
        // APP 引用的 appid
        'appid'       => env('WECHAT_APPID', ''),

        'key'         => env('WECHAT_PAY_KEY'),
        'mch_id'      => env('WECHAT_PAY_MCH_ID'),
        'cert_client' => '/etc/wechat/apiclient_cert.pem',
        'cert_key'    => '/etc/wechat/apiclient_key.pem',
        'notify_url'  => env('APP_URL') . '/pay/wechat/notify',
        'log'         => [
            'file'     => storage_path('logs/pay/wechat.log'),
            'level'    => 'debug',
            'type'     => 'daily',
            'max_file' => 30,
        ],
        'http'        => [
            'timeout'         => 30,
            'connect_timeout' => 30,
        ],
        'mode'        => 'normal',
    ],
    'qq'     => [
        'input_charset'    => 'UTF-8',
        'mch_id'           => env('QQ_MCH_ID'),
        'op_user_id'       => env('QQ_OP_USER_ID'),
        'op_user_passwd'   => env('QQ_OP_USER_PASSWD'),
        'spbill_create_ip' => env('WITHDRAW_SERVER_IP') ?? '127.0.0.1',
        'api_key'          => env('QQ_APP_KEY'),
        'appid'            => env('QQ_APP_ID'),
        'notify_url'       => env('APP_URL') . '/api/pay/qq-notify',
    ],
];