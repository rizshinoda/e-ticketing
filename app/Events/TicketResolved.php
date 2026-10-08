<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketResolved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Ticket $ticket,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('ticket-monitor'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'ticketId' => $this->ticket->id,
            'status' => $this->ticket->status,
            'currentPriority' => $this->ticket->current_priority,
            'resolvedAt' => $this->ticket->resolved_at?->toISOString(),

            'slaTimerSeconds' =>
            $this->ticket->resolution === 'provider_issue'
                ? $this->ticket->calculateDowntimeSeconds()
                : 0,
            'slaTimerStatus' => 'resolved',
        ];
    }
}
