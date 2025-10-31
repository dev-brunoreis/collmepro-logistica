<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrderSummary;
use App\Models\User;
use App\Events\OrderStatusUpdated;

class MarkDeliveredOrders extends Command
{
    protected $signature = 'orders:mark-delivered 
                            {--file= : Path to CSV file with order IDs}
                            {--ids= : Comma-separated list of order IDs}
                            {--limit= : Limit number of orders to process}
                            {--dry-run : Show what would be done without executing}
                            {--user= : User ID or email to use for marking (defaults to admin)}';

    protected $description = 'Mark delivered orders as picking, packing, and picked_up from CSV or ID list';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $filePath = $this->option('file');
        $idsOption = $this->option('ids');
        $userOption = $this->option('user');

        if ($dryRun) {
            $this->info("🔍 DRY RUN MODE - No changes will be made");
        }

        // Get or find system user
        $user = $this->getSystemUser($userOption);
        if (!$user) {
            $this->error("No user found for marking orders. Please create an admin or manager user.");
            return 1;
        }

        $this->info("Using user: {$user->name} ({$user->role})");

        // Get order IDs
        $orderIds = $this->getOrderIds($filePath, $idsOption);
        
        if (empty($orderIds)) {
            $this->error("No order IDs found. Please provide --file or --ids option.");
            return 1;
        }

        $this->info("Found " . count($orderIds) . " order IDs to process");

        // Limit if specified
        if ($limit = $this->option('limit')) {
            $orderIds = array_slice($orderIds, 0, (int) $limit);
            $this->info("Limited to " . count($orderIds) . " orders");
        }

        // Load orders with relationships
        $orders = OrderSummary::with(['invoice', 'orderStatus'])
            ->whereIn('id', $orderIds)
            ->get();

        if ($orders->isEmpty()) {
            $this->warn("No valid orders found with the provided IDs.");
            return 1;
        }

        $this->info("Processing " . $orders->count() . " orders...");

        $processed = 0;
        $skipped = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($orders->count());
        $bar->start();

        foreach ($orders as $order) {
            try {
                // Skip cancelled orders
                if ($order->isCancelled()) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // Verify order has invoice
                if (!$order->invoice || !$order->invoice->numero) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // Verify order has tracking code
                $hasTracking = false;
                if ($order->tracking_code && $order->tracking_code !== '') {
                    $hasTracking = true;
                } elseif ($order->invoice && $order->invoice->tracking()->count() > 0) {
                    $hasTracking = $order->invoice->tracking()
                        ->whereNotNull('codigo_rastreamento')
                        ->where('codigo_rastreamento', '!=', '')
                        ->exists();
                }

                if (!$hasTracking) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // Get or create order status
                $orderStatus = $order->orderStatus;
                if (!$orderStatus) {
                    if (!$dryRun) {
                        $orderStatus = $order->orderStatus()->create([
                            'picking' => false,
                            'packing' => false,
                            'picked_up' => false,
                        ]);
                    } else {
                        $orderStatus = new \App\Models\OrderStatus([
                            'picking' => false,
                            'packing' => false,
                            'picked_up' => false,
                        ]);
                    }
                }

                // Mark statuses sequentially
                if (!$dryRun) {
                    // Mark picking (if not already marked)
                    if (!$orderStatus->picking) {
                        $orderStatus->markPicking($user);
                        broadcast(new OrderStatusUpdated($order, $user, 'picking', true));
                    }

                    // Mark packing (if not already marked)
                    if (!$orderStatus->packing) {
                        $orderStatus->markPacking($user);
                        broadcast(new OrderStatusUpdated($order, $user, 'packing', true));
                    }

                    // Mark picked_up (if not already marked)
                    if (!$orderStatus->picked_up) {
                        $orderStatus->markPickedUp($user);
                        broadcast(new OrderStatusUpdated($order, $user, 'picked_up', true));
                    }
                }

                $processed++;

            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("Error processing order #{$order->numero} (ID: {$order->id}): " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Summary
        $this->info("✅ Processing completed!");
        $this->table(
            ['Status', 'Count'],
            [
                ['Processed', $processed],
                ['Skipped', $skipped],
                ['Errors', $errors],
                ['Total', $orders->count()],
            ]
        );

        if ($dryRun) {
            $this->info("🔍 DRY RUN - No actual changes were made");
        }

        return 0;
    }

    /**
     * Get order IDs from file or option
     */
    private function getOrderIds(?string $filePath, ?string $idsOption): array
    {
        if ($idsOption) {
            // Parse comma-separated IDs
            return array_map('trim', explode(',', $idsOption));
        }

        if ($filePath) {
            // Read CSV file
            if (!file_exists($filePath)) {
                $this->error("File not found: {$filePath}");
                return [];
            }

            $file = fopen($filePath, 'r');
            $ids = [];
            
            // Skip header
            $header = fgetcsv($file, 0, ';');
            
            // Find ID column index
            $idColumnIndex = array_search('ID Pedido', $header);
            if ($idColumnIndex === false) {
                fclose($file);
                $this->error("CSV file must have 'ID Pedido' column");
                return [];
            }

            // Read all rows
            while (($row = fgetcsv($file, 0, ';')) !== false) {
                if (isset($row[$idColumnIndex]) && is_numeric($row[$idColumnIndex])) {
                    $ids[] = (int) $row[$idColumnIndex];
                }
            }

            fclose($file);
            return array_unique($ids);
        }

        return [];
    }

    /**
     * Get system user for marking orders
     */
    private function getSystemUser(?string $userOption): ?User
    {
        // If user specified by option
        if ($userOption) {
            $user = null;
            
            // Try by ID
            if (is_numeric($userOption)) {
                $user = User::find($userOption);
            }
            
            // Try by email
            if (!$user) {
                $user = User::where('email', $userOption)->first();
            }
            
            if ($user) {
                return $user;
            }
        }

        // Default: find admin or manager
        return User::whereIn('role', ['admin', 'manager'])
            ->orderByRaw("FIELD(role, 'admin', 'manager')")
            ->first();
    }
}

