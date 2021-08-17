<?php
namespace Haxibiao\Wallet\Strategies\Pay\Contracts;

use Haxibiao\Wallet\Strategies\Pay\RequestResult\TransferResult;

interface PayStrategyContract
{
    public function transfer(array $bizData): TransferResult;
}
