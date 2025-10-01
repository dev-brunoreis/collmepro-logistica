<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\BlingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SyncSpecificOrderCommand extends Command
{
    protected $signature = 'sync:order {order_number : Número do pedido para sincronizar}';
    protected $description = 'Sincroniza um pedido específico e salva todas as respostas da API em JSON';

    private string $logDir;

    public function handle()
    {
        $orderNumber = $this->argument('order_number');
        
        // Cria diretório para logs
        $this->logDir = storage_path('api_logs/' . $orderNumber . '_' . now()->format('Y-m-d_H-i-s'));
        File::makeDirectory($this->logDir, 0755, true);
        
        $this->info("📁 Logs serão salvos em: {$this->logDir}");
        $this->newLine();

        // Busca o pedido
        $this->info("🔍 Buscando pedido {$orderNumber}...");
        $order = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            $this->error("❌ Pedido {$orderNumber} não encontrado!");
            return 1;
        }

        $this->info("✅ Pedido encontrado: ID {$order->id}");
        $this->saveJson('01_order_data.json', $order->toArray());
        $this->newLine();

        // Sincroniza NFE
        $this->info("📄 Buscando NFE no Bling...");
        $this->syncInvoice($order);
        $this->newLine();

        // Busca rastreio
        if ($order->fresh()->nfe_number) {
            $this->info("🚚 Buscando rastreio...");
            $this->fetchTracking($order->fresh());
        } else {
            $this->warn("⚠️  Pedido não tem NFE, não é possível buscar rastreio.");
        }

        $this->newLine();
        $this->info("✅ Sincronização completa!");
        $this->info("📁 Logs salvos em: {$this->logDir}");

        return 0;
    }

    private function syncInvoice(Order $order)
    {
        $blingService = app(BlingService::class);

        try {
            // Busca invoice básica
            $this->line("  → Buscando invoice por número do pedido...");
            $invoiceBasic = $blingService->fetchInvoiceByOrderNumber($order->order_number);
            
            if (!$invoiceBasic) {
                $this->warn("  ⚠️  Nenhuma invoice encontrada");
                $this->saveJson('02_invoice_basic_NOT_FOUND.json', ['error' => 'Invoice not found']);
                return;
            }

            $this->saveJson('02_invoice_basic.json', $invoiceBasic);
            $this->info("  ✅ Invoice básica encontrada: {$invoiceBasic['numero']}");

            // Busca invoice completa
            $this->line("  → Buscando dados completos da invoice...");
            $invoiceFull = $blingService->fetchInvoiceById($invoiceBasic['id']);
            
            if ($invoiceFull) {
                $this->saveJson('03_invoice_full.json', $invoiceFull);
                $this->info("  ✅ Invoice completa recuperada");
                
                // Extrai dados
                $invoiceData = $blingService->extractInvoiceData($invoiceFull);
                $this->saveJson('04_invoice_extracted.json', $invoiceData);
                
                // Mostra informações importantes
                $this->table(
                    ['Campo', 'Valor'],
                    [
                        ['Status', $invoiceData['status']],
                        ['Número NFE', $invoiceData['invoice_number']],
                        ['Valor', 'R$ ' . number_format($invoiceData['valor_nota'] ?? 0, 2, ',', '.')],
                        ['Tem Transporte?', !empty($invoiceData['transport']) ? 'SIM' : 'NÃO'],
                        ['Transportadora', $invoiceData['transport']['logistics_provider'] ?? 'N/A'],
                        ['Cód. Rastreio', $invoiceData['transport']['tracking_code'] ?? 'N/A'],
                        ['ID Obj. Logístico', $invoiceData['transport']['logistics_object_id'] ?? 'N/A'],
                    ]
                );

                // Atualiza pedido
                $order->update([
                    'nfe_number' => $invoiceData['invoice_number'],
                    'nfe_pdf_url' => $invoiceData['link_pdf'],
                    'nfe_access_key' => $invoiceData['access_key'],
                    'nfe_issue_date' => $invoiceData['issue_date'],
                    'nfe_value' => $invoiceData['valor_nota'],
                ]);

                $this->info("  ✅ Pedido atualizado com dados da NFE");
            }

        } catch (\Exception $e) {
            $this->error("  ❌ Erro: {$e->getMessage()}");
            $this->saveJson('ERROR_invoice_sync.json', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function fetchTracking(Order $order)
    {
        $blingService = app(BlingService::class);

        try {
            if (!$order->nfe_number) {
                $this->warn("  ⚠️  Pedido não tem NFE");
                return;
            }

            // Busca invoice novamente para ter dados atualizados
            $this->line("  → Buscando invoice para rastreio...");
            $invoiceBasic = $blingService->fetchInvoiceByOrderNumber($order->order_number);
            
            if (!$invoiceBasic) {
                $this->warn("  ⚠️  Invoice não encontrada");
                return;
            }

            $invoiceFull = $blingService->fetchInvoiceById($invoiceBasic['id']);
            
            if (!$invoiceFull) {
                $this->warn("  ⚠️  Não foi possível buscar invoice completa");
                return;
            }

            $invoiceData = $blingService->extractInvoiceData($invoiceFull);
            $transportInfo = $invoiceData['transport'] ?? [];

            $this->saveJson('05_transport_info.json', $transportInfo);

            if (empty($transportInfo)) {
                $this->warn("  ⚠️  Não há informações de transporte na invoice");
                return;
            }

            $this->info("  ✅ Informações de transporte encontradas");

            // Busca objeto logístico se disponível
            if (!empty($transportInfo['logistics_object_id'])) {
                $this->line("  → Buscando objeto logístico...");
                $logisticsObject = $blingService->fetchLogisticsObjectById($transportInfo['logistics_object_id']);
                
                if ($logisticsObject) {
                    $this->saveJson('06_logistics_object.json', $logisticsObject);
                    $this->info("  ✅ Objeto logístico recuperado");

                    $trackingData = $blingService->extractTrackingData($logisticsObject);
                    $this->saveJson('07_tracking_extracted.json', $trackingData);

                    // Mostra informações de rastreio
                    $this->table(
                        ['Campo', 'Valor'],
                        [
                            ['Código Rastreio', $trackingData['tracking_code'] ?? 'N/A'],
                            ['URL Rastreio', $trackingData['tracking_url'] ?? 'N/A'],
                            ['Status', $trackingData['status'] ?? 'N/A'],
                            ['Descrição', $trackingData['description'] ?? 'N/A'],
                            ['Serviço', isset($trackingData['service']['name']) ? $trackingData['service']['name'] : 'N/A'],
                            ['Data Postagem', $trackingData['last_update'] ?? 'N/A'],
                            ['Prazo Entrega', isset($trackingData['estimated_delivery']) ? $trackingData['estimated_delivery'] . ' dias' : 'N/A'],
                            ['Valor Frete', isset($trackingData['freight_value']) ? 'R$ ' . number_format($trackingData['freight_value'], 2, ',', '.') : 'N/A'],
                        ]
                    );

                    // Atualiza pedido
                    $updateData = [];
                    if (!empty($trackingData['tracking_code'])) {
                        $updateData['tracking_code'] = $trackingData['tracking_code'];
                    }
                    if (!empty($trackingData['tracking_url'])) {
                        $updateData['tracking_url'] = $trackingData['tracking_url'];
                    }
                    if (!empty($trackingData['description'])) {
                        $updateData['tracking_status'] = $trackingData['description'];
                    }
                    if (!empty($transportInfo['logistics_provider'])) {
                        $updateData['logistics_provider'] = $transportInfo['logistics_provider'];
                    }

                    if (!empty($updateData)) {
                        $order->update($updateData);
                        $this->info("  ✅ Pedido atualizado com dados de rastreio");
                    }
                } else {
                    $this->warn("  ⚠️  Objeto logístico não encontrado");
                    $this->saveJson('06_logistics_object_NOT_FOUND.json', ['error' => 'Logistics object not found']);
                }
            } else {
                $this->warn("  ⚠️  Invoice não tem ID de objeto logístico");
            }

        } catch (\Exception $e) {
            $this->error("  ❌ Erro: {$e->getMessage()}");
            $this->saveJson('ERROR_tracking_fetch.json', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function saveJson(string $filename, array $data)
    {
        $path = $this->logDir . '/' . $filename;
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->line("  💾 Salvo: {$filename}");
    }
}
