<?php

namespace App\Console\Commands;

use App\Models\OrderSummary;
use Illuminate\Console\Command;

class CheckTrackingQueueCount extends Command
{
    protected $signature = 'tracking:check-queue-count';
    protected $description = 'Check how many orders would be queued for tracking refresh';

    public function handle(): int
    {
        $this->info('Checking orders that would be queued for tracking refresh...');
        $this->newLine();

        // Same query as RefreshTrackingsJob
        $orders = OrderSummary::whereNotNull('tracking_code')
            ->whereHas('invoice')
            ->whereDoesntHave('invoice.tracking', function ($query) {
                $query->where('situacao', 4); // Exclude delivered
            })
            ->where('created_at', '>=', now()->subDays(30))
            ->notCancelled()
            ->get();

        $total = $orders->count();

        $this->info("Total orders that would be queued: {$total}");
        $this->newLine();

        // Breakdown by source
        $bySource = $orders->groupBy('source')->map->count();
        $this->info('Breakdown by source:');
        foreach ($bySource as $source => $count) {
            $this->line("  - {$source}: {$count}");
        }
        $this->newLine();

        // Estimate time
        $estimatedMinutes = ceil($total * 30 / 60); // ~30s per job average
        $this->info("Estimated processing time: ~{$estimatedMinutes} minutes");
        $this->comment("(This is continuous background processing, not blocking)");

        return self::SUCCESS;
    }
}

