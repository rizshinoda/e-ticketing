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
} from '@unovis/vue';

import type { ChartConfig } from '@/components/ui/chart';

import { ChartContainer } from '@/components/ui/chart';

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
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <!-- Total -->
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <p class="text-sm text-muted-foreground">Total Ticket</p>

                <p class="mt-2 text-3xl font-semibold">
                    {{ props.ticketStats.total }}
                </p>
            </div>

            <!-- Open -->
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <p class="text-sm text-muted-foreground">Open</p>

                <p class="mt-2 text-3xl font-semibold">
                    {{ props.ticketStats.open }}
                </p>
            </div>

            <!-- On Progress -->
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <p class="text-sm text-muted-foreground">On Progress</p>

                <p class="mt-2 text-3xl font-semibold">
                    {{ props.ticketStats.on_progress }}
                </p>
            </div>

            <!-- Resolved -->
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <p class="text-sm text-muted-foreground">Resolved</p>

                <p class="mt-2 text-3xl font-semibold">
                    {{ props.ticketStats.resolved }}
                </p>
            </div>

            <!-- Closed -->
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <p class="text-sm text-muted-foreground">Closed</p>

                <p class="mt-2 text-3xl font-semibold">
                    {{ props.ticketStats.closed }}
                </p>
            </div>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Status Chart -->
            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold">
                        Ticket berdasarkan Status
                    </h2>

                    <p class="text-sm text-muted-foreground">
                        Distribusi ticket berdasarkan status saat ini.
                    </p>
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
                    </VisXYContainer>
                </ChartContainer>
            </div>
            <!-- Priority Chart -->
            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold">Priority Ticket</h2>

                    <p class="text-sm text-muted-foreground">
                        Distribusi ticket berdasarkan tingkat prioritas.
                    </p>
                </div>

                <ChartContainer :config="chartConfig" class="h-[280px] w-full">
                    <VisSingleContainer :data="priorityChartData">
                        <VisDonut
                            :value="(d) => d.total"
                            :color="
                                (_d, index) => {
                                    const colors = [
                                        '#ef4444',
                                        '#f97316',
                                        '#eab308',
                                        '#9ca3af',
                                    ];

                                    return colors[index];
                                }
                            "
                            :arc-width="0"
                        />
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
