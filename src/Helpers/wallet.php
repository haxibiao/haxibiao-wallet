<?php

use SimpleSoftwareIO\QrCode\Facades\QrCode;

if (!function_exists('wallet_path')) {
    function wallet_path($path)
    {
        return __DIR__ . "/../../" . $path;
    }
}

/**
 * 微信扫码支付的base64 data
 */
if (!function_exists('wechat_pay_code')) {
    function wechat_pay_code($code_url)
    {
        if (class_exists("SimpleSoftwareIO\QrCode\Facades\QrCode")) {
            $qrcode = QrCode::format('png')->size(250)->encoding('UTF-8');

            if (!empty($code_url)) {
                try {
                    $qrcode = $qrcode->generate($code_url);
                    $data   = base64_encode($qrcode);
                    return "data:image/png;base64," . $data;
                } catch (\Throwable $ex) {
                }
            }
        }
        return null;
    }
}
