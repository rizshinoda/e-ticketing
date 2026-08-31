<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Customer {
    id: number;
    customer_name: string | null;
    site_name: string | null;
    no_jaringan: string | null;
}

interface User {
    id: number;
    name: string;
}

interface Ticket {
    id: number;
    ticket_number: string;
    ticket_type: 'individual' | 'gamas';
    priority: 'low' | 'medium' | 'high' | 'critical';
    status: 'open' | 'on_progress' | 'resolved' | 'closed';
    description: string | null;
    created_at: string;
    creator: User | null;
    customers: Customer[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTickets {
    data: Ticket[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
}

const props = defineProps<{
    tickets: PaginatedTickets;
}>();

const priorityLabel = (priority: Ticket['priority']) => {
    const labels = {
        low: 'Low',
        medium: 'Medium',
        high: 'High',
        critical: 'Critical',
    };

    return labels[priority];
};

const statusLabel = (status: Ticket['status']) => {
    const labels = {
        open: 'Open',
        on_progress: 'On Progress',
        resolved: 'Resolved',
        closed: 'Closed',
    };

    return labels[status];
};

const priorityClass = (priority: Ticket['priority']) => {
    switch (priority) {
        case 'critical':
            return 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300';

        case 'high':
            return 'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300';

        case 'medium':
            return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300';

        default:
            return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
    }
};

const statusClass = (status: Ticket['status']) => {
    switch (status) {
        case 'open':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300';

        case 'on_progress':
            return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300';

        case 'resolved':
            return 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300';

        case 'closed':
            return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';

        default:
            return 'bg-gray-100 text-gray-700';
    }
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};
</script>

<template>
    <div class="p-6">
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Tickets</h1>

                <p class="text-sm text-muted-foreground">
                    Daftar ticket gangguan.
                </p>
            </div>

            <Link
                href="/tickets/create"
                class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
            >
                Buat Ticket
            </Link>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-lg border">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b bg-muted/50">
                            <th class="px-4 py-3 text-left text-sm">Ticket</th>

                            <th class="px-4 py-3 text-left text-sm">
                                Customer / Site
                            </th>

                            <th class="px-4 py-3 text-left text-sm">Jenis</th>

                            <th class="px-4 py-3 text-left text-sm">
                                Priority
                            </th>

                            <th class="px-4 py-3 text-left text-sm">Status</th>

                            <th class="px-4 py-3 text-left text-sm">Dibuat</th>

                            <th class="px-4 py-3 text-right text-sm">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="ticket in props.tickets.data"
                            :key="ticket.id"
                            class="border-b last:border-b-0"
                        >
                            <!-- Ticket -->
                            <td class="px-4 py-4">
                                <div class="font-medium">
                                    {{ ticket.ticket_number }}
                                </div>

                                <div
                                    class="mt-1 max-w-xs truncate text-xs text-muted-foreground"
                                >
                                    {{ ticket.description || '-' }}
                                </div>
                            </td>

                            <!-- Customer -->
                            <td class="px-4 py-4">
                                <div v-if="ticket.customers.length">
                                    <div class="font-medium">
                                        {{
                                            ticket.customers[0].customer_name ||
                                            '-'
                                        }}
                                    </div>

                                    <div class="text-sm">
                                        {{
                                            ticket.customers[0].site_name || '-'
                                        }}
                                    </div>

                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            ticket.customers[0].no_jaringan ||
                                            '-'
                                        }}

                                        <span
                                            v-if="ticket.customers.length > 1"
                                        >
                                            +
                                            {{ ticket.customers.length - 1 }}
                                            site
                                        </span>
                                    </div>
                                </div>

                                <span v-else class="text-muted-foreground">
                                    -
                                </span>
                            </td>

                            <!-- Type -->
                            <td class="px-4 py-4">
                                <span
                                    class="rounded-md bg-muted px-2 py-1 text-xs"
                                >
                                    {{
                                        ticket.ticket_type === 'gamas'
                                            ? 'GAMAS'
                                            : 'Individual'
                                    }}
                                </span>
                            </td>

                            <!-- Priority -->
                            <td class="px-4 py-4">
                                <span
                                    class="rounded-md px-2 py-1 text-xs font-medium"
                                    :class="priorityClass(ticket.priority)"
                                >
                                    {{ priorityLabel(ticket.priority) }}
                                </span>
                            </td>

                            <!-- Status -->
                            <td class="px-4 py-4">
                                <span
                                    class="rounded-md px-2 py-1 text-xs font-medium"
                                    :class="statusClass(ticket.status)"
                                >
                                    {{ statusLabel(ticket.status) }}
                                </span>
                            </td>

                            <!-- Created -->
                            <td class="px-4 py-4 text-sm">
                                {{ formatDate(ticket.created_at) }}
                            </td>

                            <!-- Action -->
                            <td class="px-4 py-4 text-right">
                                <Link
                                    :href="`/tickets/${ticket.id}`"
                                    class="text-sm font-medium hover:underline"
                                >
                                    Detail
                                </Link>
                            </td>
                        </tr>

                        <tr v-if="props.tickets.data.length === 0">
                            <td
                                colspan="7"
                                class="px-4 py-10 text-center text-muted-foreground"
                            >
                                Belum ada ticket.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div
            v-if="props.tickets.last_page > 1"
            class="mt-4 flex flex-wrap gap-2"
        >
            <template v-for="(link, index) in props.tickets.links" :key="index">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-md border px-3 py-2 text-sm"
                    :class="{
                        'bg-primary text-primary-foreground': link.active,
                    }"
                    v-html="link.label"
                />

                <span
                    v-else
                    class="rounded-md border px-3 py-2 text-sm text-muted-foreground"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
