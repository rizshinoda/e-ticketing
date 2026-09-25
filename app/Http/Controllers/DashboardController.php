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
        $priorityStats = [
            'critical' => Ticket::where('priority', 'critical')->count(),

            'high' => Ticket::where('priority', 'high')->count(),

            'medium' => Ticket::where('priority', 'medium')->count(),

            'low' => Ticket::where('priority', 'low')->count(),
        ];
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

        return Inertia::render('Dashboard', [
            'ticketStats' => $ticketStats,
            'priorityStats' => $priorityStats,
            'statusChart' => $statusChart,
        ]);
    }
}
