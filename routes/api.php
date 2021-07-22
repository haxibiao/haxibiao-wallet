<?php

use Haxibiao\Wallet\Controllers\Api\PayController;
use Illuminate\Contracts\Routing\Registrar as RouteRegisterContract;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api'], function (RouteRegisterContract $api) {
    // @ANY /api/live
    $api->group(['prefix' => 'pay'], function (RouteRegisterContract $api) {
        Route::any('/alipay-notify', PayController::class . '@alipayNotify');
        Route::any('/alipay-return', PayController::class . '@alipayReturn');
        Route::any('/wechat-notify', PayController::class . '@wechatNotify');
    });
});
