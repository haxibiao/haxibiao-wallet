<?php

return [
    'alipay' => [
        'app_id'         => env('ALIPAY_PAY_APPID'),
        'notify_url'     => env('APP_URL') . '/api/pay/alipay-notify',
        'return_url'     => env('APP_URL') . '/api/pay/alipay-return',
        'private_key'    => file_get_contents(base_path('cert/alipay/private_key')),
        'ali_public_key' => file_get_contents(base_path('cert/alipay/public_key')),
        'log'            => [
            'file'     => storage_path('logs/pay/alipay.log'),
            'level'    => 'info',
            'type'     => 'daily',
            'max_file' => 30,
        ],
        'http'           => [
            'timeout'         => 30,
            'connect_timeout' => 30,
        ],
        'mode'           => 'dev',
    ],
    'wechat' => [
        'appid'       => env('WECHAT_APPID'),
        'key'         => env('WECHAT_PAY_KEY'),
        'mch_id'      => env('WECHAT_PAY_MCH_ID'),
        'cert_client' => base_path('cert/wechat/apiclient_cert.pem'),
        'cert_key'    => base_path('cert/wechat/apiclient_key.pem'),
        'notify_url'  => env('APP_URL') . '/api/pay/wechat-notify',
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
];
