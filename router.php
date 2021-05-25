<?php

use Illuminate\Support\Facades\Route;

// APIs routes.
// Route::group(
//     [
//         'prefix'     => 'api',
//         'middleware' => ['api'],
//         'namespace'  => 'Haxibiao\Wallet\Http\Api',
//     ],
//     __DIR__ . '/routes/api.php'
// );

// Web routes.
Route::group(
    [
        'middleware' => ['web'],
        'namespace'  => 'Haxibiao\Wallet\Http\Controllers',
    ],
    __DIR__ . '/routes/web.php'
);
