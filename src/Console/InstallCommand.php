<?php

namespace Haxibiao\Wallet\Console;

use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Support\Str;

class InstallCommand extends Command
{

    /**
     * The name and signature of the Console command.
     *
     * @var string
     */
    protected $signature = 'wallet:install';

    /**
     * The Console command description.
     *
     * @var string
     */
    protected $description = '安装 haxibiao-wallet';

    /**
     * Execute the Console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->comment("复制 stubs ...");
        copy(__DIR__ . '/stubs/Recharge.stub', app_path('Recharge.php'));

        $this->comment('发布资源...');
        $this->call('wallet:publish', ['--force' => true]);

        $this->comment('迁移数据库变化...');
        $this->call('migrate');

        $this->info('Haxibiao Wallet 安装 successfully.');
        $this->warn('安装成功，请检查.env中支付相关信息，详情参考 config/pay.php');
    }

    /**
     * Register the Wallet service provider in the application configuration file.
     *
     * @return void
     */
    protected function registerWalletServiceProvider()
    {
        $namespace = Str::replaceLast('\\', '', $this->getAppNamespace());

        file_put_contents(config_path('app.php'), str_replace(
            "{$namespace}\\Providers\EventServiceProvider::class," . PHP_EOL,
            "{$namespace}\\Providers\EventServiceProvider::class," . PHP_EOL . "        Haxibiao\Wallet\WalletServiceProvider::class," . PHP_EOL,
            file_get_contents(config_path('app.php'))
        ));
    }

    protected function getAppNamespace()
    {
        return Container::getInstance()->getNamespace();
    }
}
