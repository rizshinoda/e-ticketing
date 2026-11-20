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
    speed: {
        total_seconds: number;
        average_seconds: number | null;
        average_minutes: number | null;
        period_count: number;
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

/* ---------- Helper format ---------- */
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

const formatDate = (value: string) =>
    new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });

const formatNumber = (value: number | string) =>
    Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 });

/* Durasi dalam kata utuh: 90061 detik -> "1 hari 1 jam" */
const formatDuration = (seconds: number | null) => {
    if (seconds === null) return '-';
    if (seconds <= 0) return '0 menit';

    const totalMinutes = Math.round(seconds / 60);

    const days = Math.floor(totalMinutes / 1440);
    const remainingMinutes = totalMinutes % 1440;

    const hours = Math.floor(remainingMinutes / 60);
    const minutes = remainingMinutes % 60;

    const parts = [];

    if (days > 0) parts.push(`${days} hari`);
    if (hours > 0) parts.push(`${hours} jam`);
    if (minutes > 0) parts.push(`${minutes} menit`);

    return parts.join(' ');
};

/* ---------- Periode ---------- */
const selectedPeriod = computed(
    () => `${props.period.year}-${String(props.period.month).padStart(2, '0')}`,
);

const maxPeriod = computed(() => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
});

const periodLabel = computed(
    () => `${monthNames[props.period.month - 1]} ${props.period.year}`,
);

const isCurrentOrFuture = computed(() => {
    const now = new Date();
    return (
        props.period.year * 12 + props.period.month >=
        now.getFullYear() * 12 + now.getMonth() + 1
    );
});

function goToPeriod(year: number, month: number) {
    router.get(
        '/kpi-saya',
        { year, month },
        { preserveState: true, preserveScroll: true },
    );
}

function changePeriod(value: string) {
    if (!value) return;
    const [year, month] = value.split('-').map(Number);
    goToPeriod(year, month);
}

function shiftMonth(delta: number) {
    const date = new Date(props.period.year, props.period.month - 1 + delta, 1);
    goToPeriod(date.getFullYear(), date.getMonth() + 1);
}

/* ---------- Productivity ---------- */
const productivityAvailable = computed(() => props.productivity.target > 0);

const progress = computed(() =>
    Math.min(Math.max(props.productivity.score, 0), 100),
);

const remaining = computed(() =>
    Math.max(props.productivity.target - props.productivity.credit, 0),
);

const surplus = computed(() =>
    Math.max(props.productivity.credit - props.productivity.target, 0),
);

const status = computed(() => {
    const score = props.productivity.score;

    if (!productivityAvailable.value) {
        return {
            label: 'Target belum ditetapkan',
            badge: 'bg-muted text-muted-foreground',
            ring: 'stroke-muted-foreground/40',
        };
    }
    if (score >= 100) {
        return {
            label: 'Target tercapai',
            badge: 'bg-emerald-500/10 text-emerald-600',
            ring: 'stroke-emerald-500',
        };
    }
    if (score >= 75) {
        return {
            label: 'Hampir tercapai',
            badge: 'bg-sky-500/10 text-sky-600',
            ring: 'stroke-sky-500',
        };
    }
    if (score >= 50) {
        return {
            label: 'Sedang berjalan',
            badge: 'bg-amber-500/10 text-amber-600',
            ring: 'stroke-amber-500',
        };
    }
    return {
        label: 'Perlu dikejar',
        badge: 'bg-rose-500/10 text-rose-600',
        ring: 'stroke-rose-500',
    };
});

const summaryText = computed(() => {
    if (!productivityAvailable.value) {
        return 'Target bulanan untuk periode ini belum ditetapkan, sehingga skor belum bisa dihitung.';
    }
    if (surplus.value > 0) {
        return `Target terlampaui. Kamu punya kelebihan ${formatNumber(surplus.value)} kredit.`;
    }
    if (remaining.value === 0) {
        return 'Target bulan ini tercapai tepat.';
    }
    return `Kumpulkan ${formatNumber(remaining.value)} kredit lagi untuk mencapai target.`;
});

/* Progress ring (SVG) */
const RING_RADIUS = 52;
const RING_CIRCUMFERENCE = 2 * Math.PI * RING_RADIUS;
const ringOffset = computed(
    () => RING_CIRCUMFERENCE * (1 - progress.value / 100),
);

/* ---------- Speed (informasi saja, tidak masuk nilai KPI) ---------- */
const speedAvailable = computed(() => props.speed.period_count > 0);

/* ---------- Komposisi bobot KPI (donut) ---------- */
/* Bobot Quality = sisa dari bobot Productivity, jadi total selalu 100%. */
const qualityWeight = computed(() =>
    Math.max(100 - props.productivity.weight, 0),
);

const kpiComponents = computed(() => [
    {
        key: 'productivity',
        name: 'Productivity',
        description: 'Produktivitas berdasarkan kredit tiket',
        weight: props.productivity.weight,
        stroke: 'stroke-primary',
        dot: 'bg-primary',
        value: productivityAvailable.value
            ? `${formatNumber(props.productivity.score)}%`
            : null,
    },
    {
        key: 'quality',
        name: 'Quality',
        description: 'Kualitas penanganan tiket',
        weight: qualityWeight.value,
        stroke: 'stroke-violet-500',
        dot: 'bg-violet-500',
        value: null,
    },
]);

const DONUT_RADIUS = 40;
const DONUT_CIRCUMFERENCE = 2 * Math.PI * DONUT_RADIUS;

const donutSegments = computed(() => {
    const total =
        kpiComponents.value.reduce((sum, c) => sum + c.weight, 0) || 1;
    let offset = 0;

    return kpiComponents.value.map((c) => {
        const length = (c.weight / total) * DONUT_CIRCUMFERENCE;
        const segment = {
            key: c.key,
            stroke: c.stroke,
            dasharray: `${Math.max(length - 2, 0)} ${DONUT_CIRCUMFERENCE - Math.max(length - 2, 0)}`,
            dashoffset: -offset,
        };
        offset += length;
        return segment;
    });
});

/* ---------- Kontribusi ---------- */
const ticketCreditShare = (credit: string | number) => {
    if (!productivityAvailable.value) return null;
    return (Number(credit) / props.productivity.target) * 100;
};

function goToPage(url: string | null) {
    if (!url) return;
    router.get(url, {}, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <Head title="KPI Saya" />

    <div class="space-y-6">
        <!-- Header -->
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
        >
            <div class="pl-6">
                <h1 class="text-2xl font-semibold tracking-tight">KPI Saya</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Pencapaian {{ user.name }} pada {{ periodLabel }}.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    aria-label="Bulan sebelumnya"
                    class="flex h-10 w-10 items-center justify-center rounded-lg border border-input bg-background text-lg transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @click="shiftMonth(-1)"
                >
                    ‹
                </button>

                <input
                    type="month"
                    :value="selectedPeriod"
                    :max="maxPeriod"
                    aria-label="Pilih periode"
                    class="h-10 rounded-lg border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @change="
                        changePeriod(($event.target as HTMLInputElement).value)
                    "
                />

                <button
                    type="button"
                    aria-label="Bulan berikutnya"
                    :disabled="isCurrentOrFuture"
                    class="flex h-10 w-10 items-center justify-center rounded-lg border border-input bg-background text-lg transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                    @click="shiftMonth(1)"
                >
                    ›
                </button>
            </div>
        </div>

        <!-- Productivity (ringkasan utama) -->
        <section class="rounded-2xl border bg-card p-6 shadow-sm">
            <div
                class="flex flex-col items-center gap-8 sm:flex-row sm:items-center"
            >
                <!-- Ring progress -->
                <div class="relative h-44 w-44 shrink-0">
                    <svg
                        viewBox="0 0 120 120"
                        class="h-full w-full -rotate-90"
                        role="img"
                        :aria-label="
                            productivityAvailable
                                ? `Productivity ${formatNumber(productivity.score)} persen`
                                : 'Productivity belum tersedia'
                        "
                    >
                        <circle
                            cx="60"
                            cy="60"
                            :r="RING_RADIUS"
                            fill="none"
                            stroke-width="11"
                            class="stroke-muted"
                        />
                        <circle
                            v-if="productivityAvailable"
                            cx="60"
                            cy="60"
                            :r="RING_RADIUS"
                            fill="none"
                            stroke-width="11"
                            stroke-linecap="round"
                            :stroke-dasharray="RING_CIRCUMFERENCE"
                            :stroke-dashoffset="ringOffset"
                            class="transition-all duration-700 ease-out"
                            :class="status.ring"
                        />
                    </svg>

                    <div
                        class="absolute inset-0 flex flex-col items-center justify-center"
                    >
                        <template v-if="productivityAvailable">
                            <span class="text-4xl font-bold tracking-tight">
                                {{ formatNumber(productivity.score) }}%
                            </span>
                            <span class="mt-0.5 text-xs text-muted-foreground">
                                dari target
                            </span>
                        </template>
                        <span
                            v-else
                            class="px-6 text-center text-sm text-muted-foreground"
                        >
                            Belum tersedia
                        </span>
                    </div>
                </div>

                <!-- Penjelasan -->
                <div class="w-full min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-semibold">Productivity</h2>
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="status.badge"
                        >
                            {{ status.label }}
                        </span>
                    </div>

                    <p class="mt-2 max-w-md text-sm text-muted-foreground">
                        {{ summaryText }}
                    </p>

                    <dl class="mt-5 grid grid-cols-3 gap-3">
                        <div class="rounded-xl bg-muted/60 p-3">
                            <dt class="text-xs text-muted-foreground">
                                Kredit diperoleh
                            </dt>
                            <dd class="mt-1 text-xl font-semibold">
                                {{ formatNumber(productivity.credit) }}
                            </dd>
                        </div>

                        <div class="rounded-xl bg-muted/60 p-3">
                            <dt class="text-xs text-muted-foreground">
                                Target bulanan
                            </dt>
                            <dd class="mt-1 text-xl font-semibold">
                                {{ formatNumber(productivity.target) }}
                            </dd>
                        </div>

                        <div class="rounded-xl bg-muted/60 p-3">
                            <dt class="text-xs text-muted-foreground">
                                {{ surplus > 0 ? 'Kelebihan' : 'Sisa target' }}
                            </dt>
                            <dd class="mt-1 text-xl font-semibold">
                                {{
                                    !productivityAvailable
                                        ? '-'
                                        : formatNumber(
                                              surplus > 0 ? surplus : remaining,
                                          )
                                }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <!-- Speed (informasi saja) -->
        <section class="rounded-2xl border bg-card p-6">
            <div
                class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start"
            >
                <div>
                    <h2 class="font-semibold">Speed</h2>
                    <p class="mt-1 max-w-xl text-sm text-muted-foreground">
                        Seberapa lama kamu mengerjakan tiket yang selesai
                        ditangani pada {{ periodLabel }}.
                    </p>
                </div>

                <span
                    class="w-fit shrink-0 rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground"
                >
                    Hanya informasi, tidak memengaruhi nilai KPI
                </span>
            </div>

            <template v-if="speedAvailable">
                <!-- Ringkasan -->
                <dl class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl bg-muted/60 p-4">
                        <dt class="text-xs text-muted-foreground">
                            Rata-rata durasi handle
                        </dt>
                        <dd class="mt-1 text-xl font-semibold">
                            {{ formatDuration(speed.average_seconds) }}
                        </dd>
                    </div>

                    <div class="rounded-xl bg-muted/60 p-4">
                        <dt class="text-xs text-muted-foreground">
                            Jumlah periode handle
                        </dt>
                        <dd class="mt-1 text-xl font-semibold">
                            {{ speed.period_count }}
                        </dd>
                    </div>

                    <div class="rounded-xl bg-muted/60 p-4">
                        <dt class="text-xs text-muted-foreground">
                            Total durasi handle
                        </dt>
                        <dd class="mt-1 text-xl font-semibold">
                            {{ formatDuration(speed.total_seconds) }}
                        </dd>
                    </div>
                </dl>
            </template>

            <!-- Kosong -->
            <div
                v-else
                class="mt-5 rounded-xl border border-dashed p-8 text-center"
            >
                <p class="font-medium">Belum ada data Speed</p>
                <p class="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                    Data muncul setelah ada tiket yang selesai kamu tangani pada
                    {{ periodLabel }}.
                </p>
            </div>
        </section>

        <!-- Komponen KPI + donut bobot -->
        <section class="rounded-2xl border bg-card p-6">
            <div class="flex flex-col gap-1">
                <h2 class="font-semibold">Komponen KPI</h2>
                <p class="text-sm text-muted-foreground">
                    Nilai akhir KPI terdiri dari dua komponen penilaian.
                    Persentase menunjukkan bobot tiap komponen.
                </p>
            </div>

            <div
                class="mt-6 flex flex-col items-center gap-8 md:flex-row md:items-center"
            >
                <!-- Donut bobot -->
                <div class="relative h-40 w-40 shrink-0">
                    <svg
                        viewBox="0 0 100 100"
                        class="h-full w-full -rotate-90"
                        role="img"
                        aria-label="Komposisi bobot KPI"
                    >
                        <circle
                            cx="50"
                            cy="50"
                            :r="DONUT_RADIUS"
                            fill="none"
                            stroke-width="14"
                            class="stroke-muted"
                        />
                        <circle
                            v-for="segment in donutSegments"
                            :key="segment.key"
                            cx="50"
                            cy="50"
                            :r="DONUT_RADIUS"
                            fill="none"
                            stroke-width="14"
                            :stroke-dasharray="segment.dasharray"
                            :stroke-dashoffset="segment.dashoffset"
                            :class="segment.stroke"
                        />
                    </svg>

                    <div
                        class="absolute inset-0 flex flex-col items-center justify-center"
                    >
                        <span class="text-xs text-muted-foreground">
                            Total bobot
                        </span>
                        <span class="text-xl font-semibold">
                            {{
                                kpiComponents.reduce(
                                    (sum, c) => sum + c.weight,
                                    0,
                                )
                            }}%
                        </span>
                    </div>
                </div>

                <!-- Daftar komponen -->
                <ul class="w-full flex-1 divide-y">
                    <li
                        v-for="component in kpiComponents"
                        :key="component.key"
                        class="flex items-center justify-between gap-4 py-3.5 first:pt-0 last:pb-0"
                    >
                        <div class="flex min-w-0 items-start gap-3">
                            <span
                                class="mt-1.5 h-3 w-3 shrink-0 rounded-full"
                                :class="component.dot"
                            ></span>
                            <div class="min-w-0">
                                <p class="font-medium">
                                    {{ component.name }}
                                    <span
                                        class="ml-1 text-sm font-normal text-muted-foreground"
                                    >
                                        bobot {{ component.weight }}%
                                    </span>
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ component.description }}
                                </p>
                            </div>
                        </div>

                        <span
                            v-if="component.value"
                            class="shrink-0 text-lg font-semibold"
                        >
                            {{ component.value }}
                        </span>
                        <span
                            v-else
                            class="shrink-0 rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground"
                        >
                            {{
                                component.key === 'productivity'
                                    ? 'Belum tersedia'
                                    : 'Belum dihitung'
                            }}
                        </span>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Riwayat Kontribusi Tiket -->
        <section class="rounded-2xl border bg-card p-6">
            <div
                class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center"
            >
                <div>
                    <h2 class="font-semibold">Riwayat Kontribusi Tiket</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Tiket Closed yang menambah kredit Productivity pada
                        {{ periodLabel }}.
                    </p>
                </div>

                <span
                    class="w-fit rounded-full bg-muted px-3 py-1 text-sm font-medium"
                >
                    {{ contributions.total }} tiket
                </span>
            </div>

            <!-- Daftar tiket -->
            <ul v-if="contributions.data.length" class="mt-5 space-y-2.5">
                <li
                    v-for="item in contributions.data"
                    :key="item.ticket_id"
                    class="flex items-center gap-4 rounded-xl border p-4 transition-colors hover:bg-muted/40"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-xs font-bold"
                        :class="
                            item.ticket_type === 'gamas'
                                ? 'bg-orange-500/10 text-orange-600'
                                : 'bg-blue-500/10 text-blue-600'
                        "
                        aria-hidden="true"
                    >
                        {{ item.ticket_type === 'gamas' ? 'G' : 'I' }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">
                            {{ item.ticket_number }}
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{
                                item.ticket_type === 'gamas'
                                    ? 'Tiket GAMAS'
                                    : 'Tiket Individual'
                            }}
                            · Closed {{ formatDate(item.closed_at) }}
                        </p>
                    </div>

                    <div class="shrink-0 text-right">
                        <p class="text-lg font-semibold text-emerald-600">
                            +{{ formatNumber(item.credit) }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <template
                                v-if="ticketCreditShare(item.credit) !== null"
                            >
                                {{
                                    formatNumber(
                                        ticketCreditShare(item.credit) ?? 0,
                                    )
                                }}% dari target
                            </template>
                            <template v-else>Kredit</template>
                        </p>
                    </div>
                </li>
            </ul>

            <!-- Kosong -->
            <div
                v-else
                class="mt-5 rounded-xl border border-dashed p-10 text-center"
            >
                <p class="font-medium">Belum ada kontribusi tiket</p>
                <p class="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                    Kredit muncul di sini setelah tiketmu berstatus Closed. Coba
                    pilih bulan lain untuk melihat riwayat sebelumnya.
                </p>
            </div>

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
                    <span class="hidden sm:inline">
                        · Halaman {{ contributions.current_page }} dari
                        {{ contributions.last_page }}
                    </span>
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :disabled="!contributions.prev_page_url"
                        class="rounded-lg border px-4 py-2 text-sm transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                        @click="goToPage(contributions.prev_page_url)"
                    >
                        ← Sebelumnya
                    </button>

                    <button
                        type="button"
                        :disabled="!contributions.next_page_url"
                        class="rounded-lg border px-4 py-2 text-sm transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                        @click="goToPage(contributions.next_page_url)"
                    >
                        Berikutnya →
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>
