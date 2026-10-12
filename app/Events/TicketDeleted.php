<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $ticketId
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('ticket-monitor'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TicketDeleted';
    }

    public function broadcastWith(): array
    {
        return [
            'ticketId' => $this->ticketId,
        ];
    }
}
