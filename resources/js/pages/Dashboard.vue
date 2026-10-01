<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import { dashboard } from '@/routes';

import {
    VisAxis,
    VisDonut,
    VisGroupedBar,
    VisLine,
    VisSingleContainer,
    VisXYContainer,
    VisTooltip,
} from '@unovis/vue';
import {
    Eye,
    Pencil,
    Trash2,
    Activity,
    CheckCircle2,
    Lock,
    AlertTriangle,
    TicketPlus,
    Search,
    RotateCcw,
    BarChart3,
} from 'lucide-vue-next';
import type { ChartConfig } from '@/components/ui/chart';

import { ChartContainer } from '@/components/ui/chart';
import { GroupedBar, Donut } from '@unovis/ts';
defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

interface StatusChartItem {
    status: string;
    total: number;
}
interface MonthlyTicketChartItem {
    month: string;
    total: number;
}
interface ChartDataItem {
    index: number;
    status: string;
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

const props = defineProps<{
    ticketStats: TicketStats;
    priorityStats: PriorityStats;
    statusChart: StatusChartItem[];
    monthlyTicketChart: MonthlyTicketChartItem[];
}>();
const priorityChartData = [
    {
        priority: 'Critical',
        total: props.priorityStats.critical,
    },
    {
        priority: 'High',
        total: props.priorityStats.high,
    },
    {
        priority: 'Medium',
        total: props.priorityStats.medium,
    },
    {
        priority: 'Low',
        total: props.priorityStats.low,
    },
];
const statusTooltipTriggers = {
    [GroupedBar.selectors.bar]: (d: ChartDataItem) => `
        <div>
            <div style="font-weight: 600;">
                ${d.status}
            </div>
            <div style="margin-top: 4px;">
                Total: <strong>${d.total}</strong> ticket
            </div>
        </div>
    `,
};
const priorityTooltipTriggers = {
    [Donut.selectors.segment]: (d: { priority: string; total: number }) => `
        <div>
            <div style="font-weight: 600;">
                ${d.priority}
            </div>

            <div style="margin-top: 4px;">
                Total: <strong>${d.total}</strong> ticket
            </div>
        </div>
    `,
};
const getPriorityValue = (d: { total: number }) => d.total;

const getPriorityColor = (_d: { total: number }, index: number) => {
    const colors = ['#ef4444', '#f97316', '#eab308', '#9ca3af'];

    return colors[index] ?? '#9ca3af';
};
const chartData: ChartDataItem[] = props.statusChart.map((item, index) => ({
    index,
    status: item.status,
    total: item.total,
}));
const monthlyChartData = props.monthlyTicketChart.map((item, index) => ({
    index,
    month: item.month,
    total: item.total,
}));
const chartConfig = {
    total: {
        label: 'Total Ticket',
        color: 'var(--chart-1)',
    },
} satisfies ChartConfig;
</script>

<template>
    <Head title="Dashboard" />

    <div class="min-h-full space-y-6 p-4 sm:p-6">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>

            <p class="mt-1 text-sm text-muted-foreground">
                Ringkasan kondisi ticket saat ini.
            </p>
        </div>

        <!-- Statistik Ticket -->
        <!-- Statistik Ticket -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <!-- Total -->
            <div
                class="rounded-xl border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Total Ticket
                        </p>

                        <p class="mt-2 text-3xl font-semibold tracking-tight">
                            {{ props.ticketStats.total }}
                        </p>
                    </div>

                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <TicketPlus class="h-5 w-5" />
                    </div>
                </div>

                <p class="mt-3 text-xs text-muted-foreground">Seluruh ticket</p>
            </div>

            <!-- Open -->
            <div
                class="rounded-xl border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-muted-foreground">Open</p>

                        <p class="mt-2 text-3xl font-semibold tracking-tight">
                            {{ props.ticketStats.open }}
                        </p>
                    </div>

                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500/10 text-blue-500"
                    >
                        <Activity class="h-5 w-5" />
                    </div>
                </div>

                <p class="mt-3 text-xs text-muted-foreground">
                    Menunggu ditangani
                </p>
            </div>

            <!-- On Progress -->
            <div
                class="rounded-xl border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-muted-foreground">On Progress</p>

                        <p class="mt-2 text-3xl font-semibold tracking-tight">
                            {{ props.ticketStats.on_progress }}
                        </p>
                    </div>

                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-yellow-500/10 text-yellow-500"
                    >
                        <Activity class="h-5 w-5" />
                    </div>
                </div>

                <p class="mt-3 text-xs text-muted-foreground">
                    Sedang diproses
                </p>
            </div>

            <!-- Resolved -->
            <div
                class="rounded-xl border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-muted-foreground">Resolved</p>

                        <p class="mt-2 text-3xl font-semibold tracking-tight">
                            {{ props.ticketStats.resolved }}
                        </p>
                    </div>

                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-500/10 text-green-500"
                    >
                        <CheckCircle2 class="h-5 w-5" />
                    </div>
                </div>

                <p class="mt-3 text-xs text-muted-foreground">
                    Selesai ditangani
                </p>
            </div>

            <!-- Closed -->
            <div
                class="rounded-xl border bg-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-muted-foreground">Closed</p>

                        <p class="mt-2 text-3xl font-semibold tracking-tight">
                            {{ props.ticketStats.closed }}
                        </p>
                    </div>

                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-500/10 text-gray-400"
                    >
                        <Lock class="h-5 w-5" />
                    </div>
                </div>

                <p class="mt-3 text-xs text-muted-foreground">Ticket ditutup</p>
            </div>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Status Chart -->
            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-500/10 text-blue-500"
                            >
                                <BarChart3 class="h-4 w-4" />
                            </div>

                            <div>
                                <h2 class="text-base font-semibold">
                                    Ticket berdasarkan Status
                                </h2>

                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    Distribusi ticket saat ini
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <ChartContainer :config="chartConfig" class="h-[280px] w-full">
                    <VisXYContainer :data="chartData">
                        <VisGroupedBar
                            :x="(d: ChartDataItem) => d.index"
                            :y="[(d: ChartDataItem) => d.total]"
                            color="#3b82f6"
                            :rounded-corners="4"
                            bar-padding="0.25"
                            group-padding="0.1"
                        />

                        <VisAxis
                            type="x"
                            :x="(d: ChartDataItem) => d.index"
                            :tick-values="chartData.map((d) => d.index)"
                            :tick-format="
                                (value: number) => {
                                    const item = chartData.find(
                                        (d) => d.index === value,
                                    );

                                    return item?.status ?? '';
                                }
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
                        <VisTooltip :triggers="statusTooltipTriggers" />
                    </VisXYContainer>
                </ChartContainer>
            </div>
            <!-- Priority Chart -->
            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-lg bg-orange-500/10 text-orange-500"
                            >
                                <BarChart3 class="h-4 w-4" />
                            </div>

                            <div>
                                <h2 class="text-base font-semibold">
                                    Priority Ticket
                                </h2>

                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    Distribusi berdasarkan prioritas
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <ChartContainer :config="chartConfig" class="h-[280px] w-full">
                    <VisSingleContainer :data="priorityChartData">
                        <VisDonut
                            :value="getPriorityValue"
                            :color="getPriorityColor"
                            :arc-width="0"
                        />

                        <VisTooltip :triggers="priorityTooltipTriggers" />
                    </VisSingleContainer>
                </ChartContainer>
            </div>
        </div>
        <!-- Monthly Ticket Chart -->
        <div class="rounded-xl border bg-card p-6 shadow-sm">
            <div class="mb-5">
                <h2 class="text-lg font-semibold">Rekapan Ticket Bulanan</h2>

                <p class="text-sm text-muted-foreground">
                    Jumlah ticket yang dilaporkan dalam 12 bulan terakhir.
                </p>
            </div>

            <ChartContainer :config="chartConfig" class="h-[280px] w-full">
                <VisXYContainer :data="monthlyChartData">
                    <VisLine
                        :x="(d: (typeof monthlyChartData)[number]) => d.index"
                        :y="[(d: (typeof monthlyChartData)[number]) => d.total]"
                        color="#3b82f6"
                        :line-width="3"
                    />

                    <VisAxis
                        type="x"
                        :x="(d: (typeof monthlyChartData)[number]) => d.index"
                        :tick-values="monthlyChartData.map((d) => d.index)"
                        :tick-format="
                            (value: number) => {
                                const item = monthlyChartData.find(
                                    (d) => d.index === value,
                                );

                                return item?.month ?? '';
                            }
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
                    <VisTooltip :triggers="statusTooltipTriggers" />
                </VisXYContainer>
            </ChartContainer>
        </div>
        <!-- Monitoring -->
        <div
            class="flex flex-col gap-4 rounded-xl border bg-card p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h2 class="text-lg font-semibold">Live Ticket Monitoring</h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Pantau seluruh ticket yang sedang berjalan dalam tampilan
                    khusus monitoring.
                </p>
            </div>

            <Link
                href="/tickets/monitoring"
                class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
            >
                Buka Monitoring
            </Link>
        </div>
    </div>
</template>
