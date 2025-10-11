<?php

namespace App\Services;

use App\Models\Label;
use App\Models\OrderSummary;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class CollmeProBlingLabelService extends BlingService
{
    /**
     * Get the account type for Collme Pro
     */
    protected function getAccountType(): string
    {
        return 'pro';
    }

    /**
     * Get the client ID for Collme Pro from environment
     */
    protected function getClientId(): string
    {
        $clientId = config('services.bling.pro.client_id');
        
        if (!$clientId) {
            throw new Exception('BLING_PRO_CLIENT_ID not configured in environment');
        }

        return $clientId;
    }

    /**
     * Get the client secret for Collme Pro from environment
     */
    protected function getClientSecret(): string
    {
        $clientSecret = config('services.bling.pro.client_secret');
        
        if (!$clientSecret) {
            throw new Exception('BLING_PRO_CLIENT_SECRET not configured in environment');
        }

        return $clientSecret;
    }

    /**
     * Sync labels from Bling API
     */
    public function syncLabels(): array
    {
        try {
            Log::info("Starting label sync for account: {$this->getAccountType()}");

            $labels = $this->getLabelsFromApi();
            
            $processed = 0;
            $created = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];

            foreach ($labels as $blingLabel) {
                try {
                    $result = $this->processLabel($blingLabel);
                    
                    if ($result['action'] === 'created') {
                        $created++;
                    } elseif ($result['action'] === 'updated') {
                        $updated++;
                    } else {
                        $skipped++;
                    }
                    
                    $processed++;
                } catch (Exception $e) {
                    Log::error("Failed to process label: " . $e->getMessage(), [
                        'label_id' => $blingLabel['id'] ?? 'unknown',
                        'account' => $this->getAccountType(),
                    ]);
                    $errors[] = $blingLabel['id'] ?? 'unknown';
                }
            }

            Log::info("Label sync completed for account {$this->getAccountType()}", [
                'total_labels' => count($labels),
                'processed' => $processed,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'failed' => count($errors),
            ]);

            return [
                'account' => $this->getAccountType(),
                'total_labels' => count($labels),
                'processed' => $processed,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'failed' => $errors,
            ];

        } catch (Exception $e) {
            Log::error("Label sync failed for account {$this->getAccountType()}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get labels from Bling API with pagination
     */
    public function getLabelsFromApi(): array
    {
        $labels = [];
        $page = 1;
        $limit = 100;

        do {
            try {
                Log::info("Fetching page {$page} of labels for account: {$this->getAccountType()}");

                $response = $this->makeRequest('GET', '/logisticas/etiquetas', [
                    'pagina' => $page,
                    'limite' => $limit,
                ]);

                $this->applyRateLimit();

                $data = $response['data'] ?? [];
                $pageLabels = is_array($data) ? $data : [];

                if (empty($pageLabels)) {
                    break;
                }

                $labels = array_merge($labels, $pageLabels);
                
                Log::info("Fetched " . count($pageLabels) . " labels from page {$page}");

                // If we got less than the limit, we've reached the end
                if (count($pageLabels) < $limit) {
                    break;
                }

                $page++;

            } catch (Exception $e) {
                Log::error("Exception while fetching labels", [
                    'page' => $page,
                    'account' => $this->getAccountType(),
                    'error' => $e->getMessage(),
                ]);
                
                // If it's an authentication error, propagate it
                if (str_contains($e->getMessage(), 'No valid token found') || 
                    str_contains($e->getMessage(), '401') ||
                    str_contains($e->getMessage(), 'Unauthorized')) {
                    throw $e;
                }
                
                break;
            }

        } while (true);

        Log::info("Total labels fetched for account {$this->getAccountType()}: " . count($labels));

        return $labels;
    }

    /**
     * Process individual label data
     */
    protected function processLabel(array $blingLabel): array
    {
        $blingLabelId = $blingLabel['id'];
        
        // Check if label already exists
        $existingLabel = Label::where('bling_label_id', $blingLabelId)->first();

        $labelData = $this->mapBlingLabelToLabel($blingLabel);

        if ($existingLabel) {
            // Check if data has changed
            if ($this->isLabelDataIdentical($existingLabel, $labelData)) {
                return ['action' => 'skipped', 'label_id' => $blingLabelId];
            }

            $existingLabel->update($labelData);
            
            Log::info("Updated label {$blingLabelId}");
            
            return ['action' => 'updated', 'label_id' => $blingLabelId];
        } else {
            $label = Label::create($labelData);
            
            Log::info("Created new label {$blingLabelId}");
            
            return ['action' => 'created', 'label_id' => $blingLabelId];
        }
    }

    /**
     * Map Bling label data to Label model
     */
    protected function mapBlingLabelToLabel(array $blingLabel): array
    {
        // Try to find order by tracking code or other data
        $orderSummaryId = $this->findOrderSummaryId($blingLabel);

        // Determine format based on link extension or observation
        $format = 'PDF'; // Default
        $link = $blingLabel['link'] ?? null;
        if ($link && (str_contains($link, '.zpl') || str_contains(strtolower($link), 'zpl'))) {
            $format = 'ZPL';
        }

        return [
            'bling_label_id' => $blingLabel['id'],
            'order_summary_id' => $orderSummaryId,
            'tracking_code' => null, // Will be populated later when we match with tracking
            'format' => $format,
            'issue_date' => isset($blingLabel['data']) ? Carbon::parse($blingLabel['data']) : now(),
            'link' => $link,
            'observation' => $blingLabel['observacao'] ?? null,
            'raw_data' => $blingLabel,
        ];
    }

    /**
     * Try to find order_summary_id from label data
     */
    protected function findOrderSummaryId(array $blingLabel): ?int
    {
        // Try to match by order number in observation or other fields
        $observation = $blingLabel['observacao'] ?? '';
        
        // Look for order number patterns in observation
        if (preg_match('/\b(\d{6,})\b/', $observation, $matches)) {
            $orderNumber = $matches[1];
            $order = OrderSummary::where('numero', $orderNumber)->first();
            if ($order) {
                return $order->id;
            }
        }

        // If we have volume_id, we might be able to track through invoices/tracking
        // This would require more complex logic based on your data structure

        return null;
    }

    /**
     * Check if label data is identical to avoid unnecessary updates
     */
    protected function isLabelDataIdentical(Label $existingLabel, array $newData): bool
    {
        $existingData = [
            'link' => $existingLabel->link,
            'observation' => $existingLabel->observation,
            'format' => $existingLabel->format,
        ];

        $compareData = [
            'link' => $newData['link'] ?? null,
            'observation' => $newData['observation'] ?? null,
            'format' => $newData['format'] ?? 'PDF',
        ];

        return $existingData === $compareData;
    }

    /**
     * Get labels by order
     */
    public function getLabelsByOrder(int $orderSummaryId): array
    {
        return Label::where('order_summary_id', $orderSummaryId)
            ->orderBy('issue_date', 'desc')
            ->get()
            ->toArray();
    }
}

