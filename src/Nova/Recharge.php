<?php

namespace Haxibiao\Wallet\Nova;

use Haxibiao\Breeze\Nova\User;
use Haxibiao\Wallet\Recharge as WalletRecharge;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Resource;

class Recharge extends Resource
{
    public static $model  = 'Haxibiao\\Wallet\\Recharge';
    public static $title  = 'id';
    public static $search = [
        'id', 'title', 'amount',
    ];

    public static $group = '交易中心';
    public static function label()
    {
        return '充值';
    }

    public function fields(Request $request)
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('用户', 'user', User::class),
            Number::make('充值金额', 'amount')->sortable(),
            Select::make('充值状态', 'status')->options(WalletRecharge::getPayStatuses())->displayUsingLabels(),
            Select::make('交易平台', 'platform')->options(WalletRecharge::getPayPlatfroms())->displayUsingLabels(),
            Text::make('充值标题', 'title'),
            Text::make('交易订单号', 'trade_no')->hideFromIndex(),
            KeyValue::make('交易平台回调数据', 'data')->hideFromIndex(),
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
        return [];
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
