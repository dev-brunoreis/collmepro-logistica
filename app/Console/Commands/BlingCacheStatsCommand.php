<?php

namespace App\Console\Commands;

use App\Services\CollmeProBlingService;
use App\Services\CollmeAtacadistaBlingService;
use Illuminate\Console\Command;

class BlingCacheStatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bling:cache:stats
                            {--account= : Account type (pro, atacadista, or all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show Bling cache hit/miss statistics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $accountType = $this->option('account');
        
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
        
        $this->info('Bling Cache Statistics');
        $this->newLine();
        
        foreach ($services as $account => $service) {
            $stats = $service->getCacheStats();
            
            $this->info("Account: {$account}");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Sampled Orders', $stats['sampled']],
                    ['Cache Hits', $stats['hits']],
                    ['Cache Misses', $stats['misses']],
                    ['Hit Rate', $stats['hit_rate'] . '%'],
                ]
            );
            $this->newLine();
        }
        
        return self::SUCCESS;
    }
}


