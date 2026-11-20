<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KpiSpeedService
{
    public function calculate(
        User $user,
        int $year,
        int $month
    ): array {
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        // Ambil periode tanggung jawab Individual.
        $assignments = DB::table('ticket_assignments as ta')
            ->join('tickets', 'tickets.id', '=', 'ta.ticket_id')
            ->where('ta.user_id', $user->id)
            ->where('tickets.ticket_type', 'individual')
            ->where(function ($query) {
                $query->whereNotNull('ta.ended_at')
                    ->orWhereNotNull('tickets.resolved_at');
            })
            ->select([
                'ta.ticket_id',
                'ta.assigned_at as started_at',
                'ta.ended_at',
                'tickets.resolved_at',
            ])
            ->get();

        // Ambil periode tanggung jawab GAMAS.
        $participants = DB::table('ticket_participants as tp')
            ->join('tickets', 'tickets.id', '=', 'tp.ticket_id')
            ->where('tp.user_id', $user->id)
            ->where('tickets.ticket_type', 'gamas')
            ->where(function ($query) {
                $query->whereNotNull('tp.left_at')
                    ->orWhereNotNull('tickets.resolved_at');
            })
            ->select([
                'tp.ticket_id',
                'tp.joined_at as started_at',
                'tp.left_at as ended_at',
                'tickets.resolved_at',
            ])
            ->get();

        // Gabungkan kedua jenis periode tanggung jawab.
        $intervals = $assignments
            ->concat($participants)
            ->map(function ($row) use ($periodStart, $periodEnd) {

                $start = Carbon::parse($row->started_at);

                $end = $row->ended_at
                    ? Carbon::parse($row->ended_at)
                    : null;

                $resolvedAt = $row->resolved_at
                    ? Carbon::parse($row->resolved_at)
                    : null;

                // Jika tiket sudah resolved, batas akhir tidak boleh
                // melewati waktu resolved.
                if (
                    $resolvedAt &&
                    (!$end || $resolvedAt->lt($end))
                ) {
                    $end = $resolvedAt;
                }

                // Periode yang belum berakhir tidak dihitung.
                if (!$end || $end->lte($start)) {
                    return null;
                }

                // Masukkan periode berdasarkan bulan berakhirnya
                // tanggung jawab.
                if (
                    $end->lt($periodStart) ||
                    $end->gt($periodEnd)
                ) {
                    return null;
                }

                return [
                    'ticket_id' => $row->ticket_id,
                    'start' => $start,
                    'end' => $end,
                ];
            })
            ->filter()
            ->values();

        if ($intervals->isEmpty()) {
            return [
                'total_seconds' => 0,
                'average_seconds' => null,
                'average_minutes' => null,
                'period_count' => 0,
            ];
        }

        // Ambil seluruh Stop Clock yang berpotensi beririsan
        // dengan periode tanggung jawab.
        $ticketIds = $intervals
            ->pluck('ticket_id')
            ->unique()
            ->values();

        $earliestStart = $intervals
            ->min(fn($item) => $item['start']->timestamp);

        $stopClocks = DB::table('ticket_stop_clocks')
            ->whereIn('ticket_id', $ticketIds)
            ->where('started_at', '<=', $periodEnd)
            ->where(function ($query) use ($earliestStart) {
                $query->whereNull('ended_at')
                    ->orWhere(
                        'ended_at',
                        '>=',
                        Carbon::createFromTimestamp($earliestStart)
                    );
            })
            ->get()
            ->groupBy('ticket_id');

        $totalSeconds = 0;

        foreach ($intervals as $interval) {
            $start = $interval['start'];
            $end = $interval['end'];

            $calendarSeconds = $end->timestamp - $start->timestamp;

            // Hitung durasi Stop Clock yang beririsan
            // dengan periode tanggung jawab.
            $blockedIntervals = [];

            foreach (
                $stopClocks->get($interval['ticket_id'], collect())
                as $clock
            ) {
                $clockStart = Carbon::parse($clock->started_at);

                $clockEnd = $clock->ended_at
                    ? Carbon::parse($clock->ended_at)
                    : $end->copy();

                $overlapStart = max(
                    $start->timestamp,
                    $clockStart->timestamp
                );

                $overlapEnd = min(
                    $end->timestamp,
                    $clockEnd->timestamp
                );

                if ($overlapEnd > $overlapStart) {
                    $blockedIntervals[] = [
                        'start' => $overlapStart,
                        'end' => $overlapEnd,
                    ];
                }
            }

            // Urutkan interval Stop Clock.
            usort(
                $blockedIntervals,
                fn($a, $b) => $a['start'] <=> $b['start']
            );

            // Gabungkan interval yang tumpang tindih agar
            // tidak terjadi pengurangan Stop Clock ganda.
            $blockedSeconds = 0;
            $mergedStart = null;
            $mergedEnd = null;

            foreach ($blockedIntervals as $blocked) {
                if ($mergedStart === null) {
                    $mergedStart = $blocked['start'];
                    $mergedEnd = $blocked['end'];
                    continue;
                }

                if ($blocked['start'] <= $mergedEnd) {
                    $mergedEnd = max($mergedEnd, $blocked['end']);
                } else {
                    $blockedSeconds += $mergedEnd - $mergedStart;

                    $mergedStart = $blocked['start'];
                    $mergedEnd = $blocked['end'];
                }
            }

            if ($mergedStart !== null) {
                $blockedSeconds += $mergedEnd - $mergedStart;
            }

            $activeSeconds = max(
                0,
                $calendarSeconds - $blockedSeconds
            );

            $totalSeconds += $activeSeconds;
        }

        $periodCount = $intervals->count();

        $averageSeconds = $periodCount > 0
            ? $totalSeconds / $periodCount
            : null;

        return [
            'total_seconds' => $totalSeconds,
            'average_seconds' => $averageSeconds !== null
                ? (int) round($averageSeconds)
                : null,
            'average_minutes' => $averageSeconds !== null
                ? round($averageSeconds / 60, 2)
                : null,
            'period_count' => $periodCount,
        ];
    }
}
