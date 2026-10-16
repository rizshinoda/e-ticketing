<?php

namespace App\Services;

use App\Models\SlaRule;
use Carbon\CarbonInterface;
use LogicException;

class SlaRuleResolver
{
    /**
     * Ambil aturan SLA Global untuk pelanggan.
     */
    public function resolveGlobal(
        int $pelangganId,
        CarbonInterface $at
    ): ?SlaRule {
        return $this->resolve(
            pelangganId: $pelangganId,
            scope: 'global',
            onlineBillingId: null,
            at: $at,
        );
    }

    /**
     * Ambil aturan SLA khusus site.
     */
    public function resolveSite(
        int $pelangganId,
        int $onlineBillingId,
        CarbonInterface $at
    ): ?SlaRule {
        return $this->resolve(
            pelangganId: $pelangganId,
            scope: 'site',
            onlineBillingId: $onlineBillingId,
            at: $at,
        );
    }

    /**
     * Cari satu aturan yang berlaku pada tanggal tertentu.
     */
    private function resolve(
        int $pelangganId,
        string $scope,
        ?int $onlineBillingId,
        CarbonInterface $at
    ): ?SlaRule {
        $date = $at->toDateString();

        $query = SlaRule::query()
            ->where('pelanggan_id', $pelangganId)
            ->where('scope', $scope)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $date);
            });

        if ($scope === 'global') {
            $query->whereNull('online_billing_id');
        } else {
            if ($onlineBillingId === null) {
                return null;
            }

            $query->where('online_billing_id', $onlineBillingId);
        }

        // Jangan memilih aturan secara sembarangan jika ada
        // dua aturan aktif untuk cakupan yang sama.
        $rules = $query
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->limit(2)
            ->get();

        if ($rules->count() > 1) {
            throw new LogicException(
                "Terdapat aturan SLA {$scope} yang tumpang tindih " .
                    "untuk pelanggan ID {$pelangganId} pada tanggal {$date}."
            );
        }

        return $rules->first();
    }
}
