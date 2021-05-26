<?php

if (!function_exists('wallet_path')) {
    function wallet_path($path)
    {
        return __DIR__ . "/../../" . $path;
    }
}
