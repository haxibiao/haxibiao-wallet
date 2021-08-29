<?php
namespace Haxibiao\Wallet\Strategies\Pay\RequestResult;

class BaseResult
{
    protected $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    /**
     * Get the value of result
     */ 
    public function getResult()
    {
        return $this->result;
    }
}
