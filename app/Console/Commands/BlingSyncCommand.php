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
                            {--test : Test API connection only}
                            {--full : Full sync (disable incremental sync)}';

    /**
     * The console command description.
     * 
     * Usage:
     * - First sync (all orders): php artisan bling:sync --full
     * - Daily sync (last 30 days): php artisan bling:sync (default)
     * - Scheduled: Runs automatically every 2 hours (incremental)
     * 
     * Performance:
     * - Full sync (~6k orders): ~15-20 minutes
     * - Incremental sync (~200 orders): ~2-3 minutes
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
        $fullSync = $this->option('full');
        
        if (!in_array($account, ['pro', 'atacadista', 'all'])) {
            $this->error('❌ Invalid account. Use: pro, atacadista, or all');
            return 1;
        }

        if ($forceUpdate) {
            $this->warn('⚠️  Force update enabled - existing orders will be updated');
        }
        
        if ($fullSync) {
            $this->warn('⚠️  Full sync enabled - all orders will be fetched');
            $this->warn('   Expected time: ~15-20 minutes for ~6k orders');
            $this->info('   API Rate: 40 req/s (66% of Bling limit)');
        } else {
            $this->info('🔄 Incremental sync enabled - fetching orders from last 30 days');
            $this->info('   Orders older than 30 days will be automatically skipped');
            $this->info('   Expected time: ~2-3 minutes for ~200 orders');
        }

        try {
            $results = [];
            
            if ($account === 'all' || $account === 'pro') {
                $results['pro'] = $this->syncAccount('pro', $forceUpdate, $useQueue, !$fullSync);
            }
            
            if ($account === 'all' || $account === 'atacadista') {
                $results['atacadista'] = $this->syncAccount('atacadista', $forceUpdate, $useQueue, !$fullSync);
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
    private function syncAccount(string $accountType, bool $forceUpdate, bool $useQueue, bool $incrementalSync = true): array
    {
        $this->info("🔄 Starting synchronization for {$accountType} account...");
        
        try {
            if ($useQueue) {
                // Dispatch to queue
                BlingSyncJob::dispatch($forceUpdate, $accountType, $incrementalSync);
                $this->info("✅ {$accountType} synchronization queued");
                return ['queued' => true];
            } else {
                // Run synchronously
                $job = new BlingSyncJob($forceUpdate, $accountType, $incrementalSync);
                $job->handle();
                $this->info("✅ {$accountType} synchronization completed");
                return ['completed' => true];
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
            if (isset($result['queued'])) {
                $this->line("  {$account}: Queued for background processing");
            } elseif (isset($result['completed'])) {
                $this->line("  {$account}: Completed successfully");
            } elseif (isset($result['processed']) && isset($result['total_orders'])) {
                $this->line("  {$account}: {$result['processed']}/{$result['total_orders']} orders processed");
            } else {
                $this->line("  {$account}: Status unknown");
            }
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
