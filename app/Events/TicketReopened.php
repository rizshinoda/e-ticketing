<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class TicketReopened implements ShouldBroadcastNow
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
        $hasActiveStopClock = $this->ticket->stopClocks
            ->contains(
                fn($stopClock) => $stopClock->ended_at === null
            );

        $isDowntime =
            $this->ticket->latestIncident?->category?->is_downtime
            ?? false;

        if (! $isDowntime) {
            $slaTimerStatus = 'not_applicable';
        } elseif ($hasActiveStopClock) {
            $slaTimerStatus = 'stop_clock';
        } else {
            $slaTimerStatus = 'running';
        }

        return [
            'ticketId' => $this->ticket->id,
            'ticketNumber' => $this->ticket->ticket_number,
            'ticketType' => $this->ticket->ticket_type,

            'priority' => $this->ticket->priority,
            'currentPriority' => $this->ticket->current_priority,

            'status' => $this->ticket->status,

            'reportedAt' => $this->ticket->reported_at?->toISOString(),

            'slaTimerSeconds' => 0,
            'slaTimerRunning' => $isDowntime && ! $hasActiveStopClock,
            'slaTimerStatus' => $slaTimerStatus,

            'ticketDurationSeconds' =>
            $this->ticket->reported_at->diffInSeconds(now()),

            'creator' => $this->ticket->creator
                ? [
                    'id' => $this->ticket->creator->id,
                    'name' => $this->ticket->creator->name,
                ]
                : null,

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
