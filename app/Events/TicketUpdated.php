<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketUpdated implements ShouldBroadcastNow
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

            'latestUpdate' => $this->ticket->latestUpdate
                ? [
                    'id' => $this->ticket->latestUpdate->id,

                    'user' => $this->ticket->latestUpdate->user
                        ? [
                            'id' => $this->ticket->latestUpdate->user->id,
                            'name' => $this->ticket->latestUpdate->user->name,
                        ]
                        : null,
                ]
                : null,
        ];
    }
}
