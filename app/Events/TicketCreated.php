<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketCreated implements ShouldBroadcastNow
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
            'ticketNumber' => $this->ticket->ticket_number,
            'ticketType' => $this->ticket->ticket_type,
            'priority' => $this->ticket->priority,
            'currentPriority' => $this->ticket->current_priority,
            'status' => $this->ticket->status,
            'reportedAt' => $this->ticket->reported_at?->toISOString(),
            'slaTimerSeconds' => $this->ticket->calculateDowntimeSeconds(),
            'slaTimerRunning' => true,
            'customers' => $this->ticket->customers
                ->map(function ($customer) {
                    return [
                        'id' => $customer->id,
                        'customer_name' => $customer->customer_name,
                        'site_name' => $customer->site_name,
                        'no_jaringan' => $customer->no_jaringan,
                    ];
                })
                ->values()
                ->all(),
        ];
    }
}
