<?php

namespace App\Console\Commands;

use App\Events\OrderCreated;
use App\Models\OrderSummary;
use App\Models\User;
use Illuminate\Console\Command;

class TestOrderCreatedBroadcast extends Command
{
    protected $signature = 'test:order-created-broadcast';
    protected $description = 'Test OrderCreated broadcast event with the most recent order';

    public function handle()
    {
        $this->info('Testing OrderCreated broadcast...');

        // Get the most recent order
        $order = OrderSummary::with('orderStatus')->latest('id')->first();

        if (!$order) {
            $this->error('No orders found in the database');
            return 1;
        }

        // Get a user
        $user = User::whereIn('role', ['admin', 'manager'])
            ->orderByRaw("FIELD(role, 'admin', 'manager')")
            ->first();
        
        if (!$user) {
            $user = User::first();
        }

        if (!$user) {
            $this->error('No users found in the database');
            return 1;
        }

        $this->info("Broadcasting order #{$order->numero} (ID: {$order->id}) as user {$user->name}");

        // Dispatch the event
        event(new OrderCreated($order, $user));

        $this->info('Event dispatched! Check your browser console for the broadcast.');
        $this->info('Order details:');
        $this->line("  - Number: {$order->numero}");
        $this->line("  - Customer: {$order->contato_nome}");
        $this->line("  - Total: R$ {$order->total}");
        $this->line("  - Source: {$order->source}");
        $this->line("  - Invoice: " . ($order->invoice_number ?? 'N/A'));

        return 0;
    }
}

