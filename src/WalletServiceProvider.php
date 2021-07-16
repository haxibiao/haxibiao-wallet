<?php

namespace Haxibiao\Wallet;

use Haxibiao\Wallet\Console\DealWaitingWithdraw;
use Haxibiao\Wallet\Console\InstallCommand;
use Haxibiao\Wallet\Console\PublishCommand;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WalletServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
    ];

    /**
     * Boorstrap the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->bindObservers();
        $this->bindListeners();

        Relation::morphMap([
            'recharges' => '\Haxibiao\Wallet\Recharge',
        ]);

        //安装时 vendor:publish 用
        if ($this->app->runningInConsole()) {
            // 注册 migrations.
            $this->loadMigrationsFrom($this->app->make('path.haxibiao-wallet.migrations'));

            // 发布配置文件.
            $this->publishes([
                $this->app->make('path.haxibiao-wallet.config') => $this->app->configPath('/'),
            ], 'wallet-config');

            // 发布 graphql
            $this->publishes([
                __DIR__ . '/../graphql' => base_path('graphql/wallet'),
            ], 'wallet-graphql');
        }

        //注册Api路由
        $this->registerRoutes();

        // Register Commands
        $this->registerCommands();
    }

    protected function apiRoutesConfiguration()
    {
        return [
            // 'namespace' => 'Haxibiao\Live\Http\Controllers\Api',
            // 'prefix'    => 'api',
        ];
    }

    public function bindObservers()
    {
        \Haxibiao\Wallet\Withdraw::observe(\Haxibiao\Wallet\Observers\WithdrawObserver::class);
    }

    public function bindListeners()
    {
        \Illuminate\Support\Facades\Event::listen(
            'Haxibiao\Wallet\Events\WithdrawalDone',
            'Haxibiao\Wallet\Listeners\InvitationReward'
        );
        \Illuminate\Support\Facades\Event::listen(

            'Haxibiao\Wallet\Events\WithdrawalDone',
            'Haxibiao\Wallet\Listeners\SendWithdrawNotification'
        );
    }

    protected function registerCommands()
    {
        $this->commands([
            InstallCommand::class,
            PublishCommand::class,
            DealWaitingWithdraw::class,
        ]);
    }

    /**
     * Register the package routes.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        //加载路由
        $this->loadRoutesFrom(
            $this->app->make('path.haxibiao-wallet') . '/router.php'
        );
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //帮助函数
        $src_path = __DIR__;
        foreach (glob($src_path . '/Helpers/*.php') as $filename) {
            require_once $filename;
        }

        $this->bindPathsInContainer();

        // Merge config.
        if (!app()->configurationIsCached()) {
            $this->mergeConfigFrom(
                $this->app->make('path.haxibiao-wallet.config') . '/withdraw.php', 'withdraw'
            );
            $this->mergeConfigFrom(
                $this->app->make('path.haxibiao-wallet.config') . '/pay.php', 'pay'
            );
        }

        //合并view paths
        if (!app()->configurationIsCached()) {
            $view_paths = array_merge(
                //APP 的 views 最先匹配
                config('view.paths'),
                [wallet_path('resources/views')]
            );
            config(['view.paths' => $view_paths]);
        }

    }

    /**
     * Bind paths in container.
     *
     * @return void
     */
    protected function bindPathsInContainer()
    {
        foreach ([
            'path.haxibiao-wallet'            => $root = dirname(__DIR__),
            'path.haxibiao-wallet.config'     => $root . '/config',
            'path.haxibiao-wallet.graphql'    => $root . '/graphql',
            'path.haxibiao-wallet.database'   => $database = $root . '/database',
            'path.haxibiao-wallet.migrations' => $database . '/migrations',
            'path.haxibiao-wallet.seeds'      => $database . '/seeds',
        ] as $abstract => $instance) {
            $this->app->instance($abstract, $instance);
        }
    }

    /**
     * Get the events and handlers.
     *
     * @return array
     */
    public function listens()
    {
        return $this->listen;
    }
}
