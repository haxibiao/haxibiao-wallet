<?php

namespace Haxibiao\Wallet\Tests\Feature\GraphQL;

use App\User;
use Haxibiao\Breeze\GraphQLTestCase;
use Haxibiao\Wallet\Wallet;
use Haxibiao\Wallet\Withdraw;
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
            'amount'    => 0,
        ]);
    }

    /**
     * @group testGetRechargeSignature
     */
    protected function testGetRechargeSignature()
    {
        $query = file_get_contents(__DIR__ . '/Recharge/getRechargeSignatureMutation.graphql');

        $variables = [
            "amount"   => 0.01,
            "platform" => "ALIPAY",
        ];
        $this->startGraphQL($query, $variables, $this->getHeaders($this->user));
    }

    /**
     * @group testRechargesQuery
     */
    public function testRechargesQuery()
    {
        $query = file_get_contents(__DIR__ . '/Recharge/rechargesQuery.graphql');

        $variables = [
            "user_id" => $this->user->id,
        ];
        $this->startGraphQL($query, $variables, $this->getHeaders($this->user));
    }

    /**
     * @group testRechargeQuery
     */
    public function testRechargeQuery()
    {
        $query = file_get_contents(__DIR__ . '/Recharge/rechargeQuery.graphql');

        $variables = [
            "trade_no" => "test",
        ];
        $this->startGraphQL($query, $variables, $this->getHeaders($this->user));
    }

    /**
     * @group testSetWalletPaymentInfoMutation
     */
    public function testSetWalletPaymentInfoMutation()
    {
        $query = file_get_contents(__DIR__ . '/Wallet/setWalletPaymentInfoMutation.graphql');

        $variables = [
            "input" => [
                'pay_account' => "16675896695",
                'real_name'   => "中波",
            ],
        ];
        $this->startGraphQL($query, $variables, $this->getHeaders($this->user));
    }

    /**
     * @group testWithdrawQuery
     */
    public function testWithdrawQuery()
    {
        $query = file_get_contents(__DIR__ . '/Withdraw/withdrawQuery.graphql');

        $variables = [
            "id" => $this->withdraw->id,
        ];
        $this->startGraphQL($query, $variables, $this->getHeaders($this->user));
    }

    /**
     * @group testWithdrawsQuery
     */
    public function testWithdrawsQuery()
    {
        $query = file_get_contents(__DIR__ . '/Withdraw/withdrawsQuery.graphql');

        $variables = [
            "wallet_id" => $this->wallet->id,
        ];
        $this->startGraphQL($query, $variables, $this->getHeaders($this->user));
    }

    /**
     * @group testGetWithdrawAmountList
     */
    public function testGetWithdrawAmountList()
    {
        $query = file_get_contents(__DIR__ . '/Withdraw/getWithdrawAmountListQuery.graphql');

        $variables = [];
        $this->startGraphQL($query, $variables, $this->getHeaders($this->user));
    }
}
