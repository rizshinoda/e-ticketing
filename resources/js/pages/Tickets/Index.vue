<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
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
} from 'lucide-vue-next';
import Swal from 'sweetalert2';

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
    incidents_count?: number;
    latest_incident: LatestIncident | null;
}
interface Kendala {
    id: number;
    name: string;
}
interface LatestIncident {
    id: number;
    incident_number: number;
    kendala_id: number;
    category: Kendala | null;
}
interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTickets {
    from: number | null;
    to: number | null;
    data: Ticket[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
}

interface TicketStats {
    active: number;
    resolved_today: number;
    closed_today: number;
    critical_active: number;
}

const props = defineProps<{
    tickets: PaginatedTickets;
    ticketStats: TicketStats;
    kendalas: Kendala[];
    filters: {
        search?: string | null;
        ticket_type?: string | null;
        kendala?: string | null;
        status?: string | null;
        priority?: string | null;
        date?: string | null;
    };
}>();

/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

const search = ref(props.filters.search ?? '');
const ticketType = ref(props.filters.ticket_type ?? '');
const kendala = ref(props.filters.kendala ?? '');
const status = ref(props.filters.status ?? '');
const priority = ref(props.filters.priority ?? '');
const date = ref(props.filters.date ?? '');

const doSearch = () => {
    const keyword = search.value.trim();

    router.get(
        '/tickets',
        {
            ...(keyword ? { search: keyword } : {}),
            ...(ticketType.value ? { ticket_type: ticketType.value } : {}),
            ...(kendala.value ? { kendala: kendala.value } : {}),
            ...(status.value ? { status: status.value } : {}),
            ...(priority.value ? { priority: priority.value } : {}),
            ...(date.value ? { date: date.value } : {}),
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const resetFilters = () => {
    search.value = '';
    ticketType.value = '';
    kendala.value = '';
    status.value = '';
    priority.value = '';
    date.value = '';

    router.get(
        '/tickets',
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

/*
|--------------------------------------------------------------------------
| DELETE TICKET
|--------------------------------------------------------------------------
*/

const deleteTicket = (ticket: Ticket) => {
    // Hanya Open yang boleh dihapus
    if (ticket.status !== 'open') {
        Swal.fire({
            icon: 'error',
            title: 'Tidak Bisa Dihapus',
            text: 'Hanya ticket dengan status Open yang dapat dihapus.',
            confirmButtonText: 'OK',
        });

        return;
    }

    // Ticket yang pernah di-reopen tidak boleh dihapus
    if ((ticket.incidents_count ?? 1) > 1) {
        Swal.fire({
            icon: 'error',
            title: 'Tidak Bisa Dihapus',
            text: 'Ticket yang pernah di-re-open tidak dapat dihapus.',
            confirmButtonText: 'OK',
        });

        return;
    }

    Swal.fire({
        title: 'Hapus Ticket?',
        text: 'Ticket yang dihapus tidak dapat dikembalikan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true,
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        router.delete(`/tickets/${ticket.id}`, {
            preserveScroll: true,

            onSuccess: () => {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Ticket berhasil dihapus.',
                    confirmButtonText: 'OK',
                });
            },
        });
    });
};

/*
|--------------------------------------------------------------------------
| EDIT TICKET
|--------------------------------------------------------------------------
*/

const editTicket = (ticket: Ticket) => {
    // Ticket yang sudah selesai tidak boleh diedit
    if (ticket.status === 'resolved' || ticket.status === 'closed') {
        Swal.fire({
            icon: 'error',
            title: 'Tidak Bisa Edit',
            text: 'Ticket yang sudah selesai tidak dapat diedit.',
            confirmButtonText: 'OK',
        });

        return;
    }

    // Ticket yang pernah di-reopen tidak boleh diedit
    if ((ticket.incidents_count ?? 1) > 1) {
        Swal.fire({
            icon: 'error',
            title: 'Tidak Bisa Edit',
            text: 'Ticket yang pernah di-re-open tidak dapat diedit.',
            confirmButtonText: 'OK',
        });

        return;
    }

    router.visit(`/tickets/${ticket.id}/edit`);
};

/*
|--------------------------------------------------------------------------
| LABEL
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| BADGE CLASS
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| DATE
|--------------------------------------------------------------------------
*/

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};
</script>
<template>
    <div class="min-h-screen bg-background px-4 py-6 sm:px-6 lg:px-8">
        <div class="w-full">
            <!-- =====================================================
                 HEADER
            ====================================================== -->

            <div
                class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <div
                        class="mb-2 flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <span>Platform</span>
                        <span>/</span>
                        <span class="text-foreground"> Tickets </span>
                    </div>

                    <h1 class="text-3xl font-bold tracking-tight">Tickets</h1>

                    <p class="mt-2 text-sm text-muted-foreground">
                        Daftar ticket gangguan yang sedang dan telah diproses.
                    </p>
                </div>

                <Link
                    href="/tickets/create"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90"
                >
                    <TicketPlus class="h-4 w-4" />
                    Buat Ticket
                </Link>
            </div>

            <!-- =====================================================
     TICKET SUMMARY
====================================================== -->
            <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- TICKET BERJALAN -->
                <div
                    class="group rounded-xl border bg-card p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-500"
                        >
                            <Activity class="h-6 w-6" />
                        </div>

                        <div class="min-w-0">
                            <p
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Ticket Berjalan
                            </p>

                            <p class="mt-1 text-2xl font-bold tracking-tight">
                                {{ props.ticketStats.active }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- RESOLVED HARI INI -->
                <div
                    class="group rounded-xl border bg-card p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500"
                        >
                            <CheckCircle2 class="h-6 w-6" />
                        </div>

                        <div class="min-w-0">
                            <p
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Resolved Hari Ini
                            </p>

                            <p class="mt-1 text-2xl font-bold tracking-tight">
                                {{ props.ticketStats.resolved_today }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- CLOSED HARI INI -->
                <div
                    class="group rounded-xl border bg-card p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-500/10 text-slate-400"
                        >
                            <Lock class="h-6 w-6" />
                        </div>

                        <div class="min-w-0">
                            <p
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Closed Hari Ini
                            </p>

                            <p class="mt-1 text-2xl font-bold tracking-tight">
                                {{ props.ticketStats.closed_today }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- CRITICAL AKTIF -->
                <div
                    class="group rounded-xl border bg-card p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-red-500/10 text-red-500"
                        >
                            <AlertTriangle class="h-6 w-6" />
                        </div>

                        <div class="min-w-0">
                            <p
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Critical Aktif
                            </p>

                            <p class="mt-1 text-2xl font-bold tracking-tight">
                                {{ props.ticketStats.critical_active }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- =====================================================
                 TABLE CARD
            ====================================================== -->

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <!-- =====================================================
     FILTER
====================================================== -->
                <div class="border-b px-5 py-5">
                    <div class="flex flex-col gap-4">
                        <!-- FILTER INPUTS -->
                        <div
                            class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_160px_160px_160px_160px_160px]"
                        >
                            <!-- SEARCH -->
                            <div class="relative">
                                <Search
                                    class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                />

                                <input
                                    v-model="search"
                                    type="text"
                                    placeholder="Search ticket ID, customer, site..."
                                    class="h-10 w-full rounded-lg border border-input bg-background pr-3 pl-9 text-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />
                            </div>

                            <!-- TICKET TYPE -->
                            <select
                                v-model="ticketType"
                                class="h-10 rounded-lg border border-input bg-background px-3 text-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            >
                                <option value="">Ticket Type</option>
                                <option value="individual">Individual</option>
                                <option value="gamas">GAMAS</option>
                            </select>

                            <!-- KENDALA -->
                            <select
                                v-model="kendala"
                                class="h-10 rounded-lg border border-input bg-background px-3 text-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            >
                                <option value="">Kendala</option>

                                <option
                                    v-for="item in props.kendalas"
                                    :key="item.id"
                                    :value="item.id"
                                >
                                    {{ item.name }}
                                </option>
                            </select>

                            <!-- STATUS -->
                            <select
                                v-model="status"
                                class="h-10 rounded-lg border border-input bg-background px-3 text-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            >
                                <option value="">Status</option>
                                <option value="open">Open</option>
                                <option value="on_progress">On Progress</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>

                            <!-- PRIORITY -->
                            <select
                                v-model="priority"
                                class="h-10 rounded-lg border border-input bg-background px-3 text-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            >
                                <option value="">Priority</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>

                            <!-- DATE -->
                            <input
                                v-model="date"
                                type="date"
                                class="h-10 rounded-lg border border-input bg-background px-3 text-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />
                        </div>

                        <!-- FILTER ACTION -->
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:justify-end"
                        >
                            <!-- RESET -->
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-medium transition hover:bg-muted"
                                @click="resetFilters"
                            >
                                <RotateCcw class="h-4 w-4" />
                                Reset
                            </button>

                            <!-- CARI -->
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90"
                                @click="doSearch"
                            >
                                <Search class="h-4 w-4" />
                                Cari
                            </button>
                        </div>
                    </div>
                </div>
                <!-- TOTAL -->
                <!-- =================================================
                     TABLE
                ================================================== -->

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[950px]">
                        <!-- HEADER -->
                        <thead>
                            <tr class="border-b bg-muted/40">
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    No
                                </th>

                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    No Ticket
                                </th>

                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    Customer / Site
                                </th>

                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    Jenis
                                </th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    Kendala
                                </th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    Priority
                                </th>

                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    Status
                                </th>

                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    Dibuat
                                </th>

                                <th
                                    class="px-5 py-3 text-right text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    Aksi
                                </th>
                            </tr>
                        </thead>

                        <!-- BODY -->
                        <tbody>
                            <!-- DATA -->
                            <tr
                                v-for="(ticket, index) in props.tickets.data"
                                :key="ticket.id"
                                class="border-b transition last:border-b-0 hover:bg-muted/20"
                            >
                                <!-- =================================
                                     TICKET
                                ================================== -->
                                <td class="px-5 py-4 align-top">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-muted text-xs font-semibold"
                                    >
                                        {{
                                            (props.tickets.current_page - 1) *
                                                props.tickets.per_page +
                                            index +
                                            1
                                        }}
                                    </div>
                                </td>

                                <td class="px-5 py-4 align-top">
                                    <div class="flex items-start gap-3">
                                        <div class="min-w-0">
                                            <div class="font-semibold">
                                                {{ ticket.ticket_number }}
                                            </div>

                                            <div
                                                class="mt-1 max-w-xs truncate text-xs text-muted-foreground"
                                                :title="
                                                    ticket.description || '-'
                                                "
                                            >
                                                {{ ticket.description || '-' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- =================================
                                     CUSTOMER / SITE
                                ================================== -->

                                <!-- =================================
     CUSTOMER / SITE
================================== -->
                                <td
                                    class="w-[280px] max-w-[280px] px-5 py-4 align-top"
                                >
                                    <div v-if="ticket.customers.length">
                                        <!-- Customer -->
                                        <div
                                            class="truncate font-medium"
                                            :title="
                                                ticket.customers[0]
                                                    .customer_name || '-'
                                            "
                                        >
                                            {{
                                                ticket.customers[0]
                                                    .customer_name || '-'
                                            }}
                                        </div>

                                        <!-- Site -->
                                        <div
                                            class="mt-1 line-clamp-2 text-sm text-foreground/90"
                                            :title="
                                                ticket.customers[0].site_name ||
                                                '-'
                                            "
                                        >
                                            {{
                                                ticket.customers[0].site_name ||
                                                '-'
                                            }}
                                        </div>

                                        <!-- No Jaringan -->
                                        <div
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{
                                                ticket.customers[0]
                                                    .no_jaringan || '-'
                                            }}

                                            <span
                                                v-if="
                                                    ticket.customers.length > 1
                                                "
                                                class="ml-1 rounded-full bg-muted px-2 py-0.5"
                                            >
                                                +{{
                                                    ticket.customers.length - 1
                                                }}
                                                site
                                            </span>
                                        </div>
                                    </div>

                                    <span
                                        v-else
                                        class="text-sm text-muted-foreground"
                                    >
                                        —
                                    </span>
                                </td>

                                <!-- =================================
                                     TYPE
                                ================================== -->

                                <td class="px-5 py-4 align-top">
                                    <span
                                        class="inline-flex rounded-full border bg-muted px-2.5 py-1 text-xs font-medium"
                                    >
                                        {{
                                            ticket.ticket_type === 'gamas'
                                                ? 'GAMAS'
                                                : 'Individual'
                                        }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <span
                                        v-if="ticket.latest_incident?.category"
                                        class="inline-flex items-center rounded-md border border-border/70 bg-muted/40 px-2.5 py-1 text-xs font-medium text-foreground"
                                    >
                                        {{
                                            ticket.latest_incident.category.name
                                        }}
                                    </span>

                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                    >
                                        —
                                    </span>
                                </td>
                                <!-- =================================
                                     PRIORITY
                                ================================== -->

                                <td class="px-5 py-4 align-top">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                                            priorityClass(ticket.priority),
                                        ]"
                                    >
                                        {{ priorityLabel(ticket.priority) }}
                                    </span>
                                </td>

                                <!-- =================================
                                     STATUS
                                ================================== -->

                                <td class="px-5 py-4 align-top">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                                            statusClass(ticket.status),
                                        ]"
                                    >
                                        {{ statusLabel(ticket.status) }}
                                    </span>
                                </td>

                                <!-- =================================
                                     CREATED
                                ================================== -->

                                <td
                                    class="px-5 py-4 align-top text-sm whitespace-nowrap text-muted-foreground"
                                >
                                    {{ formatDate(ticket.created_at) }}
                                </td>

                                <!-- =================================
                                     ACTION
                                ================================== -->

                                <!-- =================================
     ACTION
================================== -->
                                <td class="px-5 py-4 align-top">
                                    <div
                                        class="flex items-center justify-end gap-1.5"
                                    >
                                        <!-- Detail -->
                                        <Link
                                            :href="`/tickets/${ticket.id}`"
                                            title="Lihat Detail"
                                            class="group inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border/70 bg-background text-muted-foreground transition-all duration-200 hover:border-blue-500/40 hover:bg-blue-500/10 hover:text-blue-500 hover:shadow-sm"
                                        >
                                            <Eye
                                                class="h-4 w-4 transition-transform duration-200 group-hover:scale-110"
                                            />
                                        </Link>

                                        <!-- Edit -->
                                        <button
                                            type="button"
                                            title="Edit Ticket"
                                            @click="editTicket(ticket)"
                                            class="group inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border/70 bg-background text-muted-foreground transition-all duration-200 hover:border-amber-500/40 hover:bg-amber-500/10 hover:text-amber-500 hover:shadow-sm"
                                        >
                                            <Pencil
                                                class="h-4 w-4 transition-transform duration-200 group-hover:scale-110"
                                            />
                                        </button>

                                        <!-- Hapus -->
                                        <button
                                            type="button"
                                            title="Hapus Ticket"
                                            @click="deleteTicket(ticket)"
                                            class="group inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border/70 bg-background text-muted-foreground transition-all duration-200 hover:border-red-500/40 hover:bg-red-500/10 hover:text-red-500 hover:shadow-sm"
                                        >
                                            <Trash2
                                                class="h-4 w-4 transition-transform duration-200 group-hover:scale-110"
                                            />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- =================================
                                 EMPTY STATE
                            ================================== -->

                            <tr v-if="props.tickets.data.length === 0">
                                <td colspan="7" class="px-5 py-16 text-center">
                                    <div
                                        class="mx-auto flex max-w-sm flex-col items-center"
                                    >
                                        <div
                                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-muted text-lg font-semibold"
                                        >
                                            —
                                        </div>

                                        <h3 class="mt-4 font-semibold">
                                            Belum ada ticket
                                        </h3>

                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            Belum terdapat ticket gangguan yang
                                            tercatat.
                                        </p>

                                        <Link
                                            href="/tickets/create"
                                            class="mt-5 inline-flex items-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                                        >
                                            + Buat Ticket
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- =================================================
                     TABLE FOOTER
                ================================================== -->

                <div
                    v-if="props.tickets.data.length"
                    class="flex flex-col gap-3 border-t px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-xs text-muted-foreground">
                        Menampilkan
                        <span class="font-medium text-foreground">
                            {{ props.tickets.from }}
                        </span>
                        -
                        <span class="font-medium text-foreground">
                            {{ props.tickets.to }}
                        </span>
                        dari
                        <span class="font-medium text-foreground">
                            {{ props.tickets.total }}
                        </span>
                        ticket
                    </p>

                    <!-- Pagination -->
                    <!-- Pagination -->
                    <div
                        v-if="props.tickets.last_page > 1"
                        class="flex flex-wrap gap-1.5"
                    >
                        <template
                            v-for="(link, index) in props.tickets.links"
                            :key="index"
                        >
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                :class="[
                                    'rounded-lg border px-3 py-2 text-sm transition',
                                    link.active
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-background hover:bg-muted',
                                ]"
                            >
                                {{
                                    link.label.includes('previous')
                                        ? '‹ Sebelumnya'
                                        : link.label.includes('next')
                                          ? 'Berikutnya ›'
                                          : link.label
                                }}
                            </Link>

                            <span
                                v-else
                                class="rounded-lg border px-3 py-2 text-sm text-muted-foreground opacity-60"
                            >
                                {{
                                    link.label.includes('previous')
                                        ? '‹ Sebelumnya'
                                        : link.label.includes('next')
                                          ? 'Berikutnya ›'
                                          : link.label
                                }}
                            </span>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
