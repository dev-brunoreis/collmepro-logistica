<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Jobs\BlingSyncJob;

class BlingSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bling:sync 
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

        $forceUpdate = $this->option('force');
        if ($forceUpdate) {
            $this->warn('⚠️  Force update enabled - existing orders will be updated');
        }

        $useQueue = $this->option('queue');
        
        try {
            if ($useQueue) {
                // Dispatch job to queue
                BlingSyncJob::dispatch($forceUpdate);
                
                $this->info('✅ Synchronization job dispatched to queue');
                $this->info('📊 Monitor progress with: php artisan queue:work');
                
            } else {
                // Run synchronously
                $this->info('🔄 Starting synchronization...');
                
                $job = new BlingSyncJob($forceUpdate);
                $job->handle(app(\App\Services\BlingService::class));
                
                $this->info('✅ Synchronization completed successfully');
            }

            $this->newLine();
            $this->info('📋 Next steps:');
            $this->line('  • Check orders: php artisan tinker');
            $this->line('  • View logs: tail -f storage/logs/laravel.log');
            
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
     * Test Bling API connection
     */
    private function testConnection(): int
    {
        $this->info('🔌 Testing Bling API connection...');
        
        try {
            $blingService = app(\App\Services\BlingService::class);
            $result = $blingService->testConnection();
            
            if ($result['success']) {
                $this->info('✅ Connection successful!');
                $this->newLine();
                $this->info('📊 API Status:');
                $this->line("  Status: Connected");
                $this->line("  Message: {$result['message']}");
                
                return 0;
            } else {
                $this->error("❌ Connection failed: {$result['error']}");
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Connection test failed: {$e->getMessage()}");
            return 1;
        }
    }
}
