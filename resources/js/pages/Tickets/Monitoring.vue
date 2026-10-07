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
    customers: {
        id: number;
        customer_name: string;
        site_name: string;
        no_jaringan: string | null;
    }[];
}

interface TicketCreatedEvent {
    ticketId: number;
    ticketNumber: string;
    ticketType: 'individual' | 'gamas';
    priority: 'low' | 'medium' | 'high' | 'critical';
    currentPriority: 'low' | 'medium' | 'high' | 'critical';
    status: 'open' | 'on_progress' | 'resolved' | 'closed';
    reportedAt: string;
    sla_timer_seconds: number;
    sla_timer_running: boolean;
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
 * props.tickets = data awal saat halaman dibuka.
 * tickets = data yang akan berubah ketika menerima event Reverb.
 */
const tickets = ref<Ticket[]>([...props.tickets]);

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
const formatSlaTimer = (seconds: number) => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;

    return [hours, minutes, remainingSeconds]
        .map((value) => String(value).padStart(2, '0'))
        .join(':');
};
const updateSlaTimers = () => {
    tickets.value.forEach((ticket) => {
        if (ticket.sla_timer_running) {
            ticket.sla_timer_seconds++;
        }
    });
};

const slaTimerInterval = setInterval(() => {
    updateSlaTimers();
}, 1000);
onUnmounted(() => {
    clearInterval(slaTimerInterval);
});
/**
 * Menerima ticket baru dari Reverb.
 */
useEchoPublic(
    'ticket-monitor',
    'TicketCreated',
    (event: TicketCreatedEvent) => {
        console.log('🎫 MONITORING - TICKET CREATED:', event);

        /**
         * Jangan masukkan ticket jika sudah ada.
         * Ini mencegah duplicate row jika event diterima lebih dari sekali.
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
            sla_timer_seconds: event.sla_timer_seconds,
            sla_timer_running: event.sla_timer_running,
        };

        /**
         * Ticket baru ditaruh paling atas.
         */
        tickets.value.unshift(newTicket);
    },
);
</script>

<template>
    <Head title="Ticket Monitoring" />

    <div class="space-y-6 p-6">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-semibold">Ticket Monitoring</h1>

            <p class="mt-1 text-muted-foreground">Monitoring tiket realtime.</p>
        </div>

        <!-- Table -->
        <div class="rounded-xl border">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="px-4 py-3 text-left">Ticket</th>

                            <th class="px-4 py-3 text-left">Customer / Site</th>

                            <th class="px-4 py-3 text-left">Priority</th>

                            <th class="px-4 py-3 text-left">Status</th>

                            <th class="px-4 py-3 text-left">Dilaporkan</th>
                            <th class="px-4 py-3 text-left">SLA Timer</th>
                        </tr>
                    </thead>

                    <tbody>
                        <!-- Data ticket -->
                        <tr
                            v-for="ticket in tickets"
                            :key="ticket.id"
                            class="border-b last:border-0"
                        >
                            <!-- Ticket -->
                            <td class="px-4 py-3 font-medium">
                                {{ ticket.ticket_number }}
                            </td>

                            <!-- Customer / Site -->
                            <td class="px-4 py-3">
                                <div>
                                    {{
                                        ticket.customers[0]?.customer_name ??
                                        '-'
                                    }}
                                </div>

                                <div class="text-xs text-muted-foreground">
                                    {{ ticket.customers[0]?.site_name ?? '-' }}
                                </div>
                            </td>

                            <!-- Priority -->
                            <td class="px-4 py-3">
                                {{ ticket.current_priority }}
                            </td>

                            <!-- Status -->
                            <td class="px-4 py-3">
                                {{ ticket.status }}
                            </td>

                            <!-- Reported At -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ formatDateTime(ticket.reported_at) }}
                            </td>
                            <td class="px-4 py-3 font-mono whitespace-nowrap">
                                {{ formatSlaTimer(ticket.sla_timer_seconds) }}
                            </td>
                        </tr>

                        <!-- Empty state -->
                        <tr v-if="tickets.length === 0">
                            <td
                                colspan="5"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                Tidak ada ticket yang sedang dimonitor.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
