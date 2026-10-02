<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { dashboard } from '@/routes';

import { Donut, GroupedBar } from '@unovis/ts';
import {
    VisArea,
    VisAxis,
    VisCrosshair,
    VisDonut,
    VisGroupedBar,
    VisLine,
    VisSingleContainer,
    VisTooltip,
    VisXYContainer,
} from '@unovis/vue';
import {
    Activity,
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    Lock,
    MonitorPlay,
    TicketPlus,
    TrendingDown,
    TrendingUp,
    X,
} from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

/* ---------- Types ---------- */
interface StatusChartItem {
    status: string;
    total: number;
}
interface MonthlyTicketChartItem {
    month: string;
    total: number;
}
interface TicketStats {
    total: number;
    open: number;
    on_progress: number;
    resolved: number;
    closed: number;
}
interface PriorityStats {
    critical: number;
    high: number;
    medium: number;
    low: number;
}
type StatusKey = 'open' | 'on_progress' | 'resolved' | 'closed';

const props = defineProps<{
    ticketStats: TicketStats;
    priorityStats: PriorityStats;
    criticalActive: number;

    statusChart: StatusChartItem[];
    monthlyTicketChart: MonthlyTicketChartItem[];
}>();

/* ---------- Konfigurasi status ---------- */
const statusMeta: Record<
    StatusKey,
    { label: string; hint: string; color: string; icon: any; tint: string }
> = {
    open: {
        label: 'Open',
        hint: 'Menunggu ditangani',
        color: '#3b82f6',
        icon: Activity,
        tint: 'bg-blue-500/10 text-blue-500',
    },
    on_progress: {
        label: 'On Progress',
        hint: 'Sedang diproses',
        color: '#f59e0b',
        icon: Activity,
        tint: 'bg-amber-500/10 text-amber-500',
    },
    resolved: {
        label: 'Resolved',
        hint: 'Selesai ditangani',
        color: '#10b981',
        icon: CheckCircle2,
        tint: 'bg-emerald-500/10 text-emerald-500',
    },
    closed: {
        label: 'Closed',
        hint: 'Ticket ditutup',
        color: '#94a3b8',
        icon: Lock,
        tint: 'bg-slate-500/10 text-slate-400',
    },
};
const statusKeys = Object.keys(statusMeta) as StatusKey[];

// Cocokkan label status dari backend ("On Progress", "on_progress", dst.) ke key
const toKey = (s: string): StatusKey | null => {
    const k = s
        .toLowerCase()
        .trim()
        .replace(/[\s-]+/g, '_');

    return (statusKeys as string[]).includes(k) ? (k as StatusKey) : null;
};

const percent = (value: number) =>
    props.ticketStats.total
        ? Math.round((value / props.ticketStats.total) * 100)
        : 0;

/* ---------- Interaksi: filter status ---------- */
const activeStatus = ref<StatusKey | null>(null);
const toggleStatus = (key: StatusKey) => {
    activeStatus.value = activeStatus.value === key ? null : key;
};

/* ---------- Chart status ---------- */
const chartData = computed(() =>
    props.statusChart.map((item, index) => ({
        index,
        status: item.status,
        total: item.total,
        key: toKey(item.status),
    })),
);
type StatusDatum = (typeof chartData.value)[number];

const statusBarColor = (d: StatusDatum) => {
    const base = d.key ? statusMeta[d.key].color : '#3b82f6';

    // Bar lain diredupkan saat satu status dipilih
    return activeStatus.value && d.key !== activeStatus.value
        ? `${base}40`
        : base;
};

const statusTooltip = {
    [GroupedBar.selectors.bar]: (d: StatusDatum) => `
        <div style="font-weight:600">${d.status}</div>
        <div style="margin-top:4px">
            <strong>${d.total}</strong> ticket · ${percent(d.total)}%
        </div>`,
};

/* ---------- Chart prioritas ---------- */
const priorities = computed(() => [
    {
        key: 'critical',
        label: 'Critical',
        color: '#ef4444',
        total: props.priorityStats.critical,
    },
    {
        key: 'high',
        label: 'High',
        color: '#f97316',
        total: props.priorityStats.high,
    },
    {
        key: 'medium',
        label: 'Medium',
        color: '#eab308',
        total: props.priorityStats.medium,
    },
    {
        key: 'low',
        label: 'Low',
        color: '#94a3b8',
        total: props.priorityStats.low,
    },
]);
type PriorityDatum = (typeof priorities.value)[number];

const priorityTotal = computed(() =>
    priorities.value.reduce((sum, p) => sum + p.total, 0),
);
const hoveredPriority = ref<string | null>(null);

const priorityColor = (d: PriorityDatum) =>
    hoveredPriority.value && hoveredPriority.value !== d.key
        ? `${d.color}30`
        : d.color;

const priorityTooltip = {
    [Donut.selectors.segment]: (d: { data: PriorityDatum }) => `
        <div style="font-weight:600">${d.data.label}</div>
        <div style="margin-top:4px"><strong>${d.data.total}</strong> ticket</div>`,
};

/* ---------- Chart bulanan ---------- */
const period = ref('12');
const monthly = computed(() =>
    props.monthlyTicketChart
        .slice(-Number(period.value))
        .map((item, index) => ({
            index,
            month: item.month,
            total: item.total,
        })),
);
const monthlySum = computed(() =>
    monthly.value.reduce((sum, d) => sum + d.total, 0),
);
const monthlyAvg = computed(() =>
    monthly.value.length
        ? Math.round(monthlySum.value / monthly.value.length)
        : 0,
);
// Perubahan bulan terakhir dibanding bulan sebelumnya
const monthlyDelta = computed(() => {
    const n = props.monthlyTicketChart.length;

    if (n < 2) {
        return null;
    }

    const last = props.monthlyTicketChart[n - 1].total;
    const prev = props.monthlyTicketChart[n - 2].total;

    if (!prev) {
        return null;
    }

    return Math.round(((last - prev) / prev) * 100);
});
const monthTick = (i: number) =>
    monthly.value.find((d) => d.index === i)?.month ?? '';
const crosshairTemplate = (d: { month: string; total: number }) => `
    <div style="font-weight:600">${d.month}</div>
    <div style="margin-top:4px"><strong>${d.total}</strong> ticket dilaporkan</div>`;
</script>

<template>
    <Head title="Dashboard" />

    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Ringkasan kondisi ticket saat ini.
                </p>
            </div>

            <Button as-child class="gap-2">
                <Link href="/tickets/monitoring">
                    <MonitorPlay class="h-4 w-4" />
                    Buka Monitoring
                </Link>
            </Button>
        </div>

        <!-- Peringatan ticket critical -->
        <div
            v-if="props.criticalActive > 0"
            class="flex items-center gap-3 rounded-xl border border-red-500/30 bg-red-500/5 p-4"
        >
            <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-500/10 text-red-500"
            >
                <AlertTriangle class="h-4 w-4" />
            </div>
            <p class="text-sm">
                Ada
                <strong>{{ props.criticalActive }} ticket Critical</strong>
                yang perlu segera ditangani.
            </p>
        </div>

        <!-- Statistik: klik kartu untuk menyorot status di chart -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <Card class="relative overflow-hidden">
                <CardContent class="p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Total Ticket
                            </p>
                            <p
                                class="mt-2 text-3xl font-semibold tracking-tight tabular-nums"
                            >
                                {{ props.ticketStats.total }}
                            </p>
                        </div>
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <TicketPlus class="h-5 w-5" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-muted-foreground">
                        Seluruh ticket
                    </p>
                </CardContent>
            </Card>

            <button
                v-for="key in statusKeys"
                :key="key"
                type="button"
                :aria-pressed="activeStatus === key"
                class="group relative overflow-hidden rounded-xl border bg-card text-left shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="activeStatus === key ? 'ring-2' : ''"
                :style="
                    activeStatus === key
                        ? { '--tw-ring-color': statusMeta[key].color }
                        : {}
                "
                @click="toggleStatus(key)"
            >
                <div class="p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm text-muted-foreground">
                                {{ statusMeta[key].label }}
                            </p>
                            <p
                                class="mt-2 text-3xl font-semibold tracking-tight tabular-nums"
                            >
                                {{ props.ticketStats[key] }}
                            </p>
                        </div>
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg transition-transform duration-200 group-hover:scale-110"
                            :class="statusMeta[key].tint"
                        >
                            <component
                                :is="statusMeta[key].icon"
                                class="h-5 w-5"
                            />
                        </div>
                    </div>

                    <p class="mt-3 text-xs text-muted-foreground">
                        {{ statusMeta[key].hint }} ·
                        {{ percent(props.ticketStats[key]) }}%
                    </p>
                </div>

                <!-- Bar porsi dari total -->
                <div class="h-1 w-full bg-muted">
                    <div
                        class="h-full transition-all duration-700 ease-out"
                        :style="{
                            width: `${percent(props.ticketStats[key])}%`,
                            backgroundColor: statusMeta[key].color,
                        }"
                    />
                </div>
            </button>
        </div>

        <!-- Chart status + prioritas -->
        <div class="grid gap-6 lg:grid-cols-5">
            <!-- Status -->
            <Card class="lg:col-span-3">
                <CardHeader
                    class="flex flex-row items-start justify-between gap-4 space-y-0"
                >
                    <div>
                        <CardTitle class="text-base"
                            >Ticket berdasarkan Status</CardTitle
                        >
                        <CardDescription
                            >Distribusi ticket saat ini</CardDescription
                        >
                    </div>

                    <div v-if="activeStatus" class="flex items-center gap-2">
                        <Badge variant="secondary" class="gap-1.5">
                            <span
                                class="h-2 w-2 rounded-full"
                                :style="{
                                    backgroundColor:
                                        statusMeta[activeStatus].color,
                                }"
                            />
                            {{ statusMeta[activeStatus].label }}
                        </Badge>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-7 w-7"
                            aria-label="Hapus sorotan"
                            @click="activeStatus = null"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
                </CardHeader>

                <CardContent>
                    <div class="h-[280px] w-full">
                        <VisXYContainer :data="chartData">
                            <VisGroupedBar
                                :x="(d: StatusDatum) => d.index"
                                :y="[(d: StatusDatum) => d.total]"
                                :color="statusBarColor"
                                :rounded-corners="6"
                                bar-padding="0.3"
                                group-padding="0.1"
                            />
                            <VisAxis
                                type="x"
                                :tick-values="chartData.map((d) => d.index)"
                                :tick-format="
                                    (v: number) =>
                                        chartData.find((d) => d.index === v)
                                            ?.status ?? ''
                                "
                                :tick-line="false"
                                :domain-line="false"
                                :grid-line="false"
                            />
                            <VisAxis
                                type="y"
                                :tick-line="false"
                                :domain-line="false"
                                :grid-line="true"
                            />
                            <VisTooltip :triggers="statusTooltip" />
                        </VisXYContainer>
                    </div>

                    <div v-if="activeStatus" class="mt-4 flex justify-end">
                        <Button
                            as-child
                            variant="outline"
                            size="sm"
                            class="gap-2"
                        >
                            <Link :href="`/tickets?status=${activeStatus}`">
                                Lihat ticket
                                {{ statusMeta[activeStatus].label }}
                                <ArrowRight class="h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Prioritas -->
            <!-- Prioritas -->
            <!-- Prioritas -->
            <Card class="min-w-0 lg:col-span-2">
                <CardHeader class="pb-2">
                    <CardTitle class="text-base"> Priority Ticket </CardTitle>

                    <CardDescription>
                        Distribusi berdasarkan prioritas
                    </CardDescription>
                </CardHeader>

                <CardContent class="pt-3">
                    <div
                        class="grid min-w-0 items-center gap-4 sm:grid-cols-[190px_minmax(0,1fr)]"
                    >
                        <!-- DONUT -->
                        <!-- Donut -->
                        <div class="relative mx-auto h-[220px] w-[220px]">
                            <VisSingleContainer
                                :data="priorities"
                                class="h-full w-full"
                            >
                                <VisDonut
                                    :value="(d: PriorityDatum) => d.total"
                                    :color="priorityColor"
                                    :arc-width="24"
                                    :pad-angle="0.02"
                                    :corner-radius="4"
                                />

                                <VisTooltip :triggers="priorityTooltip" />
                            </VisSingleContainer>

                            <!-- Total di tengah donut -->
                            <div
                                class="pointer-events-none absolute top-1/2 left-1/2 z-10 flex -translate-x-1/2 -translate-y-1/2 flex-col items-center justify-center text-center"
                            >
                                <span
                                    class="text-3xl leading-none font-semibold tabular-nums"
                                >
                                    {{ priorityTotal }}
                                </span>

                                <span
                                    class="mt-1 text-xs leading-none text-muted-foreground"
                                >
                                    ticket
                                </span>
                            </div>
                        </div>

                        <!-- LEGEND -->
                        <div class="min-w-0 pl-4">
                            <div class="space-y-1">
                                <Link
                                    v-for="p in priorities"
                                    :key="p.key"
                                    :href="`/tickets?priority=${p.key}`"
                                    class="group grid grid-cols-[minmax(0,1fr)_32px_42px] items-center gap-2 rounded-lg px-2.5 py-2 transition-colors hover:bg-muted/60"
                                    @mouseenter="hoveredPriority = p.key"
                                    @mouseleave="hoveredPriority = null"
                                >
                                    <!-- Nama -->
                                    <div
                                        class="flex min-w-0 items-center gap-2.5"
                                    >
                                        <span
                                            class="h-2.5 w-2.5 shrink-0 rounded-full transition-transform group-hover:scale-110"
                                            :style="{
                                                backgroundColor: p.color,
                                            }"
                                        ></span>

                                        <span class="truncate text-sm">
                                            {{ p.label }}
                                        </span>
                                    </div>

                                    <!-- Total -->
                                    <span
                                        class="text-right text-sm font-semibold tabular-nums"
                                    >
                                        {{ p.total }}
                                    </span>

                                    <!-- Persentase -->
                                    <span
                                        class="text-right text-xs text-muted-foreground tabular-nums"
                                    >
                                        {{
                                            priorityTotal
                                                ? Math.round(
                                                      (p.total /
                                                          priorityTotal) *
                                                          100,
                                                  )
                                                : 0
                                        }}%
                                    </span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Rekapan bulanan -->
        <Card>
            <CardHeader
                class="flex flex-col gap-4 space-y-0 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <CardTitle class="text-lg"
                        >Rekapan Ticket Bulanan</CardTitle
                    >
                    <CardDescription>
                        Jumlah ticket yang dilaporkan per bulan.
                    </CardDescription>
                </div>

                <Tabs v-model="period">
                    <TabsList>
                        <TabsTrigger value="3">3 bulan</TabsTrigger>
                        <TabsTrigger value="6">6 bulan</TabsTrigger>
                        <TabsTrigger value="12">12 bulan</TabsTrigger>
                    </TabsList>
                </Tabs>
            </CardHeader>

            <CardContent class="space-y-5">
                <div class="flex flex-wrap items-center gap-x-8 gap-y-3">
                    <div>
                        <p class="text-xs text-muted-foreground">
                            Total Ticket
                        </p>
                        <p class="text-2xl font-semibold tabular-nums">
                            {{ monthlySum }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">
                            Rata-rata per bulan
                        </p>
                        <p class="text-2xl font-semibold tabular-nums">
                            {{ monthlyAvg }}
                        </p>
                    </div>
                    <Badge
                        v-if="monthlyDelta !== null"
                        variant="secondary"
                        class="gap-1.5"
                        :class="
                            monthlyDelta >= 0
                                ? 'text-amber-600'
                                : 'text-emerald-600'
                        "
                    >
                        <component
                            :is="monthlyDelta >= 0 ? TrendingUp : TrendingDown"
                            class="h-3.5 w-3.5"
                        />
                        {{ monthlyDelta >= 0 ? '+' : '' }}{{ monthlyDelta }}%
                        dari bulan lalu
                    </Badge>
                </div>

                <div class="h-[280px] w-full">
                    <VisXYContainer :data="monthly">
                        <VisArea
                            :x="(d: (typeof monthly)[number]) => d.index"
                            :y="(d: (typeof monthly)[number]) => d.total"
                            color="#3b82f6"
                            :opacity="0.12"
                        />
                        <VisLine
                            :x="(d: (typeof monthly)[number]) => d.index"
                            :y="(d: (typeof monthly)[number]) => d.total"
                            color="#3b82f6"
                            :line-width="3"
                        />
                        <VisCrosshair
                            color="#3b82f6"
                            :x="(d: (typeof monthly)[number]) => d.index"
                            :y="(d: (typeof monthly)[number]) => d.total"
                            :template="crosshairTemplate"
                        />
                        <VisAxis
                            type="x"
                            :tick-values="monthly.map((d) => d.index)"
                            :tick-format="monthTick"
                            :tick-line="false"
                            :domain-line="false"
                            :grid-line="false"
                        />
                        <VisAxis
                            type="y"
                            :tick-line="false"
                            :domain-line="false"
                            :grid-line="true"
                        />
                        <VisTooltip />
                    </VisXYContainer>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
