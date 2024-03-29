<?php
namespace Haxibiao\Wallet\Strategies\Pay;

use Illuminate\Support\Str;

class PayStrategyMaker
{
    private static $instance = null;

    private $strategys = [];

    private $strategyName;

    private function __construct()
    {
        //
    }

    public static function buildInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public static function setStrategy(string $name)
    {
        $instance = self::buildInstance();
        if (!isset($instance->strategys[$name])) {
            $strategy                   = sprintf('%s\Strategy\%s', __NAMESPACE__, Str::studly($name));
            $instance->strategys[$name] = new $strategy;
        }
        $instance->strategyName = $name;

        return $instance->strategys[$name];
    }
}
