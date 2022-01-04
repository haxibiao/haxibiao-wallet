<?php

use Illuminate\Contracts\Routing\Registrar as RouteRegisterContract;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'pay'], function (RouteRegisterContract $api) {
    Route::any('/alipay-notify', PayController::class . '@alipayNotify');
    Route::any('/alipay-return', PayController::class . '@alipayReturn');

    Route::any('/qq-notify', PayController::class . '@qqNotify');
    Route::any('/wechat-notify', PayController::class . '@wechatNotify');
});