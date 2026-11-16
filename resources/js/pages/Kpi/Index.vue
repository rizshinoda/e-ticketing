<script setup lang="ts">
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';

interface Props {
    user: {
        id: number;
        name: string;
    };
    period: {
        year: number;
        month: number;
    };
    productivity: {
        credit: number;
        target: number;
        score: number;
        weight: number;
    };
    contributions: Pagination<Contribution>;
}
interface Contribution {
    ticket_id: number;
    ticket_number: string;
    ticket_type: 'individual' | 'gamas';
    closed_at: string;
    credit: string | number;
}
interface Pagination<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
    next_page_url: string | null;
    prev_page_url: string | null;
}
const props = defineProps<Props>();

const monthNames = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];
const formatDate = (value: string) => {
    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
};
const selectedPeriod = computed(
    () => `${props.period.year}-${String(props.period.month).padStart(2, '0')}`,
);

const periodLabel = computed(
    () => `${monthNames[props.period.month - 1]} ${props.period.year}`,
);

const progress = computed(() =>
    Math.min(Math.max(props.productivity.score, 0), 100),
);

const remaining = computed(() =>
    Math.max(props.productivity.target - props.productivity.credit, 0),
);

function changePeriod(value: string) {
    if (!value) return;

    const [year, month] = value.split('-').map(Number);

    router.get(
        '/kpi-saya',
        { year, month },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

const productivityAvailable = computed(() => props.productivity.target > 0);

const formatNumber = (value: number) =>
    Number(value).toLocaleString('id-ID', {
        maximumFractionDigits: 2,
    });
</script>

<template>
    <Head title="KPI Saya" />

    <div class="space-y-6">
        <!-- Header -->
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">KPI Saya</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Ringkasan pencapaian KPI {{ user.name }}.
                </p>
            </div>

            <input
                type="month"
                :value="selectedPeriod"
                @change="
                    changePeriod(($event.target as HTMLInputElement).value)
                "
                class="rounded-lg border border-input bg-background px-3 py-2 text-sm"
            />
        </div>

        <!-- Productivity -->
        <div class="rounded-xl border bg-card p-5 shadow-sm">
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start"
            >
                <div>
                    <p class="text-sm font-medium text-muted-foreground">
                        Productivity
                    </p>
                    <h2 class="mt-2 text-4xl font-bold tracking-tight">
                        {{
                            productivityAvailable
                                ? `${formatNumber(productivity.score)}%`
                                : 'Belum tersedia'
                        }}
                    </h2>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Pencapaian produktivitas {{ periodLabel }}
                    </p>
                </div>

                <div class="rounded-lg bg-primary/10 px-4 py-3">
                    <p class="text-xs text-muted-foreground">Bobot KPI</p>
                    <p class="text-2xl font-semibold">
                        {{ productivity.weight }}%
                    </p>
                </div>
            </div>

            <div class="mt-6">
                <div class="mb-2 flex justify-between text-sm">
                    <span class="text-muted-foreground"> Progress target </span>
                    <span class="font-medium">
                        {{
                            productivityAvailable
                                ? `${formatNumber(progress)}%`
                                : 'Belum tersedia'
                        }}
                    </span>
                </div>

                <div class="h-3 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full rounded-full bg-primary transition-all duration-500"
                        :style="{ width: `${progress}%` }"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Detail Productivity -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">Kredit diperoleh</p>
                <p class="mt-2 text-2xl font-semibold">
                    {{ formatNumber(productivity.credit) }}
                </p>
            </div>

            <div class="rounded-xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">Target bulanan</p>
                <p class="mt-2 text-2xl font-semibold">
                    {{ formatNumber(productivity.target) }}
                </p>
            </div>

            <div class="rounded-xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">Sisa target</p>
                <p class="mt-2 text-2xl font-semibold">
                    {{ productivityAvailable ? formatNumber(remaining) : '-' }}
                </p>
            </div>
        </div>

        <!-- Komponen KPI -->
        <div class="rounded-xl border bg-card p-5">
            <h2 class="font-semibold">Komponen KPI</h2>

            <div class="mt-4 divide-y">
                <div class="flex items-center justify-between py-4">
                    <div>
                        <p class="font-medium">Productivity</p>
                        <p class="text-xs text-muted-foreground">
                            Produktivitas berdasarkan kredit tiket
                        </p>
                    </div>
                    <span class="font-semibold">
                        {{
                            productivityAvailable
                                ? `${formatNumber(productivity.score)}%`
                                : 'Belum tersedia'
                        }}
                    </span>
                </div>

                <div class="flex items-center justify-between py-4">
                    <div>
                        <p class="font-medium">Speed</p>
                        <p class="text-xs text-muted-foreground">
                            Kecepatan penanganan tiket
                        </p>
                    </div>
                    <span class="text-sm text-muted-foreground">
                        Belum dihitung · 30%
                    </span>
                </div>

                <div class="flex items-center justify-between py-4">
                    <div>
                        <p class="font-medium">Quality</p>
                        <p class="text-xs text-muted-foreground">
                            Kualitas penanganan tiket
                        </p>
                    </div>
                    <span class="text-sm text-muted-foreground">
                        Belum dihitung · 30%
                    </span>
                </div>
            </div>
        </div>

        <!-- Riwayat Kontribusi Tiket -->

        <!-- Riwayat Kontribusi Tiket -->
        <div class="rounded-xl border bg-card p-5">
            <div
                class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center"
            >
                <div>
                    <h2 class="font-semibold">Riwayat Kontribusi Tiket</h2>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Daftar tiket yang menjadi kontribusi Productivity pada
                        periode {{ periodLabel }}.
                    </p>
                </div>

                <div class="text-sm text-muted-foreground">
                    {{ contributions.total }} tiket
                </div>
            </div>

            <!-- Daftar tiket -->
            <div v-if="contributions.data.length" class="mt-5 space-y-3">
                <div
                    v-for="item in contributions.data"
                    :key="item.ticket_id"
                    class="flex flex-col justify-between gap-3 rounded-lg border p-4 sm:flex-row sm:items-center"
                >
                    <div class="space-y-1">
                        <p class="font-medium">
                            {{ item.ticket_number }}
                        </p>

                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span
                                class="rounded-full px-2 py-1"
                                :class="
                                    item.ticket_type === 'gamas'
                                        ? 'bg-orange-500/10 text-orange-600'
                                        : 'bg-blue-500/10 text-blue-600'
                                "
                            >
                                {{
                                    item.ticket_type === 'gamas'
                                        ? 'GAMAS'
                                        : 'Individual'
                                }}
                            </span>

                            <span class="text-muted-foreground">
                                Closed {{ formatDate(item.closed_at) }}
                            </span>
                        </div>
                    </div>

                    <div class="sm:text-right">
                        <p class="text-lg font-semibold">
                            {{ formatNumber(Number(item.credit)) }}
                        </p>

                        <p class="text-xs text-muted-foreground">Kredit KPI</p>
                    </div>
                </div>
            </div>

            <!-- Jika tidak ada data -->
            <div
                v-else
                class="mt-5 rounded-lg border border-dashed p-8 text-center"
            >
                <p class="font-medium">Belum ada kontribusi tiket</p>

                <p class="mt-1 text-sm text-muted-foreground">
                    Belum ada tiket Closed yang menghasilkan kredit pada periode
                    {{ periodLabel }}.
                </p>
            </div>

            <!-- Pagination -->

            <!-- Pagination -->
            <div
                v-if="contributions.last_page > 1"
                class="mt-6 flex flex-col justify-between gap-3 border-t pt-4 sm:flex-row sm:items-center"
            >
                <p class="text-sm text-muted-foreground">
                    Menampilkan {{ contributions.from }}–{{
                        contributions.to
                    }}
                    dari {{ contributions.total }} tiket
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :disabled="!contributions.prev_page_url"
                        @click="
                            contributions.prev_page_url &&
                            router.get(
                                contributions.prev_page_url,
                                {},
                                {
                                    preserveScroll: true,
                                    preserveState: true,
                                },
                            )
                        "
                        class="rounded-md border px-4 py-2 text-sm transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        ← Sebelumnya
                    </button>

                    <button
                        type="button"
                        :disabled="!contributions.next_page_url"
                        @click="
                            contributions.next_page_url &&
                            router.get(
                                contributions.next_page_url,
                                {},
                                {
                                    preserveScroll: true,
                                    preserveState: true,
                                },
                            )
                        "
                        class="rounded-md border px-4 py-2 text-sm transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Berikutnya →
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
