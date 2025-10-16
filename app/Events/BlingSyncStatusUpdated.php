<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class BlingSyncStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $isRunning;
    public ?string $lastSyncAt;
    public ?string $nextSyncAt;
    public ?string $accountType;

    /**
     * Create a new event instance.
     */
    public function __construct(
        bool $isRunning,
        ?string $lastSyncAt = null,
        ?string $nextSyncAt = null,
        ?string $accountType = null
    ) {
        $this->isRunning = $isRunning;
        $this->lastSyncAt = $lastSyncAt;
        $this->nextSyncAt = $nextSyncAt;
        $this->accountType = $accountType;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('sync-status'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'is_running' => $this->isRunning,
            'last_sync_at' => $this->lastSyncAt,
            'next_sync_at' => $this->nextSyncAt,
            'account_type' => $this->accountType,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'sync-status.updated';
    }
}
