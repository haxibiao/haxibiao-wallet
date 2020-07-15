<?php
namespace Haxibiao\Wallet\Console;

use Illuminate\Console\Command;

class PublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:publish {--force : 强制覆盖}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '发布 haxibiao-wallet';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        // 就配置文件自定义后，不方便覆盖，更新需要单独
        // vendor:publish --tag=task-config --force=true
        $this->call('vendor:publish', [
            '--tag'   => 'wallet-config',
            '--force' => $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--tag'   => 'wallet-nova',
            '--force' => $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--tag'   => 'wallet-graphql',
            '--force' => $this->option('force'),
        ]);

    }
}
