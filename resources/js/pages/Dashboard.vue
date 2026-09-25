<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import { dashboard } from '@/routes';

import { VisAxis, VisGroupedBar, VisXYContainer } from '@unovis/vue';

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

const testStatusChart: StatusChartItem[] = [
    {
        index: 0,
        status: 'Open',
        total: 5,
    },
    {
        index: 1,
        status: 'On Progress',
        total: 8,
    },
    {
        index: 2,
        status: 'Resolved',
        total: 3,
    },
    {
        index: 3,
        status: 'Closed',
        total: 12,
    },
];

const props = defineProps<{
    ticketStats: TicketStats;
    priorityStats: PriorityStats;
    statusChart: StatusChartItem[];
}>();

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

        <!-- Priority -->
        <div class="rounded-xl border bg-card p-6 shadow-sm">
            <div class="mb-5">
                <h2 class="text-lg font-semibold">Priority Ticket</h2>

                <p class="text-sm text-muted-foreground">
                    Jumlah ticket berdasarkan tingkat prioritas.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Critical -->
                <div class="rounded-lg border p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span
                                class="h-3 w-3 rounded-full bg-red-500"
                            ></span>

                            <span class="text-sm font-medium"> Critical </span>
                        </div>

                        <span class="text-2xl font-semibold">
                            {{ props.priorityStats.critical }}
                        </span>
                    </div>
                </div>

                <!-- High -->
                <div class="rounded-lg border p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span
                                class="h-3 w-3 rounded-full bg-orange-500"
                            ></span>

                            <span class="text-sm font-medium"> High </span>
                        </div>

                        <span class="text-2xl font-semibold">
                            {{ props.priorityStats.high }}
                        </span>
                    </div>
                </div>

                <!-- Medium -->
                <div class="rounded-lg border p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span
                                class="h-3 w-3 rounded-full bg-yellow-500"
                            ></span>

                            <span class="text-sm font-medium"> Medium </span>
                        </div>

                        <span class="text-2xl font-semibold">
                            {{ props.priorityStats.medium }}
                        </span>
                    </div>
                </div>

                <!-- Low -->
                <div class="rounded-lg border p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span
                                class="h-3 w-3 rounded-full bg-gray-400"
                            ></span>

                            <span class="text-sm font-medium"> Low </span>
                        </div>

                        <span class="text-2xl font-semibold">
                            {{ props.priorityStats.low }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Status Chart -->
        <div class="rounded-xl border bg-card p-6 shadow-sm">
            <div class="mb-5">
                <h2 class="text-lg font-semibold">Ticket berdasarkan Status</h2>

                <p class="text-sm text-muted-foreground">
                    Distribusi ticket berdasarkan status saat ini.
                </p>
            </div>

            <ChartContainer :config="chartConfig" class="h-[280px] w-full">
                <VisXYContainer :data="testStatusChart">
                    <VisGroupedBar
                        :x="(d: StatusChartItem) => d.index"
                        :y="[(d: StatusChartItem) => d.total]"
                        color="#3b82f6"
                        :rounded-corners="4"
                        bar-padding="0.1"
                        group-padding="0"
                    />

                    <VisAxis
                        type="x"
                        :x="(d: StatusChartItem) => d.index"
                        :tick-values="testStatusChart.map((d) => d.index)"
                        :tick-format="
                            (value: number) => {
                                const item = testStatusChart.find(
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
