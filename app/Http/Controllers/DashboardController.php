<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $ticketStats = [
            'total' => Ticket::count(),

            'open' => Ticket::where(
                'status',
                'open'
            )->count(),

            'on_progress' => Ticket::where(
                'status',
                'on_progress'
            )->count(),

            'resolved' => Ticket::where(
                'status',
                'resolved'
            )->count(),

            'closed' => Ticket::where(
                'status',
                'closed'
            )->count(),
        ];
        $ticketsForPriority = Ticket::query()
            ->get([
                'id',
                'priority',
                'status',
                'reported_at',
            ]);

        $priorityStats = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        foreach ($ticketsForPriority as $ticket) {
            $currentPriority = $ticket->calculateCurrentPriority();

            $priorityStats[$currentPriority]++;
        }
        $activeTickets = Ticket::query()
            ->whereIn('status', ['open', 'on_progress'])
            ->get([
                'id',
                'priority',
                'status',
                'reported_at',
            ]);

        $criticalActive = $activeTickets
            ->filter(fn($ticket) => $ticket->calculateCurrentPriority() === 'critical')
            ->count();
        $statusChart = [
            [
                'status' => 'Open',
                'total' => $ticketStats['open'],
            ],
            [
                'status' => 'On Progress',
                'total' => $ticketStats['on_progress'],
            ],
            [
                'status' => 'Resolved',
                'total' => $ticketStats['resolved'],
            ],
            [
                'status' => 'Closed',
                'total' => $ticketStats['closed'],
            ],
        ];
        $monthlyTickets = Ticket::query()
            ->selectRaw("DATE_FORMAT(reported_at, '%Y-%m') as month")
            ->selectRaw('COUNT(*) as total')
            ->where('reported_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $monthlyTicketChart = collect();

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);

            $monthKey = $date->format('Y-m');

            $monthlyTicketChart->push([
                'month' => $date->translatedFormat('M Y'),
                'total' => (int) (
                    $monthlyTickets->firstWhere('month', $monthKey)?->total ?? 0
                ),
            ]);
        }
        return Inertia::render('Dashboard', [
            'ticketStats' => $ticketStats,
            'priorityStats' => $priorityStats,
            'statusChart' => $statusChart,
            'monthlyTicketChart' => $monthlyTicketChart,
            'criticalActive' => $criticalActive,
        ]);
    }

    public function monitor()
    {
        $tickets = Ticket::query()
            ->whereIn('status', ['open', 'on_progress', 'resolved'])
            ->get([
                'id',
                'priority',
                'status',
                'reported_at',
            ]);

        $priorityStats = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        foreach ($tickets as $ticket) {
            $currentPriority = $ticket->calculateCurrentPriority();

            $priorityStats[$currentPriority]++;
        }

        $criticalActive = $priorityStats['critical'];

        return response()->json([
            'priorityStats' => $priorityStats,
            'criticalActive' => $criticalActive,
        ]);
    }
}
