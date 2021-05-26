<?php

use Illuminate\Support\Facades\Route;

//支付调试 ?type=
Route::any('/pay/test', 'PayController@test');

//打赏支付 ?amount=&type=tip
Route::get('/pay', 'PayController@tip');

Route::any('/pay/alipay', 'PayController@alipay');
Route::any('/pay/alipay/return', 'PayController@alipayReturn');
Route::any('/pay/alipay/notify', 'PayController@alipayNotify');

Route::any('/pay/wechat', 'PayController@wechat');
Route::any('/pay/wechat/notify', 'PayController@wechatNotify');
