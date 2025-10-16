<?php

namespace App\Console\Commands;

use App\Events\OrderUpdated;
use App\Models\OrderSummary;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BroadcastOrderUpdate extends Command
{
    protected $signature = 'broadcast:order-update {numero : Order number to broadcast}';
    protected $description = 'Manually broadcast an order update event for emergency fixes';

    public function handle()
    {
        $numero = $this->argument('numero');
        
        // Find the order
        $order = OrderSummary::with(['invoice', 'orderStatus'])->where('numero', $numero)->first();
        
        if (!$order) {
            $this->error("Order {$numero} not found in database");
            return 1;
        }
        
        $this->info("Found order: {$order->numero} (ID: {$order->id})");
        
        // Get system user for broadcasting events
        // Use orderBy with conditional to work with SQLite (no FIELD function)
        $systemUser = User::whereIn('role', ['admin', 'manager'])
            ->orderBy(DB::raw("CASE WHEN role = 'admin' THEN 1 WHEN role = 'manager' THEN 2 ELSE 3 END"))
            ->first();
        
        // Fallback to any user if no admin/manager exists
        if (!$systemUser) {
            $systemUser = User::first();
        }
        
        if (!$systemUser) {
            $this->error('No users found in system to broadcast as');
            return 1;
        }
        
        // Determine what changed based on current order state
        $changedFields = [];
        $changeTypes = [];
        
        // Check for invoice
        if ($order->invoice && $order->invoice->numero) {
            $changedFields[] = 'invoice_number';
            $changeTypes[] = 'invoice_added';
            $this->info("Invoice found: {$order->invoice->numero}");
        }
        
        // Check for tracking
        if ($order->tracking_code) {
            $changedFields[] = 'tracking_code';
            $changeTypes[] = 'tracking_added';
            $this->info("Tracking code found: {$order->tracking_code}");
        }
        
        // Check order status
        if ($order->orderStatus) {
            if ($order->orderStatus->picking) {
                $changedFields[] = 'picking';
                $changeTypes[] = 'status_updated';
            }
            if ($order->orderStatus->packing) {
                $changedFields[] = 'packing';
                $changeTypes[] = 'status_updated';
            }
            if ($order->orderStatus->picked_up) {
                $changedFields[] = 'picked_up';
                $changeTypes[] = 'status_updated';
            }
        }
        
        if (empty($changedFields)) {
            $this->warn('No significant changes detected. Broadcasting general update.');
            $changedFields = ['general'];
            $changeTypes = ['general_update'];
        }
        
        // Broadcast the event
        event(new OrderUpdated($order, $systemUser, $changedFields, $changeTypes));
        
        $this->info('Order update event broadcasted successfully!');
        $this->table(
            ['Field', 'Value'],
            [
                ['Order Number', $order->numero],
                ['Invoice Number', $order->invoice?->numero ?? 'N/A'],
                ['Tracking Code', $order->tracking_code ?? 'N/A'],
                ['Changed Fields', implode(', ', $changedFields)],
                ['Change Types', implode(', ', $changeTypes)],
                ['Broadcasted By', $systemUser->name],
            ]
        );
        
        return 0;
    }
}
