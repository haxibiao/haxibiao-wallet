<?php

namespace Haxibiao\Wallet\Tests\Feature\GraphQL;

use App\User;
use App\Wallet;
use App\Withdraw;
use Haxibiao\Breeze\GraphQLTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class WalletTest extends GraphQLTestCase
{
    use DatabaseTransactions;

    protected $user;
    protected $wallet;
    protected $withdraw;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory([
            'api_token' => str_random(60),
            'account'   => rand(10000000000, 99999999999),
        ])->create();
        $this->wallet = Wallet::create([
            'user_id' => $this->user->id,
        ]);
        $this->withdraw = Withdraw::create([
            'wallet_id' => $this->wallet->id,
            'amount' => 0,
        ]);
    }

    /**
     * @group testGetRechargeSignature
     */
    protected function testGetRechargeSignature()
    {
        $query  = file_get_contents(__DIR__ . '/recharge/getRechargeSignatureMutation.gql');
        
        $variables = [
            "amount"  => 0.01,
            "platform" => "ALIPAY"
        ];
        $this->runGQL($query, $variables,$this->getHeaders($this->user));
    }

    /**
     * @group testRechargesQuery
     */
    public function testRechargesQuery()
    {
        $query  = file_get_contents(__DIR__ . '/recharge/rechargesQuery.gql');
        
        $variables = [
            "user_id"  => $this->user->id
        ];
        $this->runGQL($query, $variables,$this->getHeaders($this->user));
    }

    /**
     * @group testRechargeQuery
     */
    public function testRechargeQuery()
    {
        $query  = file_get_contents(__DIR__ . '/recharge/rechargeQuery.gql');
        
        $variables = [
            "trade_no"  => "test"
        ];
        $this->runGQL($query, $variables,$this->getHeaders($this->user));
    }

    /**
     * @group testSetWalletPaymentInfoMutation
     */
    public function testSetWalletPaymentInfoMutation()
    {
        $query  = file_get_contents(__DIR__ . '/wallet/setWalletPaymentInfoMutation.gql');
        
        $variables = [
            "input"  => [
                'pay_account'=>"16675896695",
                'real_name'=> "中波",
            ]
        ];
        $this->runGQL($query, $variables,$this->getHeaders($this->user));
    }

    /**
     * @group testWithdrawQuery
     */
    public function testWithdrawQuery()
    {
        $query  = file_get_contents(__DIR__ . '/withdraw/withdrawQuery.gql');
        
        $variables = [
            "id"  => $this->withdraw->id,
        ];
        $this->runGQL($query, $variables,$this->getHeaders($this->user));
    }

    /**
     * @group testWithdrawsQuery
     */
    public function testWithdrawsQuery()
    {
        $query  = file_get_contents(__DIR__ . '/withdraw/withdrawsQuery.gql');
        
        $variables = [
            "wallet_id"  => $this->wallet->id,
        ];
        $this->runGQL($query, $variables,$this->getHeaders($this->user));
    }

    /**
     * @group testGetWithdrawAmountList
     */
    public function testGetWithdrawAmountList()
    {
        $query  = file_get_contents(__DIR__ . '/withdraw/getWithdrawAmountListQuery.gql');
        
        $variables = [];
        $this->runGQL($query, $variables,$this->getHeaders($this->user));
    }
}