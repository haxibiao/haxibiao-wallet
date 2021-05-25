<?php

use Illuminate\Support\Facades\Route;

Route::any('/pay/alipay', 'PayController@alipay');
Route::any('/pay/alipay/return', 'PayController@alipayReturn');
Route::any('/pay/alipay/notify', 'PayController@alipayNotify');

Route::any('/pay/wechat', 'PayController@wechat');
Route::any('/pay/wechat/notify', 'PayController@wechatNotify');
