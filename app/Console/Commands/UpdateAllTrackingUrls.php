<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tracking;
use App\Services\CollmeProBlingService;
use App\Services\CollmeAtacadistaBlingService;

class UpdateAllTrackingUrls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracking:update-all-urls {--limit=50}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update all tracking records that have NULL url_rastreamento';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        
        $trackings = Tracking::whereNull('url_rastreamento')
            ->whereNotNull('volume_id')
            ->limit($limit)
            ->get();
        
        if ($trackings->isEmpty()) {
            $this->info('No tracking records found with NULL url_rastreamento');
            return 0;
        }
        
        $this->info("Found {$trackings->count()} tracking records to update");
        $this->line('');
        
        $blingServicePro = app(CollmeProBlingService::class);
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        
        $bar = $this->output->createProgressBar($trackings->count());
        $bar->start();
        
        foreach ($trackings as $tracking) {
            try {
                $trackingData = $blingServicePro->getCompleteTrackingData((int) $tracking->volume_id);
                
                if ($trackingData) {
                    $url = $trackingData['rastreamento']['url'] ?? null;
                    
                    if ($url) {
                        $tracking->update([
                            'url_rastreamento' => $url,
                            'codigo_rastreamento' => $trackingData['rastreamento']['codigo'] ?? $tracking->codigo_rastreamento,
                            'descricao' => $trackingData['rastreamento']['descricao'] ?? $tracking->descricao,
                            'situacao' => $trackingData['rastreamento']['situacao'] ?? $tracking->situacao,
                            'raw_data' => $trackingData,
                            'last_sync_at' => now(),
                        ]);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } else {
                    $skipped++;
                }
                
                // Rate limiting: wait 1 second between requests
                sleep(1);
                
            } catch (\Exception $e) {
                $errors++;
                \Log::error("Error updating tracking {$tracking->id}: " . $e->getMessage());
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->line('');
        $this->line('');
        
        $this->info("=== UPDATE SUMMARY ===");
        $this->line("Updated: {$updated}");
        $this->line("Skipped (no URL): {$skipped}");
        $this->line("Errors: {$errors}");
        
        return 0;
    }
}

