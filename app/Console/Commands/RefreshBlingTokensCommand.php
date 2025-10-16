<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\RefreshBlingTokensJob;

class RefreshBlingTokensCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bling:refresh-tokens 
                            {--account= : Specific account to refresh (pro|atacadista), or leave empty for both}';

    /**
     * The console command description.
     */
    protected $description = 'Manually refresh Bling API tokens for Pro and/or Atacadista accounts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $account = $this->option('account');

        if ($account && !in_array($account, ['pro', 'atacadista'])) {
            $this->error('Invalid account. Use: pro or atacadista');
            return 1;
        }

        $this->info('🔄 Refreshing Bling tokens...');
        $this->newLine();

        if ($account) {
            $this->info("Checking and refreshing token for {$account} account only...");
        } else {
            $this->info('Checking and refreshing tokens for all accounts...');
        }

        $this->newLine();

        // Dispatch the job synchronously
        RefreshBlingTokensJob::dispatchSync();

        $this->newLine();
        $this->info('✅ Token refresh process completed!');
        $this->newLine();
        $this->line('📋 Check the logs for details:');
        $this->line('   tail -f storage/logs/token.log');

        return 0;
    }
}

