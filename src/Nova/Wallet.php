<?php

namespace Haxibiao\Wallet\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Resource;

class Wallet extends Resource
{
    public static $model = 'Haxibiao\Wallet\Wallet';

    // public static $displayInNavigation = false;

    public static $title = 'id';

    public static $search = [
        'id', 'real_name', 'pay_account',
    ];

    public static function label()
    {
        return "钱包";
    }
    public static $group = '交易中心';

    public function fields(Request $request)
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('用户', 'user', User::class),
            Select::make('类型', 'type')->options([
                0 => 'RMB钱包',
                1 => '金币钱包',
            ])->displayUsingLabels(),
            Text::make('余额', 'balance'),
            Text::make('提现账号', 'pay_account'),
            Text::make('真实姓名', 'real_name'),
            Text::make('提现总额', 'total_withdraw_amount'),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function cards(Request $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function filters(Request $request)
    {
        return [
            new \App\Nova\Filters\Transaction\WalletType,
        ];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function lenses(Request $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function actions(Request $request)
    {
        return [];
    }
}
