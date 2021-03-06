<?php

namespace Haxibiao\Wallet\Nova;

use App\Nova\Resource;
use App\Nova\Wallet;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;

class Transaction extends Resource
{
    public static $model = 'Haxibiao\Wallet\Transaction';

    public static $displayInNavigation = false;

    public static $title = 'id';

    public static $search = [
        'id', 'name',
    ];

    public static function label()
    {
        return "账单";
    }

    public static $group = '交易中心';

    public function fields(Request $request)
    {
        return [
            ID::make()->sortable(),
            belongsTo::make('钱包ID', 'wallet', Wallet::class),
            Text::make('类型', 'type'),
            Text::make('记录', 'remark')->asHtml(),
            Text::make('状态', 'status'),
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
            // new \App\Nova\Filters\Transaction\TransactionStatusType,
            // new \App\Nova\Filters\Transaction\TransactionType,
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
