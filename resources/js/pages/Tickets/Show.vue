<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
/*
|--------------------------------------------------------------------------
| Interfaces
|--------------------------------------------------------------------------
*/

interface User {
    id: number;
    name: string;
}

interface Category {
    id: number;
    name: string;
    is_downtime: boolean;
}

interface StopClock {
    id: number;
    started_at: string;
    ended_at: string | null;
    reason: string;
}

interface Rfo {
    id: number;
    rfo_number: string;
    content: string;
    created_at: string;
}

interface Incident {
    id: number;
    incident_number: number;
    reported_at: string;
    category: Category;
}

interface Customer {
    id: number;
    customer_name: string | null;
    site_name: string | null;
    no_jaringan: string | null;
    reported_via: string | null;

    online_billing: {
        id: number;
    } | null;

    incidents: Incident[];
}

interface Attachment {
    id: number;
    file_path: string;
    file_name: string;
    mime_type: string | null;
    file_size: number | null;
}

interface Update {
    id: number;
    message: string;
    created_at: string;
    user: User;
    attachments: Attachment[];
}

interface Ticket {
    id: number;
    ticket_number: string;
    ticket_type: 'individual' | 'gamas';
    description: string | null;
    priority: string;
    status: string;

    first_response_at: string | null;
    reported_at: string;
    resolved_at: string | null;
    downtime_minutes: number | null;
    closed_at: string | null;

    created_at: string;

    creator: User | null;
    customers: Customer[];
    stop_clocks: StopClock[];
    rfos: Rfo[];
    updates: Update[];
}

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/
const groupedCustomers = computed(() => {
    const groups: Record<string, Customer[]> = {};

    props.ticket.customers.forEach((customer) => {
        const customerName =
            customer.customer_name || 'Customer Tidak Diketahui';

        if (!groups[customerName]) {
            groups[customerName] = [];
        }

        groups[customerName].push(customer);
    });

    return Object.entries(groups).map(([customerName, sites]) => ({
        customerName,
        sites,
    }));
});
const props = defineProps<{
    ticket: Ticket;
}>();
const page = usePage<{
    flash: {
        success?: string;
    };
}>();
/*
|--------------------------------------------------------------------------
| Format Date
|--------------------------------------------------------------------------
*/

const formatDate = (date: string | null) => {
    if (!date) {
        return '-';
    }

    return new Date(date).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};

/*
|--------------------------------------------------------------------------
| Status Label
|--------------------------------------------------------------------------
*/

const statusLabel = (status: string) => {
    const labels: Record<string, string> = {
        open: 'Open',
        on_progress: 'On Progress',
        resolved: 'Resolved',
        closed: 'Closed',
    };

    return labels[status] ?? status;
};

/*
|--------------------------------------------------------------------------
| Priority Label
|--------------------------------------------------------------------------
*/

const priorityLabel = (priority: string) => {
    const labels: Record<string, string> = {
        low: 'Low',
        medium: 'Medium',
        high: 'High',
        critical: 'Critical',
    };

    return labels[priority] ?? priority;
};

/*
|--------------------------------------------------------------------------
| Priority Class
|--------------------------------------------------------------------------
*/

const priorityClass = (priority: string) => {
    const classes: Record<string, string> = {
        low: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

        medium: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300',

        high: 'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300',

        critical: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    };

    return classes[priority] ?? 'bg-muted text-muted-foreground';
};

/*
|--------------------------------------------------------------------------
| Status Class
|--------------------------------------------------------------------------
*/

const statusClass = (status: string) => {
    const classes: Record<string, string> = {
        open: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',

        on_progress:
            'bg-yellow-100 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300',

        resolved:
            'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',

        closed: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    };

    return classes[status] ?? 'bg-muted text-muted-foreground';
};

/*
|--------------------------------------------------------------------------
| Format Downtime
|--------------------------------------------------------------------------
*/

const formatDowntime = (minutes: number | null) => {
    if (minutes === null) {
        return '-';
    }

    const hours = Math.floor(minutes / 60);

    const remainingMinutes = minutes % 60;

    if (hours === 0) {
        return `${remainingMinutes} menit`;
    }

    return `${hours} jam ${remainingMinutes} menit`;
};

/*
|--------------------------------------------------------------------------
| Update Form
|--------------------------------------------------------------------------
*/

const updateForm = useForm<{
    message: string;
    attachments: File[];
}>({
    message: '',
    attachments: [],
});

/*
|--------------------------------------------------------------------------
| Handle Files
|--------------------------------------------------------------------------
*/

const handleFiles = (event: Event) => {
    const target = event.target as HTMLInputElement;

    if (!target.files) {
        return;
    }

    updateForm.attachments = Array.from(target.files);
};

/*
|--------------------------------------------------------------------------
| Submit Update
|--------------------------------------------------------------------------
*/

const submitUpdate = () => {
    updateForm.post(`/tickets/${props.ticket.id}/updates`, {
        forceFormData: true,

        preserveScroll: true,

        onSuccess: () => {
            updateForm.reset();
        },
    });
};

/*
|--------------------------------------------------------------------------
| Stop Clock Form
|--------------------------------------------------------------------------
*/

const stopClockForm = useForm<{
    reason: string;
}>({
    reason: '',
});

/*
|--------------------------------------------------------------------------
| Cancel Stop Clock
|--------------------------------------------------------------------------
*/

const cancelStopClockForm = () => {
    stopClockForm.reset();
};

/*
|--------------------------------------------------------------------------
| Start Stop Clock
|--------------------------------------------------------------------------
*/

const submitStopClock = () => {
    stopClockForm.post(`/tickets/${props.ticket.id}/stop-clock`, {
        preserveScroll: true,
        onSuccess: () => {
            stopClockForm.reset();
        },
    });
};

/*
|--------------------------------------------------------------------------
| Resume Stop Clock
|--------------------------------------------------------------------------
*/

const resumeStopClock = () => {
    stopClockForm.post(`/tickets/${props.ticket.id}/stop-clock/resume`, {
        preserveScroll: true,
        onSuccess: () => {
            stopClockForm.reset();
        },
    });
};
/*
|--------------------------------------------------------------------------
| Resolve Ticket
|--------------------------------------------------------------------------
*/

const resolveTicket = () => {
    useForm({}).post(`/tickets/${props.ticket.id}/resolve`, {
        preserveScroll: true,

        onError: (errors) => {
            console.log('RESOLVE ERRORS:', errors);
        },
    });
};
/*
|--------------------------------------------------------------------------
| Check Active Stop Clock
|--------------------------------------------------------------------------
*/

const hasActiveStopClock = () => {
    return props.ticket.stop_clocks.some(
        (stopClock) => stopClock.ended_at === null,
    );
};
</script>

<template>
    <!-- =====================================================
         FLASH MESSAGE
    ====================================================== -->
    <div
        v-if="page.props.flash.success"
        class="mb-4 rounded-md bg-green-100 px-4 py-3 text-sm text-green-700"
    >
        {{ page.props.flash.success }}
    </div>
    <div
        v-if="page.props.errors.resolve"
        class="mb-3 rounded-md bg-red-100 px-4 py-3 text-sm text-red-700"
    >
        {{ page.props.errors.resolve }}
    </div>
    <div class="p-6">
        <!-- =====================================================
             HEADER
        ====================================================== -->
        <div class="mb-6 flex items-start justify-between">
            <div>
                <div class="mb-1 text-sm text-muted-foreground">Ticket</div>

                <h1 class="text-2xl font-semibold">
                    {{ ticket.ticket_number }}
                </h1>

                <div class="mt-2 flex flex-wrap gap-2">
                    <!-- Ticket Type -->
                    <span class="rounded-md bg-muted px-2 py-1 text-xs">
                        {{
                            ticket.ticket_type === 'gamas'
                                ? 'GAMAS'
                                : 'Individual'
                        }}
                    </span>

                    <!-- Priority -->
                    <span
                        class="rounded-md px-2 py-1 text-xs font-medium"
                        :class="priorityClass(ticket.priority)"
                    >
                        {{ priorityLabel(ticket.priority) }}
                    </span>

                    <!-- Status -->
                    <span
                        class="rounded-md px-2 py-1 text-xs font-medium"
                        :class="statusClass(ticket.status)"
                    >
                        {{ statusLabel(ticket.status) }}
                    </span>
                </div>
            </div>

            <Link
                href="/tickets"
                class="rounded-md border px-4 py-2 text-sm hover:bg-muted"
            >
                Kembali
            </Link>
        </div>

        <!-- =====================================================
             MAIN GRID
        ====================================================== -->
        <div class="grid gap-6 lg:grid-cols-3">
            <!-- =================================================
                 LEFT / MAIN
            ================================================== -->
            <div class="space-y-6 lg:col-span-2">
                <!-- =================================================
                     DESCRIPTION
                ================================================== -->
                <div class="rounded-lg border p-5">
                    <h2 class="mb-3 font-semibold">Deskripsi Gangguan</h2>

                    <div class="text-sm whitespace-pre-wrap">
                        {{ ticket.description || '-' }}
                    </div>
                </div>

                <!-- =================================================
                     CUSTOMER / SITE
                ================================================== -->
                <div class="rounded-lg border p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-semibold">Customer / Site Terdampak</h2>

                        <span class="text-sm text-muted-foreground">
                            {{ ticket.customers.length }}
                            site
                        </span>
                    </div>

                    <div v-if="ticket.customers.length" class="space-y-4">
                        <!-- GROUP CUSTOMER -->
                        <div
                            v-for="group in groupedCustomers"
                            :key="group.customerName"
                            class="rounded-lg border p-4"
                        >
                            <!-- CUSTOMER NAME -->
                            <div class="mb-4">
                                <div class="font-medium">
                                    {{ group.customerName }}
                                </div>
                            </div>

                            <!-- SITES -->
                            <div class="space-y-4">
                                <div
                                    v-for="customer in group.sites"
                                    :key="customer.id"
                                    class="rounded-md bg-muted/40 p-4"
                                >
                                    <!-- SITE INFO -->
                                    <div>
                                        <div class="text-sm font-medium">
                                            {{ customer.site_name || '-' }}
                                        </div>

                                        <div
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            No Jaringan:
                                            {{ customer.no_jaringan || '-' }}
                                        </div>

                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            Dilaporkan melalui:
                                            {{ customer.reported_via || '-' }}
                                        </div>
                                    </div>

                                    <!-- INCIDENTS -->
                                    <div class="mt-4 space-y-3">
                                        <div
                                            v-for="incident in customer.incidents"
                                            :key="incident.id"
                                            class="rounded-md border bg-background p-4"
                                        >
                                            <!-- Incident Header -->
                                            <div
                                                class="mb-3 flex items-center justify-between"
                                            >
                                                <div class="font-medium">
                                                    Incident #{{
                                                        incident.incident_number
                                                    }}
                                                </div>

                                                <span
                                                    class="rounded-md bg-muted px-2 py-1 text-xs"
                                                >
                                                    {{ incident.category.name }}
                                                </span>
                                            </div>

                                            <!-- Incident Times -->
                                            <div
                                                class="grid gap-3 text-sm md:grid-cols-2"
                                            >
                                                <div>
                                                    <div
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        Reported
                                                    </div>

                                                    <div>
                                                        {{
                                                            formatDate(
                                                                incident.reported_at,
                                                            )
                                                        }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        Tidak ada customer/site.
                    </div>
                </div>

                <!-- =================================================
                     STOP CLOCK - TICKET LEVEL
                ================================================== -->
                <div class="rounded-lg border p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="font-semibold">Stop Clock</h2>

                            <div
                                v-if="hasActiveStopClock()"
                                class="mt-1 text-xs text-yellow-600 dark:text-yellow-400"
                            >
                                SLA sedang dihentikan
                            </div>

                            <div
                                v-else
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                SLA berjalan
                            </div>
                        </div>

                        <!-- Start Stop Clock -->
                        <button
                            v-if="
                                !hasActiveStopClock() &&
                                ticket.status === 'on_progress'
                            "
                            type="button"
                            class="rounded-md border px-3 py-2 text-sm hover:bg-muted"
                            @click="stopClockForm.reason = ''"
                        >
                            Stop Clock
                        </button>

                        <!-- Resume Stop Clock -->
                        <button
                            v-else-if="hasActiveStopClock()"
                            type="button"
                            class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground disabled:opacity-50"
                            :disabled="stopClockForm.processing"
                            @click="resumeStopClock"
                        >
                            {{
                                stopClockForm.processing
                                    ? 'Memproses...'
                                    : 'Resume Clock'
                            }}
                        </button>
                    </div>

                    <!-- =================================================
                         STOP CLOCK FORM
                    ================================================== -->
                    <div
                        v-if="
                            ticket.status === 'on_progress' &&
                            !hasActiveStopClock()
                        "
                        class="mt-3 rounded-md border bg-background p-4"
                    >
                        <form
                            class="space-y-3"
                            @submit.prevent="submitStopClock"
                        >
                            <div>
                                <label class="mb-2 block text-sm font-medium">
                                    Alasan Stop Clock
                                </label>

                                <textarea
                                    v-model="stopClockForm.reason"
                                    rows="3"
                                    class="w-full rounded-md border px-3 py-2 text-sm"
                                    placeholder="Contoh: Menunggu customer melakukan pengecekan power CPE..."
                                ></textarea>

                                <p
                                    v-if="stopClockForm.errors.reason"
                                    class="mt-1 text-sm text-destructive"
                                >
                                    {{ stopClockForm.errors.reason }}
                                </p>
                            </div>

                            <div class="flex justify-end gap-2">
                                <button
                                    type="button"
                                    class="rounded-md border px-3 py-2 text-sm hover:bg-muted"
                                    :disabled="stopClockForm.processing"
                                    @click="cancelStopClockForm"
                                >
                                    Batal
                                </button>

                                <button
                                    type="submit"
                                    class="rounded-md bg-yellow-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                                    :disabled="
                                        stopClockForm.processing ||
                                        !stopClockForm.reason.trim()
                                    "
                                >
                                    {{
                                        stopClockForm.processing
                                            ? 'Menyimpan...'
                                            : 'Mulai Stop Clock'
                                    }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- =================================================
                         STOP CLOCK HISTORY
                    ================================================== -->
                    <div
                        v-if="ticket.stop_clocks.length"
                        class="mt-4 space-y-2"
                    >
                        <div class="text-xs font-medium text-muted-foreground">
                            Riwayat Stop Clock
                        </div>

                        <div
                            v-for="stopClock in ticket.stop_clocks"
                            :key="stopClock.id"
                            class="rounded-md border bg-background p-3 text-xs"
                        >
                            <!-- Time -->
                            <div class="grid gap-2 sm:grid-cols-2">
                                <div>
                                    <span class="text-muted-foreground">
                                        Mulai:
                                    </span>

                                    <span class="ml-1">
                                        {{ formatDate(stopClock.started_at) }}
                                    </span>
                                </div>

                                <div>
                                    <span class="text-muted-foreground">
                                        Selesai:
                                    </span>

                                    <span class="ml-1">
                                        {{
                                            stopClock.ended_at
                                                ? formatDate(stopClock.ended_at)
                                                : '-'
                                        }}
                                    </span>
                                </div>
                            </div>

                            <!-- Reason -->
                            <div class="mt-2">
                                <span class="text-muted-foreground">
                                    Reason:
                                </span>

                                <span class="ml-1">
                                    {{ stopClock.reason }}
                                </span>
                            </div>

                            <!-- Active Indicator -->
                            <div
                                v-if="stopClock.ended_at === null"
                                class="mt-2 font-medium text-yellow-600 dark:text-yellow-400"
                            >
                                ⏸ Sedang aktif
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="mt-4 py-4 text-center text-sm text-muted-foreground"
                    >
                        Belum ada riwayat Stop Clock.
                    </div>
                </div>

                <!-- =================================================
                     RFO
                ================================================== -->
                <div class="rounded-lg border p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-semibold">RFO</h2>

                        <span class="text-xs text-muted-foreground">
                            {{ ticket.rfos.length }} RFO
                        </span>
                    </div>

                    <div v-if="ticket.rfos.length" class="space-y-3">
                        <div
                            v-for="rfo in ticket.rfos"
                            :key="rfo.id"
                            class="rounded-md border p-4"
                        >
                            <div class="flex items-center justify-between">
                                <div class="font-medium">
                                    {{ rfo.rfo_number }}
                                </div>

                                <div class="text-xs text-muted-foreground">
                                    {{ formatDate(rfo.created_at) }}
                                </div>
                            </div>

                            <div class="mt-3 text-sm whitespace-pre-wrap">
                                {{ rfo.content }}
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        Belum ada RFO.
                    </div>
                </div>

                <!-- =================================================
                     ACTIVITY / PROGRESS
                ================================================== -->
                <div class="rounded-lg border p-5">
                    <h2 class="mb-5 font-semibold">Activity / Progress</h2>

                    <div v-if="ticket.updates.length" class="space-y-5">
                        <div
                            v-for="update in ticket.updates"
                            :key="update.id"
                            class="relative border-l pl-5"
                        >
                            <!-- Timeline Dot -->
                            <div
                                class="absolute top-1 -left-1.5 h-3 w-3 rounded-full bg-primary"
                            ></div>

                            <!-- Update Header -->
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <div class="font-medium">
                                    {{ update.user.name }}
                                </div>

                                <div class="text-xs text-muted-foreground">
                                    {{ formatDate(update.created_at) }}
                                </div>
                            </div>

                            <!-- Message -->
                            <div class="mt-2 text-sm whitespace-pre-wrap">
                                {{ update.message }}
                            </div>

                            <!-- Attachments -->
                            <div
                                v-if="update.attachments.length"
                                class="mt-3 space-y-2"
                            >
                                <div
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    Attachment
                                </div>

                                <div
                                    v-for="attachment in update.attachments"
                                    :key="attachment.id"
                                    class="flex items-center justify-between rounded-md border p-2 text-sm"
                                >
                                    <a
                                        :href="`/storage/${attachment.file_path}`"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="truncate hover:underline"
                                    >
                                        {{ attachment.file_name }}
                                    </a>

                                    <span
                                        v-if="attachment.file_size"
                                        class="ml-3 shrink-0 text-xs text-muted-foreground"
                                    >
                                        {{
                                            (
                                                attachment.file_size /
                                                1024 /
                                                1024
                                            ).toFixed(2)
                                        }}
                                        MB
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        Belum ada aktivitas.
                    </div>
                </div>
            </div>

            <!-- =================================================
                 RIGHT SIDEBAR
            ================================================== -->
            <div class="space-y-6">
                <!-- =================================================
                     TICKET INFORMATION
                ================================================== -->
                <div class="rounded-lg border p-5">
                    <h2 class="mb-4 font-semibold">Informasi Ticket</h2>

                    <div class="space-y-4 text-sm">
                        <!-- Created By -->
                        <div>
                            <div class="text-xs text-muted-foreground">
                                Dibuat oleh
                            </div>

                            <div>
                                {{ ticket.creator?.name || '-' }}
                            </div>
                        </div>

                        <!-- Created -->
                        <div>
                            <div class="text-xs text-muted-foreground">
                                Dibuat
                            </div>

                            <div>
                                {{ formatDate(ticket.created_at) }}
                            </div>
                        </div>

                        <!-- Reported -->
                        <div>
                            <div class="text-xs text-muted-foreground">
                                Reported
                            </div>

                            <div>
                                {{ formatDate(ticket.reported_at) }}
                            </div>
                        </div>

                        <!-- First Response -->
                        <div>
                            <div class="text-xs text-muted-foreground">
                                First Response
                            </div>

                            <div>
                                {{ formatDate(ticket.first_response_at) }}
                            </div>
                        </div>

                        <!-- Downtime -->
                        <div>
                            <div class="text-xs text-muted-foreground">
                                Downtime
                            </div>

                            <div>
                                {{ formatDowntime(ticket.downtime_minutes) }}
                            </div>
                        </div>

                        <!-- Resolved -->
                        <div>
                            <div class="text-xs text-muted-foreground">
                                Resolved
                            </div>

                            <div>
                                {{ formatDate(ticket.resolved_at) }}
                            </div>
                        </div>

                        <!-- Closed -->
                        <div>
                            <div class="text-xs text-muted-foreground">
                                Closed
                            </div>

                            <div>
                                {{ formatDate(ticket.closed_at) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- =================================================
                     ADD UPDATE
                ================================================== -->
                <div class="rounded-lg border p-5">
                    <h2 class="mb-4 font-semibold">Tambah Progress</h2>

                    <form class="space-y-4" @submit.prevent="submitUpdate">
                        <!-- Message -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Progress / Catatan
                            </label>

                            <textarea
                                v-model="updateForm.message"
                                rows="4"
                                class="w-full rounded-md border px-3 py-2"
                                placeholder="Contoh: Dilakukan pengecekan CPE, ditemukan LOS..."
                            ></textarea>

                            <p
                                v-if="updateForm.errors.message"
                                class="mt-1 text-sm text-destructive"
                            >
                                {{ updateForm.errors.message }}
                            </p>
                        </div>

                        <!-- Attachment -->
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Foto / Attachment
                            </label>

                            <input
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,application/pdf"
                                class="block w-full rounded-md border px-3 py-2 text-sm"
                                @change="handleFiles"
                            />

                            <p class="mt-1 text-xs text-muted-foreground">
                                Bisa memilih beberapa foto sekaligus. Maksimal 5
                                MB per file.
                            </p>

                            <p
                                v-if="updateForm.errors.attachments"
                                class="mt-1 text-sm text-destructive"
                            >
                                {{ updateForm.errors.attachments }}
                            </p>
                        </div>

                        <!-- Selected Files -->
                        <div
                            v-if="updateForm.attachments.length"
                            class="space-y-2"
                        >
                            <div class="text-sm font-medium">
                                File yang dipilih
                            </div>

                            <div
                                v-for="file in updateForm.attachments"
                                :key="file.name + file.size"
                                class="flex items-center justify-between rounded-md border p-2 text-sm"
                            >
                                <span class="truncate">
                                    {{ file.name }}
                                </span>

                                <span
                                    class="ml-3 shrink-0 text-xs text-muted-foreground"
                                >
                                    {{ (file.size / 1024 / 1024).toFixed(2) }}
                                    MB
                                </span>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="flex justify-end">
                            <button
                                type="submit"
                                class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
                                :disabled="
                                    updateForm.processing ||
                                    !updateForm.message.trim()
                                "
                            >
                                {{
                                    updateForm.processing
                                        ? 'Menyimpan...'
                                        : 'Tambah Progress'
                                }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- =================================================
                     ACTIONS
                ================================================== -->
                <div class="rounded-lg border p-5">
                    <h2 class="mb-4 font-semibold">Action</h2>

                    <div class="space-y-2">
                        <!-- Resolve -->
                        <button
                            v-if="ticket.status === 'on_progress'"
                            type="button"
                            class="w-full rounded-md bg-green-600 px-4 py-2 text-sm text-white disabled:opacity-50"
                            @click="resolveTicket"
                        >
                            Resolve Ticket
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
