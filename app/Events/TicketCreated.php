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
        /*
         * Cek Stop Clock aktif.
         */
        $hasActiveStopClock = $this->ticket->stopClocks
            ->contains(
                fn($stopClock) => $stopClock->ended_at === null
            );

        /*
         * Cek apakah kendala merupakan downtime.
         */
        $isDowntime =
            $this->ticket->latestIncident?->category?->is_downtime ?? false;

        $isHistoricalResolved =
            $this->ticket->latestIncident?->resolved_at !== null;

        // Tentukan status SLA.
        if (! $isDowntime) {
            $slaTimerStatus = 'not_applicable';
        } elseif (
            $this->ticket->status === 'resolved'
            || $isHistoricalResolved
        ) {
            $slaTimerStatus = 'resolved';
        } elseif ($hasActiveStopClock) {
            $slaTimerStatus = 'stop_clock';
        } else {
            $slaTimerStatus = 'running';
        }

        // Tentukan apakah SLA boleh berjalan.
        $slaTimerRunning =
            $this->ticket->status !== 'resolved'
            && ! $isHistoricalResolved
            && $isDowntime
            && ! $hasActiveStopClock;
        $calculatedAt = now();

        $slaTimerSeconds = $this->ticket
            ->calculateDowntimeSeconds($calculatedAt);
        return [
            'ticketId' => $this->ticket->id,

            'ticketNumber' => $this->ticket->ticket_number,

            'ticketType' => $this->ticket->ticket_type,

            'priority' => $this->ticket->priority,

            'currentPriority' => $this->ticket->current_priority,

            'status' => $this->ticket->status,

            'reportedAt' => $this->ticket->reported_at?->toISOString(),

            /*
             * SLA Timer
             *
             * Untuk ticket baru biasanya 0.
             * Jika ticket dibuat sebagai historical,
             * calculateDowntimeSeconds() tetap bisa memberikan
             * nilai sesuai incident.
             */
            'slaTimerSeconds' => $slaTimerSeconds,

            'slaTimerCalculatedAt' => $calculatedAt->toISOString(),

            'slaTimerRunning' => $slaTimerRunning,

            'slaTimerStatus' => $slaTimerStatus,

            /*
             * Ticket Duration
             */
            'ticketDurationSeconds' =>
            $this->ticket->calculateTicketDurationSeconds(),
            /*
             * User pembuat ticket
             */
            'creator' => $this->ticket->creator
                ? [
                    'id' => $this->ticket->creator->id,
                    'name' => $this->ticket->creator->name,
                ]
                : null,

            /*
             * User yang terakhir melakukan update
             */
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

            /*
             * Customer / Site
             */
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

            'latestIncident' => $this->ticket->latestIncident
                ? [
                    'id' => $this->ticket->latestIncident->id,
                    'incident_number' => $this->ticket->latestIncident->incident_number,
                    'kendala_id' => $this->ticket->latestIncident->kendala_id,
                    'category' => $this->ticket->latestIncident->category
                        ? [
                            'id' => $this->ticket->latestIncident->category->id,
                            'name' => $this->ticket->latestIncident->category->name,
                        ]
                        : null,
                ]
                : null,
        ];
    }
}
