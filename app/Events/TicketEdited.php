<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketEdited implements ShouldBroadcastNow
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

    public function broadcastAs(): string
    {
        return 'TicketEdited';
    }

    public function broadcastWith(): array
    {
        $ticket = $this->ticket;

        $incident = $ticket->latestIncident;
        $category = $incident?->category;

        $hasActiveStopClock = $ticket->stopClocks
            ->contains(fn($clock) => $clock->ended_at === null);

        $isDowntime = $category?->is_downtime ?? false;

        if (! $isDowntime) {
            $slaTimerStatus = 'not_applicable';
        } elseif ($incident?->resolved_at !== null || $ticket->status === 'resolved') {
            $slaTimerStatus = 'resolved';
        } elseif ($hasActiveStopClock) {
            $slaTimerStatus = 'stop_clock';
        } else {
            $slaTimerStatus = 'running';
        }

        return [
            'ticketId' => $ticket->id,
            'ticketNumber' => $ticket->ticket_number,
            'ticketType' => $ticket->ticket_type,
            'priority' => $ticket->priority,
            'currentPriority' => $ticket->current_priority,
            'status' => $ticket->status,
            'description' => $ticket->description,
            'reportedAt' => $ticket->reported_at?->toISOString(),

            'customers' => $ticket->customers
                ->map(fn($customer) => [
                    'id' => $customer->id,
                    'customer_name' => $customer->customer_name,
                    'site_name' => $customer->site_name,
                    'no_jaringan' => $customer->no_jaringan,
                ])
                ->values()
                ->all(),

            'latestIncident' => $incident
                ? [
                    'id' => $incident->id,
                    'incident_number' => $incident->incident_number,
                    'kendala_id' => $incident->kendala_id,
                    'reported_at' => $incident->reported_at?->toISOString(),
                    'resolved_at' => $incident->resolved_at?->toISOString(),
                    'category' => $category
                        ? [
                            'id' => $category->id,
                            'name' => $category->name,
                        ]
                        : null,
                ]
                : null,

            'slaTimerSeconds' => $isDowntime
                ? $ticket->calculateDowntimeSeconds()
                : 0,

            'slaTimerRunning' =>
            $ticket->status !== 'resolved'
                && $ticket->status !== 'closed'
                && $isDowntime
                && ! $hasActiveStopClock,

            'slaTimerStatus' => $slaTimerStatus,

            'ticketDurationSeconds' => $ticket->reported_at
                ->diffInSeconds($ticket->closed_at ?? now()),

            'creator' => $ticket->creator
                ? [
                    'id' => $ticket->creator->id,
                    'name' => $ticket->creator->name,
                ]
                : null,

            'latestUpdate' => $ticket->latestUpdate
                ? [
                    'id' => $ticket->latestUpdate->id,
                    'user' => $ticket->latestUpdate->user
                        ? [
                            'id' => $ticket->latestUpdate->user->id,
                            'name' => $ticket->latestUpdate->user->name,
                        ]
                        : null,
                ]
                : null,
        ];
    }
}
