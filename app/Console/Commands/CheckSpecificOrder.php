<?php

namespace App\Console\Commands;

use App\Models\OrderSummary;
use App\Services\CollmeProBlingService;
use App\Services\CollmeAtacadistaBlingService;
use Illuminate\Console\Command;

class CheckSpecificOrder extends Command
{
    protected $signature = 'bling:check-specific {numero : Order number to check}';
    protected $description = 'Check specific order status and sync details';

    public function handle()
    {
        $numero = $this->argument('numero');
        
        // Find in database
        $localOrder = OrderSummary::where('numero', $numero)->first();
        
        if (!$localOrder) {
            $this->error("Order {$numero} not found in database");
            return 1;
        }
        
        $this->info("=== LOCAL DATABASE STATUS ===");
        $this->table(
            ['Field', 'Value'],
            [
                ['Order Number', $localOrder->numero],
                ['Bling ID', $localOrder->bling_id],
                ['Source', $localOrder->source],
                ['Situacao Valor', $localOrder->situacao_valor],
                ['Situacao ID', $localOrder->situacao_id],
                ['Total', $localOrder->total],
                ['Contato Nome', $localOrder->contato_nome],
                ['Updated At', $localOrder->updated_at],
                ['External Updated At', $localOrder->external_updated_at],
                ['Data', $localOrder->data],
            ]
        );
        
        // Determine which service to use
        $blingService = $localOrder->source === 'collmepro_atacadista' 
            ? app(CollmeAtacadistaBlingService::class)
            : app(CollmeProBlingService::class);
        
        $this->info("\n=== FETCHING FROM BLING API ===");
        
        try {
            $blingOrder = $blingService->getCompleteOrderData($localOrder->bling_id);
            
            if (!$blingOrder) {
                $this->error("Could not fetch order from Bling API");
                return 1;
            }
            
            $this->info("\n=== BLING API CURRENT DATA ===");
            $this->table(
                ['Field', 'Value'],
                [
                    ['Order Number', $blingOrder['numero']],
                    ['Bling ID', $blingOrder['id']],
                    ['Situacao Valor', $blingOrder['situacao']['valor'] ?? 'N/A'],
                    ['Situacao ID', $blingOrder['situacao']['id'] ?? 'N/A'],
                    ['Total', $blingOrder['total'] ?? 'N/A'],
                    ['Contato Nome', $blingOrder['contato']['nome'] ?? 'N/A'],
                    ['Data Alteracao', $blingOrder['dataAlteracao'] ?? 'N/A'],
                    ['Data', $blingOrder['data'] ?? 'N/A'],
                ]
            );
            
            // Compare key fields
            $this->info("\n=== COMPARISON ANALYSIS ===");
            
            $localSituacaoValor = $localOrder->situacao_valor;
            $blingSituacaoValor = $blingOrder['situacao']['valor'] ?? null;
            
            $localSituacaoId = $localOrder->situacao_id;
            $blingSituacaoId = $blingOrder['situacao']['id'] ?? null;
            
            $localTotal = $localOrder->total;
            $blingTotal = (float) ($blingOrder['total'] ?? 0);
            
            $localContato = $localOrder->contato_nome;
            $blingContato = $blingOrder['contato']['nome'] ?? null;
            
            $this->table(
                ['Field', 'Local', 'Bling', 'Match'],
                [
                    ['Situacao Valor', $localSituacaoValor, $blingSituacaoValor, $localSituacaoValor == $blingSituacaoValor ? '✅' : '❌'],
                    ['Situacao ID', $localSituacaoId, $blingSituacaoId, $localSituacaoId == $blingSituacaoId ? '✅' : '❌'],
                    ['Total', $localTotal, $blingTotal, $localTotal == $blingTotal ? '✅' : '❌'],
                    ['Contato Nome', $localContato, $blingContato, $localContato == $blingContato ? '✅' : '❌'],
                ]
            );
            
            // Check if order should be synced
            $this->info("\n=== SYNC ANALYSIS ===");
            
            $blingUpdatedAt = \Carbon\Carbon::parse($blingOrder['dataAlteracao'] ?? $blingOrder['data']);
            $localUpdatedAt = $localOrder->updated_at;
            
            $this->line("Bling Updated At: {$blingUpdatedAt}");
            $this->line("Local Updated At: {$localUpdatedAt}");
            $this->line("Should Skip Sync: " . ($blingUpdatedAt->lte($localUpdatedAt) ? 'YES' : 'NO'));
            
            // Check if there are differences that should trigger sync
            $hasDifferences = (
                $localSituacaoValor != $blingSituacaoValor ||
                $localSituacaoId != $blingSituacaoId ||
                $localTotal != $blingTotal ||
                $localContato != $blingContato
            );
            
            $this->line("Has Differences: " . ($hasDifferences ? 'YES' : 'NO'));
            
            if ($hasDifferences) {
                $this->warn("\n🚨 ORDER NEEDS SYNC!");
                $this->line("This order has differences between local DB and Bling API.");
                $this->line("It should be updated in the next sync cycle.");
            } else {
                $this->info("\n✅ ORDER IS IN SYNC");
            }
            
        } catch (\Exception $e) {
            $this->error("Error fetching from Bling: " . $e->getMessage());
            return 1;
        }
    }
}
