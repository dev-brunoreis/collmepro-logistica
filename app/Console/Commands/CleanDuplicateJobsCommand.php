<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanDuplicateJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'queue:clean-duplicates {--queue=invoices : Queue to clean} {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     */
    protected $description = 'Clean duplicate jobs from the specified queue';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $queue = $this->option('queue');
        $dryRun = $this->option('dry-run');

        $this->info("Cleaning duplicate jobs from queue: {$queue}");

        $jobs = DB::table('jobs')->where('queue', $queue)->get(['id', 'payload']);
        
        if ($jobs->isEmpty()) {
            $this->info('No jobs found in the queue.');
            return 0;
        }

        $uniqueIds = [];
        $duplicates = [];
        $processed = 0;

        foreach ($jobs as $job) {
            $processed++;
            
            try {
                $payload = json_decode($job->payload, true);
                $command = unserialize($payload['data']['command']);
                
                // Get the unique ID based on job type
                $uniqueId = $this->getUniqueId($command);
                
                if (isset($uniqueIds[$uniqueId])) {
                    $duplicates[] = $job->id;
                    $this->line("Duplicate found: {$uniqueId} (Job ID: {$job->id})");
                } else {
                    $uniqueIds[$uniqueId] = $job->id;
                }
            } catch (\Exception $e) {
                $this->warn("Error processing job {$job->id}: {$e->getMessage()}");
            }
        }

        $this->info("Processed {$processed} jobs");
        $this->info("Found " . count($duplicates) . " duplicate jobs");

        if (empty($duplicates)) {
            $this->info('No duplicates found.');
            return 0;
        }

        if ($dryRun) {
            $this->info('DRY RUN: Would delete ' . count($duplicates) . ' duplicate jobs');
            return 0;
        }

        if ($this->confirm('Do you want to delete these duplicate jobs?')) {
            $deleted = DB::table('jobs')->whereIn('id', $duplicates)->delete();
            $this->info("Deleted {$deleted} duplicate jobs");
            
            Log::info('CleanDuplicateJobsCommand: Deleted duplicate jobs', [
                'queue' => $queue,
                'deleted_count' => $deleted,
                'duplicate_ids' => $duplicates,
            ]);
        } else {
            $this->info('Operation cancelled.');
        }

        return 0;
    }

    /**
     * Get unique ID for different job types
     */
    private function getUniqueId($command): string
    {
        $className = get_class($command);
        
        switch ($className) {
            case 'App\Jobs\SyncInvoiceJob':
                $reflection = new \ReflectionClass($command);
                $property = $reflection->getProperty('orderSummaryId');
                $property->setAccessible(true);
                $orderId = $property->getValue($command);
                return "sync-invoice-{$orderId}";
                
            case 'App\Jobs\ExtractTrackingJob':
                $reflection = new \ReflectionClass($command);
                $property = $reflection->getProperty('orderSummaryId');
                $property->setAccessible(true);
                $orderId = $property->getValue($command);
                return "extract-tracking-{$orderId}";
                
            case 'App\Jobs\SyncSellerJob':
                $reflection = new \ReflectionClass($command);
                $property = $reflection->getProperty('orderSummaryId');
                $property->setAccessible(true);
                $orderId = $property->getValue($command);
                return "sync-seller-{$orderId}";
                
            default:
                // Fallback to job ID for unknown job types
                $id = property_exists($command, 'id') ? $command->id : 'unknown';
                return "unknown-{$id}";
        }
    }
}
