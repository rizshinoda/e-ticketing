<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import { onUnmounted, ref } from 'vue';

interface Ticket {
    id: number;
    ticket_number: string;
    ticket_type: 'individual' | 'gamas';
    priority: 'low' | 'medium' | 'high' | 'critical';
    current_priority: 'low' | 'medium' | 'high' | 'critical';
    status: 'open' | 'on_progress' | 'resolved' | 'closed';
    description: string | null;
    reported_at: string;

    sla_timer_seconds: number;
    sla_timer_running: boolean;
    sla_timer_status: 'running' | 'stop_clock' | 'resolved' | 'not_applicable';
    ticket_duration_seconds: number;

    customers: {
        id: number;
        customer_name: string;
        site_name: string;
        no_jaringan: string | null;
    }[];

    creator: {
        id: number;
        name: string;
    } | null;

    latest_update: {
        id: number;
        user: {
            id: number;
            name: string;
        } | null;
    } | null;
}

interface TicketCreatedEvent {
    ticketId: number;
    ticketNumber: string;
    ticketType: 'individual' | 'gamas';
    priority: 'low' | 'medium' | 'high' | 'critical';
    currentPriority: 'low' | 'medium' | 'high' | 'critical';
    status: 'open' | 'on_progress' | 'resolved' | 'closed';

    reportedAt: string;

    slaTimerSeconds: number;
    slaTimerRunning: boolean;
    slaTimerStatus: 'running' | 'stop_clock' | 'not_applicable';

    ticketDurationSeconds: number;

    creator: {
        id: number;
        name: string;
    } | null;

    latestUpdate: {
        id: number;
        user: {
            id: number;
            name: string;
        } | null;
    } | null;

    customers: {
        id: number;
        customer_name: string;
        site_name: string;
        no_jaringan: string | null;
    }[];
}
interface TicketUpdatedEvent {
    ticketId: number;

    status: 'open' | 'on_progress' | 'resolved' | 'closed';

    currentPriority: 'low' | 'medium' | 'high' | 'critical';

    latestUpdate: {
        id: number;
        user: {
            id: number;
            name: string;
        } | null;
    } | null;
}
interface StopClockEvent {
    ticketId: number;
    slaTimerSeconds: number;
    slaTimerRunning: boolean;
}
interface TicketResolvedEvent {
    ticketId: number;
    status: 'open' | 'on_progress' | 'resolved' | 'closed';
    currentPriority: 'low' | 'medium' | 'high' | 'critical';
    resolvedAt: string | null;
    slaTimerSeconds: number;
    slaTimerStatus: 'resolved';
}
interface TicketClosedEvent {
    ticketId: number;
    status: 'closed';
    closedAt: string | null;
}
interface TicketReopenedEvent {
    ticketId: number;

    ticketNumber: string;

    ticketType: 'individual' | 'gamas';

    priority: 'low' | 'medium' | 'high' | 'critical';

    currentPriority: 'low' | 'medium' | 'high' | 'critical';

    status: 'open' | 'on_progress' | 'resolved' | 'closed';

    reportedAt: string;

    slaTimerSeconds: number;

    slaTimerRunning: boolean;

    slaTimerStatus: 'running' | 'stop_clock' | 'resolved' | 'not_applicable';

    ticketDurationSeconds: number;

    creator: {
        id: number;
        name: string;
    } | null;

    latestUpdate: {
        id: number;

        user: {
            id: number;
            name: string;
        } | null;
    } | null;

    customers: {
        id: number;
        customer_name: string;
        site_name: string;
        no_jaringan: string | null;
    }[];
}
const props = defineProps<{
    tickets: Ticket[];
}>();

/**
 * Data lokal untuk monitoring realtime.
 *
 * props.tickets:
 * Data awal yang dikirim Laravel ketika halaman dibuka.
 *
 * tickets:
 * Data lokal yang akan berubah ketika menerima event Reverb.
 */
const tickets = ref<Ticket[]>([...props.tickets]);

/**
 * Format tanggal ke:
 *
 * 08 Okt 2026 • 10:44 WIB
 */
const formatDateTime = (date: string) => {
    const formatted = new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone: 'Asia/Jakarta',
    }).formatToParts(new Date(date));

    const parts = Object.fromEntries(
        formatted.map((part) => [part.type, part.value]),
    );

    return `${parts.day} ${parts.month} ${parts.year} • ${parts.hour}:${parts.minute} WIB`;
};

/**
 * Format SLA:
 *
 * 00:00:00
 * 01:25:32
 * 12:45:10
 */
const formatSlaTimer = (seconds: number) => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;

    return [hours, minutes, remainingSeconds]
        .map((value) => String(value).padStart(2, '0'))
        .join(':');
};

/**
 * Format durasi ticket:
 *
 * 00j 25m
 * 03j 15m
 * 1h 04j 27m
 */
const formatTicketDuration = (seconds: number) => {
    const totalSeconds = Math.floor(seconds);

    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const remainingSeconds = totalSeconds % 60;

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
};

/**
 * Update seluruh timer setiap detik.
 *
 * Ticket Duration:
 * - terus berjalan sampai CLOSED
 *
 * SLA Timer:
 * - hanya berjalan jika status SLA = running
 */

const calculateCurrentPriority = (ticket: Ticket) => {
    if (ticket.status === 'closed') {
        return ticket.current_priority;
    }

    const escalationCount = Math.floor(
        ticket.ticket_duration_seconds / (4 * 60 * 60),
    );

    const levels = {
        low: 0,
        medium: 1,
        high: 2,
        critical: 3,
    };

    const priorities = ['low', 'medium', 'high', 'critical'] as const;

    const currentLevel = levels[ticket.priority];

    const newLevel = Math.min(currentLevel + escalationCount, 3);

    return priorities[newLevel];
};
const updateTimers = () => {
    tickets.value.forEach((ticket) => {
        if (ticket.status !== 'closed') {
            ticket.ticket_duration_seconds++;
        }

        if (ticket.sla_timer_status === 'running') {
            ticket.sla_timer_seconds++;
        }

        ticket.current_priority = calculateCurrentPriority(ticket);
    });
};

const timerInterval = setInterval(() => {
    updateTimers();
}, 1000);

onUnmounted(() => {
    clearInterval(timerInterval);
});

/**
 * =========================================================
 * TICKET CREATED
 * =========================================================
 *
 * Event ketika ticket baru dibuat.
 */
useEchoPublic(
    'ticket-monitor',
    'TicketCreated',
    (event: TicketCreatedEvent) => {
        console.log('🎫 MONITORING - TICKET CREATED:', event);

        /**
         * Jangan masukkan ticket jika sudah ada.
         *
         * Mencegah duplicate row jika event diterima lebih dari sekali.
         */
        const alreadyExists = tickets.value.some(
            (ticket) => ticket.id === event.ticketId,
        );

        if (alreadyExists) {
            return;
        }

        const newTicket: Ticket = {
            id: event.ticketId,
            ticket_number: event.ticketNumber,
            ticket_type: event.ticketType,
            priority: event.priority,
            current_priority: event.currentPriority,
            status: event.status,
            description: null,
            reported_at: event.reportedAt,

            customers: event.customers,

            sla_timer_seconds: event.slaTimerSeconds,
            sla_timer_running: event.slaTimerRunning,
            sla_timer_status: event.slaTimerStatus,

            ticket_duration_seconds: event.ticketDurationSeconds,

            creator: event.creator,

            latest_update: event.latestUpdate,
        };

        /**
         * Ticket baru ditaruh paling atas.
         */
        tickets.value.unshift(newTicket);
    },
);

/**
 * =========================================================
 * STOP CLOCK STARTED
 * =========================================================
 *
 * Ketika Stop Clock dimulai:
 *
 * SLA:
 * - berhenti
 *
 * Ticket Duration:
 * - tetap berjalan
 */
useEchoPublic(
    'ticket-monitor',
    'TicketStopClockStarted',
    (event: StopClockEvent) => {
        console.log('⏸ MONITORING - STOP CLOCK STARTED:', event);

        const ticket = tickets.value.find(
            (ticket) => ticket.id === event.ticketId,
        );

        if (!ticket) {
            return;
        }

        ticket.sla_timer_seconds = event.slaTimerSeconds;

        ticket.sla_timer_running = false;

        ticket.sla_timer_status = 'stop_clock';
    },
);

/**
 * =========================================================
 * STOP CLOCK ENDED / RESUME
 * =========================================================
 *
 * Ketika Stop Clock selesai:
 *
 * SLA:
 * - berjalan kembali
 *
 * Ticket Duration:
 * - tetap berjalan
 */
useEchoPublic(
    'ticket-monitor',
    'TicketStopClockEnded',
    (event: StopClockEvent) => {
        console.log('▶️ MONITORING - STOP CLOCK ENDED:', event);

        const ticket = tickets.value.find(
            (ticket) => ticket.id === event.ticketId,
        );

        if (!ticket) {
            return;
        }

        ticket.sla_timer_seconds = event.slaTimerSeconds;

        ticket.sla_timer_running = true;

        ticket.sla_timer_status = 'running';
    },
);

useEchoPublic(
    'ticket-monitor',
    'TicketUpdated',
    (event: TicketUpdatedEvent) => {
        console.log('📝 MONITORING - TICKET UPDATED:', event);

        const ticket = tickets.value.find(
            (ticket) => ticket.id === event.ticketId,
        );

        if (!ticket) {
            return;
        }

        /*
         * Update status ticket.
         */
        ticket.status = event.status;

        /*
         * Update current priority.
         */
        ticket.current_priority = event.currentPriority;

        /*
         * Update user yang terakhir melakukan update.
         */
        ticket.latest_update = event.latestUpdate;
    },
);
useEchoPublic<TicketResolvedEvent>(
    'ticket-monitor',
    'TicketResolved',
    (event) => {
        console.log('✅ MONITORING - TICKET RESOLVED:', event);

        const ticket = tickets.value.find(
            (ticket) => ticket.id === event.ticketId,
        );

        if (!ticket) {
            return;
        }

        // Update status
        ticket.status = event.status;

        // Update current priority
        ticket.current_priority = event.currentPriority;

        // Update SLA timer
        ticket.sla_timer_seconds = event.slaTimerSeconds;

        // SLA berhenti setelah resolve
        ticket.sla_timer_running = false;
        ticket.sla_timer_status = event.slaTimerStatus;
    },
);
useEchoPublic<TicketClosedEvent>('ticket-monitor', 'TicketClosed', (event) => {
    console.log('🔒 MONITORING - TICKET CLOSED:', event);

    tickets.value = tickets.value.filter(
        (ticket) => ticket.id !== event.ticketId,
    );
});
useEchoPublic<TicketReopenedEvent>(
    'ticket-monitor',
    'TicketReopened',
    (event) => {
        console.log('🔓 MONITORING - TICKET REOPENED:', event);

        const ticket = tickets.value.find(
            (ticket) => ticket.id === event.ticketId,
        );

        if (ticket) {
            ticket.status = event.status;
            ticket.current_priority = event.currentPriority;

            ticket.sla_timer_seconds = event.slaTimerSeconds;

            ticket.sla_timer_running = event.slaTimerRunning;

            ticket.sla_timer_status = event.slaTimerStatus;

            ticket.latest_update = event.latestUpdate;

            ticket.ticket_duration_seconds = event.ticketDurationSeconds;

            return;
        }

        // Ticket sebelumnya sudah dihapus
        // dari Monitoring ketika Closed.
        const reopenedTicket: Ticket = {
            id: event.ticketId,
            ticket_number: event.ticketNumber,
            ticket_type: event.ticketType,

            priority: event.priority,
            current_priority: event.currentPriority,

            status: event.status,

            description: null,
            reported_at: event.reportedAt,

            customers: event.customers,

            sla_timer_seconds: event.slaTimerSeconds,

            sla_timer_running: event.slaTimerRunning,

            sla_timer_status: event.slaTimerStatus,

            ticket_duration_seconds: event.ticketDurationSeconds,

            creator: event.creator,

            latest_update: event.latestUpdate,
        };

        tickets.value.unshift(reopenedTicket);
    },
);
</script>

<template>
    <Head title="Ticket Monitoring" />

    <div class="space-y-6 p-6">
        <!-- ================================================= -->
        <!-- HEADER -->
        <!-- ================================================= -->

        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Ticket Monitoring
                    </h1>

                    <!-- Live indicator -->
                    <div
                        class="flex items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-3 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                    >
                        <span class="relative flex h-2 w-2">
                            <span
                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-75"
                            ></span>

                            <span
                                class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"
                            ></span>
                        </span>

                        LIVE
                    </div>
                </div>

                <p class="mt-1 text-sm text-muted-foreground">
                    Monitoring ticket dan SLA secara realtime.
                </p>
            </div>

            <!-- Total ticket -->
            <div class="rounded-lg border bg-card px-4 py-3">
                <div class="text-xs text-muted-foreground">
                    Ticket Dimonitor
                </div>

                <div class="mt-1 text-xl font-semibold">
                    {{ tickets.length }}
                </div>
            </div>
        </div>

        <!-- ================================================= -->
        <!-- MONITORING TABLE -->
        <!-- ================================================= -->

        <div class="overflow-hidden rounded-xl border bg-card">
            <!-- Table header -->
            <div class="border-b bg-muted/20 px-5 py-4">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="font-medium">Active Ticket</h2>

                        <p class="mt-1 text-xs text-muted-foreground">
                            Ticket open, on progress, dan resolved.
                        </p>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <!-- ================================================= -->
                    <!-- TABLE HEAD -->
                    <!-- ================================================= -->

                    <thead>
                        <tr class="border-b bg-muted/30">
                            <th
                                class="px-5 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Ticket
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Customer / Site
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Priority
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Status
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Durasi Ticket
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Dibuat Oleh
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Handle Saat Ini
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Dilaporkan
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                SLA Timer
                            </th>
                        </tr>
                    </thead>

                    <!-- ================================================= -->
                    <!-- TABLE BODY -->
                    <!-- ================================================= -->

                    <tbody class="divide-y">
                        <!-- Data ticket -->
                        <tr
                            v-for="ticket in tickets"
                            :key="ticket.id"
                            class="transition-colors hover:bg-muted/30"
                        >
                            <!-- ========================================= -->
                            <!-- TICKET -->
                            <!-- ========================================= -->

                            <td class="px-5 py-4 align-top">
                                <div class="font-semibold">
                                    {{ ticket.ticket_number }}
                                </div>

                                <div
                                    class="mt-1 text-xs text-muted-foreground capitalize"
                                >
                                    {{ ticket.ticket_type }}
                                </div>
                            </td>

                            <!-- ========================================= -->
                            <!-- CUSTOMER / SITE -->
                            <!-- ========================================= -->

                            <td class="px-5 py-4 align-top">
                                <div class="font-medium">
                                    {{
                                        ticket.customers[0]?.customer_name ??
                                        '-'
                                    }}
                                </div>

                                <div class="mt-1 text-xs text-muted-foreground">
                                    {{ ticket.customers[0]?.site_name ?? '-' }}
                                </div>

                                <div
                                    v-if="ticket.customers[0]?.no_jaringan"
                                    class="mt-1 font-mono text-xs text-muted-foreground"
                                >
                                    {{ ticket.customers[0].no_jaringan }}
                                </div>

                                <div
                                    v-if="ticket.customers.length > 1"
                                    class="mt-1 text-xs font-medium text-primary"
                                >
                                    +{{ ticket.customers.length - 1 }} site
                                </div>
                            </td>

                            <!-- ========================================= -->
                            <!-- PRIORITY -->
                            <!-- ========================================= -->

                            <td class="px-5 py-4 align-top">
                                <span
                                    class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                                    :class="{
                                        'border-slate-500/20 bg-slate-500/10 text-slate-600 dark:text-slate-400':
                                            ticket.current_priority === 'low',

                                        'border-blue-500/20 bg-blue-500/10 text-blue-600 dark:text-blue-400':
                                            ticket.current_priority ===
                                            'medium',

                                        'border-orange-500/20 bg-orange-500/10 text-orange-600 dark:text-orange-400':
                                            ticket.current_priority === 'high',

                                        'border-red-500/20 bg-red-500/10 text-red-600 dark:text-red-400':
                                            ticket.current_priority ===
                                            'critical',
                                    }"
                                >
                                    {{ ticket.current_priority }}
                                </span>
                            </td>

                            <!-- ========================================= -->
                            <!-- STATUS -->
                            <!-- ========================================= -->

                            <td class="px-5 py-4 align-top">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold"
                                    :class="{
                                        'border-blue-500/20 bg-blue-500/10 text-blue-600 dark:text-blue-400':
                                            ticket.status === 'open',

                                        'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400':
                                            ticket.status === 'on_progress',

                                        'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400':
                                            ticket.status === 'resolved',

                                        'border-slate-500/20 bg-slate-500/10 text-slate-600 dark:text-slate-400':
                                            ticket.status === 'closed',
                                    }"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-current"
                                    ></span>

                                    <span class="capitalize">
                                        {{ ticket.status.replace('_', ' ') }}
                                    </span>
                                </span>
                            </td>

                            <!-- ========================================= -->
                            <!-- TICKET DURATION -->
                            <!-- ========================================= -->

                            <td class="px-5 py-4 align-top whitespace-nowrap">
                                <div
                                    class="font-mono text-sm font-semibold tabular-nums"
                                >
                                    {{
                                        formatTicketDuration(
                                            ticket.ticket_duration_seconds,
                                        )
                                    }}
                                </div>

                                <div class="mt-1 text-xs text-muted-foreground">
                                    sejak dibuat
                                </div>
                            </td>

                            <!-- ========================================= -->
                            <!-- DIBUAT OLEH -->
                            <!-- ========================================= -->

                            <td class="px-5 py-4 align-top">
                                <div class="font-medium">
                                    {{ ticket.creator?.name ?? '-' }}
                                </div>

                                <div class="mt-1 text-xs text-muted-foreground">
                                    Creator
                                </div>
                            </td>

                            <!-- ========================================= -->
                            <!-- HANDLE SAAT INI -->
                            <!-- ========================================= -->

                            <td class="px-5 py-4 align-top">
                                <div class="font-medium">
                                    {{
                                        ticket.latest_update?.user?.name ?? '-'
                                    }}
                                </div>

                                <div
                                    v-if="ticket.latest_update"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    Update terakhir
                                </div>

                                <div
                                    v-else
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    Belum ada update
                                </div>
                            </td>

                            <!-- ========================================= -->
                            <!-- REPORTED AT -->
                            <!-- ========================================= -->

                            <td
                                class="px-5 py-4 align-top whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDateTime(ticket.reported_at) }}
                            </td>

                            <!-- ========================================= -->
                            <!-- SLA TIMER -->
                            <!-- ========================================= -->
                            <td class="px-5 py-4 align-top">
                                <div
                                    class="inline-flex min-w-[120px] flex-col rounded-lg border px-3 py-2"
                                    :class="{
                                        'border-emerald-500/20 bg-emerald-500/5':
                                            ticket.sla_timer_status ===
                                            'running',

                                        'border-amber-500/20 bg-amber-500/5':
                                            ticket.sla_timer_status ===
                                            'stop_clock',

                                        'border-slate-500/20 bg-slate-500/5':
                                            ticket.sla_timer_status ===
                                                'resolved' ||
                                            ticket.sla_timer_status ===
                                                'not_applicable',
                                    }"
                                >
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="h-2 w-2 rounded-full"
                                            :class="{
                                                'animate-pulse bg-emerald-500':
                                                    ticket.sla_timer_status ===
                                                    'running',

                                                'bg-amber-500':
                                                    ticket.sla_timer_status ===
                                                    'stop_clock',

                                                'bg-slate-400':
                                                    ticket.sla_timer_status ===
                                                        'resolved' ||
                                                    ticket.sla_timer_status ===
                                                        'not_applicable',
                                            }"
                                        ></span>

                                        <span
                                            class="text-[11px] font-medium tracking-wide uppercase"
                                            :class="{
                                                'text-emerald-600 dark:text-emerald-400':
                                                    ticket.sla_timer_status ===
                                                    'running',

                                                'text-amber-600 dark:text-amber-400':
                                                    ticket.sla_timer_status ===
                                                    'stop_clock',

                                                'text-slate-500 dark:text-slate-400':
                                                    ticket.sla_timer_status ===
                                                        'resolved' ||
                                                    ticket.sla_timer_status ===
                                                        'not_applicable',
                                            }"
                                        >
                                            {{
                                                ticket.sla_timer_status ===
                                                'running'
                                                    ? 'Running'
                                                    : ticket.sla_timer_status ===
                                                        'stop_clock'
                                                      ? 'Stop Clock'
                                                      : ticket.sla_timer_status ===
                                                          'resolved'
                                                        ? 'Resolved'
                                                        : 'N/A'
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        v-if="
                                            ticket.sla_timer_status ===
                                                'running' ||
                                            ticket.sla_timer_status ===
                                                'stop_clock' ||
                                            ticket.sla_timer_status ===
                                                'resolved'
                                        "
                                        class="mt-1 font-mono text-base font-semibold tabular-nums"
                                    >
                                        {{
                                            formatSlaTimer(
                                                ticket.sla_timer_seconds,
                                            )
                                        }}
                                    </div>

                                    <div
                                        v-else
                                        class="mt-1 font-mono text-base font-semibold text-muted-foreground"
                                    >
                                        —
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <!-- ============================================= -->
                        <!-- EMPTY STATE -->
                        <!-- ============================================= -->

                        <tr v-if="tickets.length === 0">
                            <td colspan="9" class="px-5 py-12 text-center">
                                <div
                                    class="mx-auto flex max-w-sm flex-col items-center"
                                >
                                    <div
                                        class="flex h-12 w-12 items-center justify-center rounded-full bg-muted"
                                    >
                                        <span
                                            class="text-xl text-muted-foreground"
                                        >
                                            —
                                        </span>
                                    </div>

                                    <p class="mt-4 font-medium">
                                        Tidak ada ticket yang sedang dimonitor
                                    </p>

                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        Ticket baru akan muncul otomatis ketika
                                        diterima melalui realtime monitoring.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
