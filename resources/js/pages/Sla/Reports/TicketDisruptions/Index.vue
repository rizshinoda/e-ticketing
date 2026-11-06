<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { VisAxis, VisGroupedBar, VisXYContainer } from '@unovis/vue';
import {
    ChartContainer,
    ChartCrosshair,
    ChartTooltip,
    ChartTooltipContent,
    componentToString,
    type ChartConfig,
} from '@/components/ui/chart';

interface Filters {
    month: number;
    year: number;
}

interface Summary {
    total_tickets: number;
    total_incidents: number;
    affected_sites: number;
    affected_instansi: number;
    sites_without_instansi: number;
    total_duration_seconds: number;
}

interface DailyTrend {
    date: string;
    incident_count: number;
}

interface CategoryRanking {
    category_name: string;
    incident_count: number;
    ticket_count: number;
    duration_seconds: number;
}

interface SiteRanking {
    site_name: string;
    customer_name?: string | null;
    no_jaringan?: string | null;
    instansi_name?: string | null;
    ticket_count: number;
    incident_count: number;
    duration_seconds: number;
}

interface InstansiRanking {
    instansi_name: string;
    site_count: number;
    ticket_count: number;
    incident_count: number;
    duration_seconds: number;
}

interface IncidentSite {
    site_name?: string | null;
    nama_site?: string | null;
}

type IncidentSiteValue = string | IncidentSite;

interface Incident {
    ticket_id: number;
    ticket_number: string;
    incident_id: number;
    incident_number?: number | null;
    category_name: string;
    reported_at: string;
    resolved_at?: string | null;
    duration_seconds: number;
    sites: IncidentSiteValue[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedIncidents {
    data: Incident[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
interface ChartPoint {
    day: number;
    date: string;
    incident_count: number;
}

const props = defineProps<{
    filters: Filters;
    summary: Summary;
    dailyTrend: DailyTrend[];
    categoryRanking: CategoryRanking[];
    siteRanking: SiteRanking[];
    instansiRanking: InstansiRanking[];
    incidents: PaginatedIncidents;
}>();

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

const currentYear = new Date().getFullYear();

const years = Array.from({ length: 11 }, (_, index) => currentYear - index);

const selectedMonth = ref(String(props.filters.month));
const selectedYear = ref(String(props.filters.year));

watch(
    () => props.filters,
    (filters) => {
        selectedMonth.value = String(filters.month);
        selectedYear.value = String(filters.year);
    },
    { deep: true },
);

const selectedPeriod = computed(() => {
    const monthIndex = Number(selectedMonth.value) - 1;
    const monthName = monthNames[monthIndex] ?? '';

    return `${monthName} ${selectedYear.value}`;
});

function applyFilters() {
    router.get(
        '/reports/ticket-disruptions',
        {
            month: Number(selectedMonth.value),
            year: Number(selectedYear.value),
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function formatNumber(value: number | null | undefined): string {
    return new Intl.NumberFormat('id-ID').format(value ?? 0);
}

function formatDuration(seconds: number | null | undefined): string {
    let remaining = Math.max(0, Number(seconds ?? 0));

    const days = Math.floor(remaining / 86400);
    remaining %= 86400;

    const hours = Math.floor(remaining / 3600);
    remaining %= 3600;

    const minutes = Math.floor(remaining / 60);
    const secs = remaining % 60;

    const parts: string[] = [];

    if (days > 0) parts.push(`${days} hari`);
    if (hours > 0) parts.push(`${hours} jam`);
    if (minutes > 0) parts.push(`${minutes} menit`);

    if (parts.length === 0) {
        parts.push(`${secs} detik`);
    }

    return parts.join(' ');
}

function formatDate(value: string | null | undefined): string {
    if (!value) return '-';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Asia/Jakarta',
    }).format(date);
}

function formatSites(sites: IncidentSiteValue[] | null | undefined): string {
    if (!Array.isArray(sites) || sites.length === 0) {
        return '-';
    }

    const names = sites
        .map((site) => {
            if (typeof site === 'string') {
                return site;
            }

            return site.site_name ?? site.nama_site ?? '';
        })
        .filter((name) => Boolean(name));

    return names.length > 0 ? names.join(', ') : '-';
}

/* ---------- Chart tren insiden harian (shadcn-vue + Unovis) ---------- */

const chartConfig = {
    incident_count: {
        label: 'Insiden',
        color: 'var(--chart-1)', // jika tema lama: 'hsl(var(--chart-1))'
    },
} satisfies ChartConfig;

const chartData = computed<ChartPoint[]>(() => {
    const month = Number(props.filters.month);
    const year = Number(props.filters.year);
    const daysInMonth = new Date(year, month, 0).getDate();

    const countByDay = new Map<number, number>();

    props.dailyTrend.forEach((item) => {
        const day = Number(item.date.slice(-2));
        countByDay.set(day, Number(item.incident_count ?? 0));
    });

    return Array.from({ length: daysInMonth }, (_, index) => {
        const day = index + 1;

        return {
            day,
            date: `${String(day).padStart(2, '0')} ${monthNames[month - 1]} ${year}`,
            incident_count: countByDay.get(day) ?? 0,
        };
    });
});

const hasTrendData = computed(() =>
    props.dailyTrend.some((item) => Number(item.incident_count) > 0),
);
</script>

<template>
    <div class="min-w-full space-y-6 bg-background p-4 text-foreground md:p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold tracking-tight text-foreground"
                >
                    Laporan Tiket dan Kendala
                </h1>

                <p class="mt-1 text-sm text-muted-foreground">
                    Rekapitulasi tiket dan insiden berdasarkan tanggal laporan
                    insiden.
                </p>
            </div>

            <!-- Filter periode -->
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                <div>
                    <label
                        for="month"
                        class="mb-1 block text-sm font-medium text-foreground"
                    >
                        Bulan
                    </label>

                    <select
                        id="month"
                        v-model="selectedMonth"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring sm:w-40"
                    >
                        <option
                            v-for="(month, index) in monthNames"
                            :key="index"
                            :value="String(index + 1)"
                        >
                            {{ month }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="year"
                        class="mb-1 block text-sm font-medium text-foreground"
                    >
                        Tahun
                    </label>

                    <select
                        id="year"
                        v-model="selectedYear"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring sm:w-32"
                    >
                        <option
                            v-for="year in years"
                            :key="year"
                            :value="String(year)"
                        >
                            {{ year }}
                        </option>
                    </select>
                </div>

                <button
                    type="button"
                    @click="applyFilters"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                >
                    Tampilkan
                </button>
                <a
                    :href="`/reports/ticket-disruptions/export/excel?month=${selectedMonth}&year=${selectedYear}`"
                    class="inline-flex items-center justify-center rounded-lg border border-border bg-card px-4 py-2 text-sm font-medium text-foreground shadow-sm transition-colors hover:bg-muted"
                >
                    Export Excel
                </a>
                <a
                    :href="`/reports/ticket-disruptions/export/pdf?month=${selectedMonth}&year=${selectedYear}`"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm font-medium text-foreground shadow-sm transition-colors hover:bg-muted"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path
                            d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
                        />
                        <path d="M14 2v6h6" />
                        <path d="M8 13h8" />
                        <path d="M8 17h8" />
                    </svg>

                    Export PDFD
                </a>
            </div>
        </div>

        <div class="text-sm font-medium text-muted-foreground">
            Periode laporan:
            <span class="text-foreground">{{ selectedPeriod }}</span>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                class="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm"
            >
                <p class="text-sm text-muted-foreground">Total Tiket</p>

                <p
                    class="mt-2 text-2xl font-semibold tracking-tight text-foreground"
                >
                    {{ formatNumber(summary.total_tickets) }}
                </p>

                <p class="mt-1 text-xs text-muted-foreground">
                    Tiket unik pada periode terpilih
                </p>
            </div>

            <div
                class="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm"
            >
                <p class="text-sm text-muted-foreground">Total Insiden</p>

                <p
                    class="mt-2 text-2xl font-semibold tracking-tight text-foreground"
                >
                    {{ formatNumber(summary.total_incidents) }}
                </p>

                <p class="mt-1 text-xs text-muted-foreground">
                    Insiden unik berdasarkan laporan kendala
                </p>
            </div>

            <div
                class="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm"
            >
                <p class="text-sm text-muted-foreground">Site Terdampak</p>

                <p
                    class="mt-2 text-2xl font-semibold tracking-tight text-foreground"
                >
                    {{ formatNumber(summary.affected_sites) }}
                </p>

                <p class="mt-1 text-xs text-muted-foreground">
                    Site unik yang tercatat pada tiket
                </p>
            </div>

            <div
                class="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm"
            >
                <p class="text-sm text-muted-foreground">Instansi Terdampak</p>

                <p
                    class="mt-2 text-2xl font-semibold tracking-tight text-foreground"
                >
                    {{ formatNumber(summary.affected_instansi) }}
                </p>

                <p class="mt-1 text-xs text-muted-foreground">
                    Instansi unik yang memiliki site terdampak
                </p>
            </div>
        </div>

        <!-- Total duration -->
        <div
            class="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm"
        >
            <p class="text-sm text-muted-foreground">
                Total Durasi Penanganan Insiden
            </p>

            <p
                class="mt-2 text-xl font-semibold tracking-tight text-foreground"
            >
                {{ formatDuration(summary.total_duration_seconds) }}
            </p>

            <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                Durasi dihitung dari waktu pelaporan sampai waktu penyelesaian.
                Insiden yang belum selesai dihitung sampai batas akhir periode
                laporan atau waktu saat ini. *Angka ini bukan perhitungan
                downtime SLA.
            </p>

            <p
                v-if="summary.sites_without_instansi > 0"
                class="mt-2 text-xs font-medium text-amber-600 dark:text-amber-400"
            >
                {{ formatNumber(summary.sites_without_instansi) }} site belum
                memiliki data lengkap.
            </p>
        </div>

        <!-- Daily trend -->
        <section
            class="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm"
        >
            <div class="mb-5">
                <h2 class="text-base font-semibold text-foreground">
                    Tren Insiden Harian
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Jumlah insiden berdasarkan tanggal reported_at.
                </p>
            </div>

            <ChartContainer
                v-if="hasTrendData"
                :config="chartConfig"
                class="h-64 w-full"
            >
                <VisXYContainer
                    :data="chartData"
                    :margin="{ left: 0, right: 8, top: 8 }"
                    :y-domain="[0, undefined]"
                >
                    <VisGroupedBar
                        :x="(d: ChartPoint) => d.day"
                        :y="[(d: ChartPoint) => d.incident_count]"
                        :color="() => 'var(--color-incident_count)'"
                        :rounded-corners="4"
                        :bar-padding="0.2"
                    />

                    <VisAxis
                        type="x"
                        :num-ticks="chartData.length"
                        :tick-format="(d: number) => String(d)"
                        :tick-line="false"
                        :domain-line="false"
                        :grid-line="false"
                    />

                    <VisAxis
                        type="y"
                        :tick-format="
                            (d: number) =>
                                Number.isInteger(d) ? String(d) : ''
                        "
                        :tick-line="false"
                        :domain-line="false"
                    />

                    <ChartTooltip />

                    <ChartCrosshair
                        :template="
                            componentToString(
                                chartConfig,
                                ChartTooltipContent,
                                {
                                    labelFormatter: (d: number | Date) =>
                                        chartData[Number(d) - 1]?.date ?? '',
                                },
                            )
                        "
                        :color="() => 'var(--color-incident_count)'"
                    />
                </VisXYContainer>
            </ChartContainer>

            <div
                v-else
                class="rounded-lg border border-dashed border-border py-10 text-center text-sm text-muted-foreground"
            >
                Belum ada data insiden pada periode ini.
            </div>
        </section>

        <!-- Ranking kategori -->
        <section
            class="overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm"
        >
            <div class="border-b border-border p-5">
                <h2 class="text-base font-semibold text-foreground">
                    Rekap Kendala
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Seluruh kategori kendala, baik yang termasuk downtime maupun
                    bukan downtime.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[650px] text-left text-sm">
                    <thead class="bg-muted/60 text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-medium">
                                Kategori Kendala
                            </th>
                            <th class="px-5 py-3 text-right font-medium">
                                Total Insiden
                            </th>
                            <th class="px-5 py-3 text-right font-medium">
                                Total Tiket
                            </th>
                            <th class="px-5 py-3 text-right font-medium">
                                Durasi Penanganan
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="(item, index) in categoryRanking"
                            :key="index"
                            class="transition-colors hover:bg-muted/40"
                        >
                            <td class="px-5 py-3 font-medium text-foreground">
                                {{ item.category_name }}
                            </td>

                            <td
                                class="px-5 py-3 text-right text-muted-foreground tabular-nums"
                            >
                                {{ formatNumber(item.incident_count) }}
                            </td>

                            <td
                                class="px-5 py-3 text-right text-muted-foreground tabular-nums"
                            >
                                {{ formatNumber(item.ticket_count) }}
                            </td>

                            <td
                                class="px-5 py-3 text-right text-muted-foreground"
                            >
                                {{ formatDuration(item.duration_seconds) }}
                            </td>
                        </tr>

                        <tr v-if="categoryRanking.length === 0">
                            <td
                                colspan="4"
                                class="px-5 py-8 text-center text-muted-foreground"
                            >
                                Belum ada data kategori kendala.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Ranking site -->
        <section
            class="overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm"
        >
            <div class="border-b border-border p-5">
                <h2 class="text-base font-semibold text-foreground">
                    Site Paling Sering Mengalami Kendala
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Peringkat berdasarkan jumlah insiden dan tiket unik.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-left text-sm">
                    <thead class="bg-muted/60 text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-medium">Site</th>
                            <th class="px-5 py-3 font-medium">Pelanggan</th>
                            <th class="px-5 py-3 font-medium">No. Jaringan</th>
                            <th class="px-5 py-3 font-medium">Instansi</th>
                            <th class="px-5 py-3 text-right font-medium">
                                Tiket
                            </th>
                            <th class="px-5 py-3 text-right font-medium">
                                Insiden
                            </th>
                            <th class="px-5 py-3 text-right font-medium">
                                Durasi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="(item, index) in siteRanking"
                            :key="index"
                            class="transition-colors hover:bg-muted/40"
                        >
                            <td class="px-5 py-3 font-medium text-foreground">
                                {{ item.site_name || '-' }}
                            </td>

                            <td class="px-5 py-3 text-muted-foreground">
                                {{ item.customer_name || '-' }}
                            </td>

                            <td class="px-5 py-3 text-muted-foreground">
                                {{ item.no_jaringan || '-' }}
                            </td>

                            <td class="px-5 py-3 text-muted-foreground">
                                {{ item.instansi_name || '-' }}
                            </td>

                            <td
                                class="px-5 py-3 text-right text-muted-foreground tabular-nums"
                            >
                                {{ formatNumber(item.ticket_count) }}
                            </td>

                            <td
                                class="px-5 py-3 text-right text-muted-foreground tabular-nums"
                            >
                                {{ formatNumber(item.incident_count) }}
                            </td>

                            <td
                                class="px-5 py-3 text-right text-muted-foreground"
                            >
                                {{ formatDuration(item.duration_seconds) }}
                            </td>
                        </tr>

                        <tr v-if="siteRanking.length === 0">
                            <td
                                colspan="7"
                                class="px-5 py-8 text-center text-muted-foreground"
                            >
                                Belum ada data site terdampak.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Ranking instansi -->
        <section
            class="overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm"
        >
            <div class="border-b border-border p-5">
                <h2 class="text-base font-semibold text-foreground">
                    Instansi Paling Sering Mengalami Kendala
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Satu insiden dihitung satu kali untuk setiap instansi,
                    meskipun terdapat beberapa site dari instansi yang sama pada
                    tiket tersebut.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[750px] text-left text-sm">
                    <thead class="bg-muted/60 text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-medium">Instansi</th>
                            <th class="px-5 py-3 text-right font-medium">
                                Jumlah Site
                            </th>
                            <th class="px-5 py-3 text-right font-medium">
                                Total Tiket
                            </th>
                            <th class="px-5 py-3 text-right font-medium">
                                Total Insiden
                            </th>
                            <th class="px-5 py-3 text-right font-medium">
                                Durasi Penanganan
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="(item, index) in instansiRanking"
                            :key="index"
                            class="transition-colors hover:bg-muted/40"
                        >
                            <td class="px-5 py-3 font-medium text-foreground">
                                {{ item.instansi_name }}
                            </td>

                            <td
                                class="px-5 py-3 text-right text-muted-foreground tabular-nums"
                            >
                                {{ formatNumber(item.site_count) }}
                            </td>

                            <td
                                class="px-5 py-3 text-right text-muted-foreground tabular-nums"
                            >
                                {{ formatNumber(item.ticket_count) }}
                            </td>

                            <td
                                class="px-5 py-3 text-right text-muted-foreground tabular-nums"
                            >
                                {{ formatNumber(item.incident_count) }}
                            </td>

                            <td
                                class="px-5 py-3 text-right text-muted-foreground"
                            >
                                {{ formatDuration(item.duration_seconds) }}
                            </td>
                        </tr>

                        <tr v-if="instansiRanking.length === 0">
                            <td
                                colspan="5"
                                class="px-5 py-8 text-center text-muted-foreground"
                            >
                                Belum ada data instansi terdampak.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Detail insiden -->
        <section
            class="overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm"
        >
            <div class="border-b border-border p-5">
                <h2 class="text-base font-semibold text-foreground">
                    Detail Tiket dan Insiden
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Daftar insiden yang dilaporkan pada {{ selectedPeriod }}.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] text-left text-sm">
                    <thead class="bg-muted/60 text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-medium">No. Tiket</th>
                            <th class="px-5 py-3 font-medium">No. Insiden</th>
                            <th class="px-5 py-3 font-medium">
                                Kategori Kendala
                            </th>
                            <th class="px-5 py-3 font-medium">
                                Site Terdampak
                            </th>
                            <th class="px-5 py-3 font-medium">Dilaporkan</th>
                            <th class="px-5 py-3 font-medium">Diselesaikan</th>
                            <th class="px-5 py-3 font-medium">
                                Durasi Penanganan
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="item in incidents.data"
                            :key="item.incident_id"
                            class="transition-colors hover:bg-muted/40"
                        >
                            <td class="px-5 py-3 font-medium text-primary">
                                {{ item.ticket_number }}
                            </td>

                            <td class="px-5 py-3 text-muted-foreground">
                                {{
                                    item.incident_number
                                        ? `#${item.incident_number}`
                                        : '-'
                                }}
                            </td>

                            <td class="px-5 py-3 text-muted-foreground">
                                {{ item.category_name || 'Tanpa kategori' }}
                            </td>

                            <td
                                class="max-w-xs px-5 py-3 whitespace-normal text-muted-foreground"
                            >
                                {{ formatSites(item.sites) }}
                            </td>

                            <td
                                class="px-5 py-3 whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDate(item.reported_at) }}
                            </td>

                            <td
                                class="px-5 py-3 whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDate(item.resolved_at) }}
                            </td>

                            <td
                                class="px-5 py-3 whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDuration(item.duration_seconds) }}
                            </td>
                        </tr>

                        <tr v-if="incidents.data.length === 0">
                            <td
                                colspan="7"
                                class="px-5 py-8 text-center text-muted-foreground"
                            >
                                Belum ada insiden pada periode ini.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <!-- Pagination -->
            <div
                class="flex flex-col gap-3 border-t border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-muted-foreground">
                    Menampilkan {{ incidents.from ?? 0 }}–{{
                        incidents.to ?? 0
                    }}
                    dari {{ formatNumber(incidents.total) }} insiden
                </p>

                <div class="flex items-center gap-2">
                    <!-- Sebelumnya -->
                    <Link
                        v-if="incidents.prev_page_url"
                        :href="incidents.prev_page_url"
                        preserve-scroll
                        class="inline-flex items-center justify-center rounded-lg border border-border bg-background px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                    >
                        Sebelumnya
                    </Link>

                    <button
                        v-else
                        type="button"
                        disabled
                        class="cursor-not-allowed rounded-lg border border-border bg-muted px-4 py-2 text-sm font-medium text-muted-foreground opacity-50"
                    >
                        Sebelumnya
                    </button>

                    <!-- Informasi halaman -->
                    <span class="px-2 text-sm text-muted-foreground">
                        {{ incidents.current_page }} / {{ incidents.last_page }}
                    </span>

                    <!-- Berikutnya -->
                    <Link
                        v-if="incidents.next_page_url"
                        :href="incidents.next_page_url"
                        preserve-scroll
                        class="inline-flex items-center justify-center rounded-lg border border-border bg-background px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                    >
                        Berikutnya
                    </Link>

                    <button
                        v-else
                        type="button"
                        disabled
                        class="cursor-not-allowed rounded-lg border border-border bg-muted px-4 py-2 text-sm font-medium text-muted-foreground opacity-50"
                    >
                        Berikutnya
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>
