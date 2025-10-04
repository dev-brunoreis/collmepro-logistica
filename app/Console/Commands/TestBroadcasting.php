<?php

namespace App\Console\Commands;

use App\Events\OrderStatusUpdated;
use App\Models\OrderSummary;
use App\Models\User;
use Illuminate\Console\Command;

class TestBroadcasting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'broadcast:test {order_id} {action=picking} {status=true}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test broadcasting by sending a test order status update';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $orderId = $this->argument('order_id');
        $action = $this->argument('action');
        $status = filter_var($this->argument('status'), FILTER_VALIDATE_BOOLEAN);

        $order = OrderSummary::find($orderId);
        if (!$order) {
            $this->error("Order with ID {$orderId} not found.");
            return 1;
        }

        // Create a test user or use the first admin user
        $user = User::where('role', 'admin')->first();
        if (!$user) {
            $user = User::first();
        }

        if (!$user) {
            $this->error('No users found in the system.');
            return 1;
        }

        $this->info("Broadcasting order status update:");
        $this->info("Order: {$order->numero}");
        $this->info("Action: {$action}");
        $this->info("Status: " . ($status ? 'true' : 'false'));
        $this->info("User: {$user->name}");

        // Broadcast the event
        broadcast(new OrderStatusUpdated($order, $user, $action, $status));

        $this->info('Broadcast sent successfully!');

        return 0;
    }
}
