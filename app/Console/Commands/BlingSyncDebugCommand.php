<?php

namespace App\Console\Commands;

use App\Models\OrderSummary;
use App\Services\CollmeProBlingService;
use App\Services\CollmeAtacadistaBlingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BlingSyncDebugCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bling:sync:debug {order_id : The order ID or bling_id}
                            {--bling-id : Treat order_id as bling_id instead of internal ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug sync process for a specific order';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $orderId = $this->argument('order_id');
        $isBlingId = $this->option('bling-id');
        
        // Find the order
        if ($isBlingId) {
            $order = OrderSummary::where('bling_id', $orderId)->first();
        } else {
            $order = OrderSummary::find($orderId);
        }
        
        if (!$order) {
            $this->error("Order not found: {$orderId}");
            return self::FAILURE;
        }
        
        $this->info("Order Debug Information");
        $this->newLine();
        
        // Basic order info
        $this->table(
            ['Field', 'Value'],
            [
                ['Internal ID', $order->id],
                ['Bling ID', $order->bling_id],
                ['Número', $order->numero],
                ['Source', $order->source],
                ['Total', 'R$ ' . number_format($order->total, 2, ',', '.')],
                ['Status (situacao_id)', $order->situacao_id],
                ['Status (situacao_valor)', $order->situacao_valor],
                ['Has Invoice', $order->invoice ? 'Yes' : 'No'],
                ['Invoice Number', $order->invoice?->numero ?? 'N/A'],
                ['Tracking Code', $order->tracking_code ?? 'N/A'],
                ['Updated At', $order->updated_at->format('Y-m-d H:i:s')],
            ]
        );
        
        $this->newLine();
        
        // Determine service
        $service = match ($order->source) {
            'Pro', 'Atacadista' => $order->source === 'Pro' 
                ? app(CollmeProBlingService::class)
                : app(CollmeAtacadistaBlingService::class),
            default => app(CollmeProBlingService::class),
        };
        
        // Get cached hash
        $cachedHash = $service->getCachedOrderHash($order->bling_id);
        $dbHash = $order->bling_data_hash;
        
        $this->info("Hash Information:");
        $this->table(
            ['Type', 'Hash'],
            [
                ['Database Hash', $dbHash ?? 'NULL'],
                ['Cached Hash', $cachedHash ?? 'NULL (Cache Miss)'],
                ['Hashes Match', $cachedHash && $cachedHash === $dbHash ? 'Yes' : 'No'],
            ]
        );
        
        $this->newLine();
        
        // Try to fetch from API and compare
        if ($this->confirm('Fetch fresh data from Bling API and compare?', true)) {
            $this->info('Fetching from Bling API...');
            
            try {
                $completeOrderData = $service->getCompleteOrderData($order->bling_id);
                
                if ($completeOrderData) {
                    $freshHash = $service->generateOrderHash($completeOrderData);
                    
                    $this->info("Fresh API Data:");
                    $this->table(
                        ['Field', 'Value'],
                        [
                            ['Fresh Hash', $freshHash],
                            ['Matches DB Hash', $freshHash === $dbHash ? 'Yes (No changes)' : 'No (Changed!)'],
                            ['Has Invoice', !empty($completeOrderData['notaFiscal']['id']) ? 'Yes' : 'No'],
                            ['Invoice ID', $completeOrderData['notaFiscal']['id'] ?? 'N/A'],
                            ['Has Tracking', !empty($completeOrderData['transporte']['volumes']) ? 'Yes' : 'No'],
                            ['situacao_id', $completeOrderData['situacao']['id']],
                            ['situacao_valor', $completeOrderData['situacao']['valor']],
                        ]
                    );
                    
                    if ($freshHash !== $dbHash) {
                        $this->warn("⚠ Order has changes in Bling that need to be synced!");
                    } else {
                        $this->info("✓ Order is up to date with Bling");
                    }
                } else {
                    $this->error('Failed to fetch order from Bling API');
                }
            } catch (\Exception $e) {
                $this->error('Error fetching from Bling: ' . $e->getMessage());
                Log::error('Bling sync debug error', [
                    'order_id' => $order->id,
                    'bling_id' => $order->bling_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return self::SUCCESS;
    }
}


