<?php

namespace Haxibiao\Wallet\Tests\Feature\GraphQL;

use App\User;
use Haxibiao\Breeze\GraphQLTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class GoldTest extends GraphQLTestCase
{
    use DatabaseTransactions;

    /**
     * @group testGoldsQuery
     */
    public function testGoldsQuery()
    {
        $query  = file_get_contents(__DIR__ . '/Gold/goldsQuery.graphql');
        $user = User::factory()->create([
            'api_token' => str_random(60),
            'account'   => rand(10000000000, 99999999999),
        ]);
        $variables = [
            "user_id"  => $user->id,
        ];
        $this->runGuestGQL($query, $variables,$this->getHeaders($user));
    }
}