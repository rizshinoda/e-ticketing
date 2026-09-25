<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Eye, Pencil, Trash2 } from 'lucide-vue-next';
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
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTickets {
    from: any;
    to: any;
    data: Ticket[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
}

const props = defineProps<{
    tickets: PaginatedTickets;
    ticketStats: {
        total: number;
        open: number;
        on_progress: number;
        resolved: number;
        closed: number;
    };
    filters: {
        status?: string | null;
        search?: string | null;
    };
}>();

const search = ref(props.filters.search ?? '');
const filterStatus = (status: string | null) => {
    router.get(
        '/tickets',
        {
            ...(status ? { status } : {}),
            ...(search.value ? { search: search.value } : {}),
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};
const deleteTicket = (ticket: any) => {
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
    if (ticket.incidents_count > 1) {
        Swal.fire({
            icon: 'error',
            title: 'Tidak Bisa Dihapus',
            text: 'Ticket yang pernah di-re-open tidak dapat dihapus.',
            confirmButtonText: 'OK',
        });

        return;
    }

    // Kalau boleh hapus, tampilkan konfirmasi
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

const editTicket = (ticket: any) => {
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

    // Ticket yang pernah di-reopen memiliki lebih dari 1 incident
    if (ticket.incidents_count > 1) {
        Swal.fire({
            icon: 'error',
            title: 'Tidak Bisa Edit',
            text: 'Ticket yang pernah di-re-open tidak dapat diedit.',
            confirmButtonText: 'OK',
        });

        return;
    }

    // Kalau boleh edit
    router.visit(`/tickets/${ticket.id}/edit`);
};
const doSearch = () => {
    const keyword = search.value.trim();

    router.get(
        '/tickets',
        {
            ...(keyword ? { search: keyword } : {}),
            ...(props.filters.status ? { status: props.filters.status } : {}),
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};
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
    <div class="min-h-screen bg-background px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
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
                    class="inline-flex shrink-0 items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90"
                >
                    + Buat Ticket
                </Link>
            </div>

            <!-- =====================================================
     TICKET SUMMARY
====================================================== -->

            <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <!-- TOTAL -->
                <!-- TOTAL -->
                <button
                    type="button"
                    class="w-full rounded-xl border bg-card p-5 text-center shadow-sm transition hover:bg-muted/30"
                    :class="{
                        'ring-2 ring-primary': !props.filters.status,
                    }"
                    @click="filterStatus(null)"
                >
                    <div class="flex flex-col items-center justify-center">
                        <p class="text-sm text-muted-foreground">
                            Total Ticket
                        </p>

                        <p class="mt-2 text-2xl font-bold">
                            {{ props.ticketStats.total }}
                        </p>
                    </div>
                </button>

                <!-- OPEN -->
                <button
                    type="button"
                    class="w-full rounded-xl border bg-card p-5 text-center shadow-sm transition hover:bg-muted/30"
                    :class="{
                        'ring-2 ring-blue-500': props.filters.status === 'open',
                    }"
                    @click="filterStatus('open')"
                >
                    <div class="flex flex-col items-center justify-between">
                        <div>
                            <p class="text-sm text-muted-foreground">Open</p>

                            <p class="mt-2 text-2xl font-bold">
                                {{ props.ticketStats.open }}
                            </p>
                        </div>
                    </div>
                </button>

                <!-- ON PROGRESS -->
                <button
                    type="button"
                    class="w-full rounded-xl border bg-card p-5 text-center shadow-sm transition hover:bg-muted/30"
                    :class="{
                        'ring-2 ring-yellow-500':
                            props.filters.status === 'on_progress',
                    }"
                    @click="filterStatus('on_progress')"
                >
                    <div class="flex flex-col items-center justify-between">
                        <div>
                            <p class="text-sm text-muted-foreground">
                                On Progress
                            </p>

                            <p class="mt-2 text-2xl font-bold">
                                {{ props.ticketStats.on_progress }}
                            </p>
                        </div>
                    </div>
                </button>

                <!-- RESOLVED -->
                <button
                    type="button"
                    class="w-full rounded-xl border bg-card p-5 text-center shadow-sm transition hover:bg-muted/30"
                    :class="{
                        'ring-2 ring-green-500':
                            props.filters.status === 'resolved',
                    }"
                    @click="filterStatus('resolved')"
                >
                    <div class="flex flex-col items-center justify-between">
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Resolved
                            </p>

                            <p class="mt-2 text-2xl font-bold">
                                {{ props.ticketStats.resolved }}
                            </p>
                        </div>
                    </div>
                </button>

                <!-- CLOSED -->
                <button
                    type="button"
                    class="w-full rounded-xl border bg-card p-5 text-center shadow-sm transition hover:bg-muted/30"
                    :class="{
                        'ring-2 ring-muted-foreground':
                            props.filters.status === 'closed',
                    }"
                    @click="filterStatus('closed')"
                >
                    <div class="flex flex-col items-center justify-between">
                        <div>
                            <p class="text-sm text-muted-foreground">Closed</p>

                            <p class="mt-2 text-2xl font-bold">
                                {{ props.ticketStats.closed }}
                            </p>
                        </div>
                    </div>
                </button>
            </div>

            <!-- =====================================================
                 TABLE CARD
            ====================================================== -->

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <!-- Table Header -->
                <div
                    class="mb-2 flex flex-col gap-2 border-b px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="font-semibold">Daftar Ticket</h2>

                        <p class="mt-1 text-xs text-muted-foreground">
                            Ticket gangguan yang tercatat pada sistem.
                        </p>
                    </div>

                    <div class="text-xs text-muted-foreground">
                        {{ props.tickets.total }} ticket
                    </div>
                </div>
                <!-- SEARCH -->
                <form
                    class="mb-2 flex flex-col gap-3 sm:flex-row"
                    @submit.prevent="doSearch"
                >
                    <div class="relative flex-1">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari no ticket, pelanggan, site, atau no jaringan..."
                            class="w-full rounded-lg border bg-background px-4 py-2.5 text-sm transition outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-primary"
                        />
                    </div>

                    <button
                        type="submit"
                        class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    >
                        Cari
                    </button>
                </form>

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

                                <td class="px-5 py-4 align-top">
                                    <div v-if="ticket.customers.length">
                                        <div class="font-medium">
                                            {{
                                                ticket.customers[0]
                                                    .customer_name || '-'
                                            }}
                                        </div>

                                        <div class="mt-1 text-sm">
                                            {{
                                                ticket.customers[0].site_name ||
                                                '-'
                                            }}
                                        </div>

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
                                        -
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

                                <td class="px-5 py-4 text-right align-top">
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <!-- Detail -->
                                        <Link
                                            :href="`/tickets/${ticket.id}`"
                                            class="inline-flex items-center justify-center rounded-lg border bg-background p-2.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                            title="Lihat Detail"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </Link>

                                        <!-- Edit -->
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-lg border bg-background p-2.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                            title="Edit Ticket"
                                            @click="editTicket(ticket)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>

                                        <!-- Hapus -->
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-lg border bg-background p-2.5 text-muted-foreground transition hover:bg-muted hover:text-destructive"
                                            title="Hapus Ticket"
                                            @click="deleteTicket(ticket)"
                                        >
                                            <Trash2 class="h-4 w-4" />
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
