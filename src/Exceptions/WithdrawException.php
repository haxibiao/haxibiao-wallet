<?php

namespace Haxibiai\Wallet\Exceptions;

use Exception;
use Haxibiao\Breeze\Exceptions\ErrorCode;

class WithdrawException extends Exception
{
    public function __construct($code = ErrorCode::FAILURE_STATUS, $msg = '')
    {
        $msg = !empty($msg) ? $msg : ErrorCode::getMsg($code);
        parent::__construct($msg, $code);
    }
}
