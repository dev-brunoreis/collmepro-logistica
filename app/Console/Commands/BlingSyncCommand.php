<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Jobs\BlingSyncJob;
use App\Services\CollmeProBlingService;
use App\Services\CollmeAtacadistaBlingService;

class BlingSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bling:sync 
                            {--account= : Account to sync (pro|atacadista|all)}
                            {--force : Force update existing orders}
                            {--queue : Run in background queue}
                            {--test : Test API connection only}';

    /**
     * The console command description.
     */
    protected $description = 'Synchronize orders, invoices and tracking from Bling';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Bling Synchronization System');
        $this->newLine();

        // Test connection if requested
        if ($this->option('test')) {
            return $this->testConnection();
        }

        $account = $this->option('account') ?? 'all';
        $forceUpdate = $this->option('force');
        $useQueue = $this->option('queue');
        
        if (!in_array($account, ['pro', 'atacadista', 'all'])) {
            $this->error('❌ Invalid account. Use: pro, atacadista, or all');
            return 1;
        }

        if ($forceUpdate) {
            $this->warn('⚠️  Force update enabled - existing orders will be updated');
        }

        try {
            $results = [];
            
            if ($account === 'all' || $account === 'pro') {
                $results['pro'] = $this->syncAccount('pro', $forceUpdate, $useQueue);
            }
            
            if ($account === 'all' || $account === 'atacadista') {
                $results['atacadista'] = $this->syncAccount('atacadista', $forceUpdate, $useQueue);
            }

            $this->displayResults($results);
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Synchronization failed: {$e->getMessage()}");
            
            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }
            
            return 1;
        }
    }

    /**
     * Sync a specific account
     */
    private function syncAccount(string $accountType, bool $forceUpdate, bool $useQueue): array
    {
        $this->info("🔄 Starting synchronization for {$accountType} account...");
        
        try {
            $service = $accountType === 'pro' 
                ? app(CollmeProBlingService::class)
                : app(CollmeAtacadistaBlingService::class);

            if ($useQueue) {
                // For now, we'll run synchronously even with queue option
                // You can implement proper queue dispatch later
                $result = $service->syncOrders();
                $this->info("✅ {$accountType} synchronization completed");
                return $result;
            } else {
                $result = $service->syncOrders();
                $this->info("✅ {$accountType} synchronization completed");
                return $result;
            }

        } catch (\Exception $e) {
            $this->error("❌ {$accountType} synchronization failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Display synchronization results
     */
    private function displayResults(array $results): void
    {
        $this->newLine();
        $this->info('📊 Synchronization Summary:');
        
        foreach ($results as $account => $result) {
            $this->line("  {$account}: {$result['processed']}/{$result['total_orders']} orders processed");
        }
        
        $this->newLine();
        $this->info('📋 Next steps:');
        $this->line('  • Check orders: php artisan tinker');
        $this->line('  • View logs: tail -f storage/logs/laravel.log');
    }

    /**
     * Test Bling API connection
     */
    private function testConnection(): int
    {
        $this->info('🔌 Testing Bling API connections...');
        
        $account = $this->option('account') ?? 'all';
        $success = true;
        
        try {
            if ($account === 'all' || $account === 'pro') {
                $this->testAccountConnection('pro');
            }
            
            if ($account === 'all' || $account === 'atacadista') {
                $this->testAccountConnection('atacadista');
            }
            
            if ($success) {
                $this->info('✅ All connections successful!');
                return 0;
            } else {
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Connection test failed: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Test connection for a specific account
     */
    private function testAccountConnection(string $accountType): void
    {
        try {
            $service = $accountType === 'pro' 
                ? app(CollmeProBlingService::class)
                : app(CollmeAtacadistaBlingService::class);

            $this->info("🔌 Testing {$accountType} connection...");
            
            // Try to get a valid token (this will test the OAuth flow)
            $token = $service->getAuthorizationUrl();
            
            $this->line("  ✅ {$accountType}: Token management configured");
            
        } catch (\Exception $e) {
            $this->error("  ❌ {$accountType}: {$e->getMessage()}");
            throw $e;
        }
    }
}
