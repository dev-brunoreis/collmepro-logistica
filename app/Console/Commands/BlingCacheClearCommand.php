<?php

namespace App\Console\Commands;

use App\Services\CollmeProBlingService;
use App\Services\CollmeAtacadistaBlingService;
use Illuminate\Console\Command;

class BlingCacheClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bling:cache:clear
                            {--account= : Account type (pro, atacadista, or all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all cached Bling order hashes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $accountType = $this->option('account');
        
        $this->info('Clearing Bling order hash cache...');
        
        $services = [];
        
        if ($accountType === null || $accountType === 'all') {
            $services['pro'] = app(CollmeProBlingService::class);
            $services['atacadista'] = app(CollmeAtacadistaBlingService::class);
        } elseif ($accountType === 'pro') {
            $services['pro'] = app(CollmeProBlingService::class);
        } elseif ($accountType === 'atacadista') {
            $services['atacadista'] = app(CollmeAtacadistaBlingService::class);
        } else {
            $this->error("Invalid account type: {$accountType}");
            return self::FAILURE;
        }
        
        $totalCleared = 0;
        
        foreach ($services as $account => $service) {
            $this->info("Clearing cache for account: {$account}");
            $cleared = $service->clearAllOrderCache();
            $totalCleared += $cleared;
            $this->info("Cleared {$cleared} cache entries for {$account}");
        }
        
        $this->newLine();
        $this->info("✓ Total cache entries cleared: {$totalCleared}");
        
        return self::SUCCESS;
    }
}


