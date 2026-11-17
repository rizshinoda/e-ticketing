<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\KpiProductivityService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KpiController extends Controller
{
    public function index(
        Request $request,
        KpiProductivityService $productivityService
    ): Response {
        $user = $request->user();

        // KPI hanya untuk staf ticketing aktif.
        abort_unless(
            $user->status === 'active'
                && $user->is_ticketing_staff,
            403,
            'Anda tidak memiliki akses ke halaman KPI.'
        );

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        abort_if(
            $year < 2020
                || $month < 1
                || $month > 12,
            422,
            'Periode KPI tidak valid.'
        );

        $evaluation = $productivityService->calculate(
            $user,
            $year,
            $month
        );
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $contributions = DB::table('ticket_kpi_contributions')
            ->join(
                'tickets',
                'tickets.id',
                '=',
                'ticket_kpi_contributions.ticket_id'
            )
            ->where('ticket_kpi_contributions.user_id', $user->id)
            ->where('tickets.status', 'closed')
            ->whereBetween('tickets.closed_at', [
                $periodStart,
                $periodEnd,
            ])
            ->orderByDesc('tickets.closed_at')
            ->select([
                'tickets.id as ticket_id',
                'tickets.ticket_number',
                'tickets.ticket_type',
                'tickets.closed_at',
                'ticket_kpi_contributions.credit',
            ])
            ->paginate(3)
            ->withQueryString();
        return Inertia::render('Kpi/Index', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'period' => [
                'year' => $year,
                'month' => $month,
            ],
            'productivity' => [
                'credit' => (float) $evaluation->details
                    ->firstWhere('indicator_code', 'productivity')
                    ?->actual_value ?? 0,
                'target' => (float) $evaluation->productivity_target,
                'score' => (float) $evaluation->productivity_score,
                'weight' => 40,
            ],
            'contributions' => $contributions,
        ]);
    }
}
