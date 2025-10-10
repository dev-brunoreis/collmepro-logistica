<?php

namespace App\Console\Commands;

use App\Models\OrderSummary;
use App\Services\CarrierService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateCarrierDisplayCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'carriers:update-display
                          {--dry-run : Run in dry-run mode without making changes}
                          {--chunk=500 : Number of records to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update logistics_provider field with formatted carrier display names';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');

        $this->info('Starting carrier display update...');
        
        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode - no changes will be made');
        }

        $totalRecords = OrderSummary::whereHas('invoice.tracking')->count();
        $this->info("Total records to process: {$totalRecords}");

        $processedCount = 0;
        $updatedCount = 0;
        $unchangedCount = 0;
        $errorCount = 0;

        $progressBar = $this->output->createProgressBar($totalRecords);
        $progressBar->start();

        OrderSummary::whereHas('invoice.tracking')
            ->with(['invoice.tracking'])
            ->chunkById($chunkSize, function ($orders) use (
                &$processedCount,
                &$updatedCount,
                &$unchangedCount,
                &$errorCount,
                $dryRun,
                $progressBar
            ) {
                foreach ($orders as $order) {
                    try {
                        // Extract service type from tracking relationship
                        $firstTracking = $order->invoice?->tracking->first();
                        $serviceType = $firstTracking ? CarrierService::extractServiceType($firstTracking->raw_data) : null;
                        
                        // Get formatted carrier display
                        $newCarrierDisplay = CarrierService::getCarrierDisplay($serviceType);
                        
                        // Check if update is needed
                        if ($order->logistics_provider !== $newCarrierDisplay) {
                            if (!$dryRun) {
                                $order->logistics_provider = $newCarrierDisplay;
                                $order->service_type = $serviceType;
                                $order->save();
                            }
                            
                            $updatedCount++;
                            
                            // Log the change in verbose mode
                            if ($this->output->isVerbose()) {
                                $this->line("\nOrder #{$order->numero}: '{$order->logistics_provider}' -> '{$newCarrierDisplay}'");
                            }
                        } else {
                            $unchangedCount++;
                        }
                        
                        $processedCount++;
                    } catch (\Exception $e) {
                        $errorCount++;
                        $this->error("\nError processing order #{$order->numero}: " . $e->getMessage());
                    }
                    
                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $this->newLine(2);

        // Display summary
        $this->info('Update completed!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed', $processedCount],
                ['Updated', $updatedCount],
                ['Unchanged', $unchangedCount],
                ['Errors', $errorCount],
            ]
        );

        // Display carrier distribution
        $this->newLine();
        $this->info('Carrier distribution after update:');
        
        $distribution = OrderSummary::whereNotNull('logistics_provider')
            ->where('logistics_provider', '!=', '-')
            ->select('logistics_provider', DB::raw('count(*) as count'))
            ->groupBy('logistics_provider')
            ->orderBy('count', 'desc')
            ->get();

        $distributionData = $distribution->map(function ($item) {
            return [$item->logistics_provider, $item->count];
        })->toArray();

        $this->table(['Carrier', 'Count'], $distributionData);

        if ($dryRun) {
            $this->warn('This was a DRY-RUN. No changes were made to the database.');
            $this->info('Run without --dry-run to apply the changes.');
        }

        return self::SUCCESS;
    }
}

