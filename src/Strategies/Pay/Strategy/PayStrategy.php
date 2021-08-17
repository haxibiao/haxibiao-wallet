<?php
namespace Haxibiao\Wallet\Strategies\Pay\Strategy;

use Haxibiao\Wallet\Strategies\Pay\Contracts\PayStrategyContract;

abstract class PayStrategy implements PayStrategyContract
{
    private static $instance = null;

    public static function buildInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self;
        }
        return self::$instance;
    }
}
