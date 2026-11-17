<?php

namespace App\Services;

use App\Models\User;
use App\Models\Ticket;
use App\Models\KpiEvaluation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KpiProductivityService
{
    public function calculate(
        User $user,
        int $year,
        int $month
    ): KpiEvaluation {
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        // Tiga bulan kalender sebelum periode KPI.
        $previousStart = $periodStart->copy()
            ->subMonths(3)
            ->startOfMonth();

        $previousEnd = $periodStart->copy()
            ->subMonth()
            ->endOfMonth();

        return DB::transaction(function () use (
            $user,
            $periodStart,
            $periodEnd,
            $previousStart,
            $previousEnd
        ) {
            $evaluation = KpiEvaluation::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                ],
                [
                    'status' => 'draft',
                ]
            );

            // Snapshot target dibuat satu kali per periode.
            if ($evaluation->active_staff_count === 0) {

                // Cari tanggal tiket pertama sejak aplikasi digunakan.
                $firstTicketDate = Ticket::query()
                    ->oldest('created_at')
                    ->value('created_at');

                $totalIncoming = 0;
                $availableMonths = 0;

                if ($firstTicketDate) {
                    // Bulan tiket pertama dihitung sebagai satu bulan penuh.
                    $firstTicketMonth = Carbon::parse($firstTicketDate)
                        ->startOfMonth();

                    // Tentukan awal periode historis yang tersedia.
                    $availableStart = $firstTicketMonth->greaterThan($previousStart)
                        ? $firstTicketMonth->copy()
                        : $previousStart->copy();

                    // Pastikan bulan pertama tidak melewati akhir periode historis.
                    if ($availableStart->lte($previousEnd)) {
                        $availableMonths = (int) $availableStart
                            ->copy()
                            ->startOfMonth()
                            ->diffInMonths(
                                $previousEnd->copy()->startOfMonth()
                            ) + 1;

                        // Hitung tiket masuk pada bulan historis yang tersedia.
                        $totalIncoming = Ticket::query()
                            ->whereBetween('created_at', [
                                $availableStart,
                                $previousEnd,
                            ])
                            ->count();
                    }
                }

                $activeStaffCount = User::query()
                    ->where('status', 'active')
                    ->where('is_ticketing_staff', true)
                    ->count();

                // Rata-rata berdasarkan jumlah bulan yang tersedia.
                $averageIncoming = $availableMonths > 0
                    ? $totalIncoming / $availableMonths
                    : 0;

                $target = ($availableMonths > 0 && $activeStaffCount > 0)
                    ? $averageIncoming / $activeStaffCount
                    : 0;

                $evaluation->update([
                    'total_incoming_tickets' => $totalIncoming,
                    'active_staff_count' => $activeStaffCount,
                    'productivity_target' => round($target, 2),
                ]);
            }

            // Akumulasi kredit tiket yang di-Close pada periode KPI.
            $totalCredit = DB::table('ticket_kpi_contributions')
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
                ->sum('ticket_kpi_contributions.credit');

            $target = (float) $evaluation->productivity_target;

            $score = $target > 0
                ? min(($totalCredit / $target) * 100, 100)
                : 0;

            $evaluation->update([
                'productivity_score' => round($score, 2),
            ]);

            // Simpan rincian indikator Productivity.
            $evaluation->details()->updateOrCreate(
                ['indicator_code' => 'productivity'],
                [
                    'actual_value' => round((float) $totalCredit, 2),
                    'target_value' => $target,
                    'weight' => 40,
                    'score' => round($score, 2),
                ]
            );

            return $evaluation->fresh('details');
        });
    }
}
