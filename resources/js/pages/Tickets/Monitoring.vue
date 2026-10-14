<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import { Activity, CircleCheck, Lock, TriangleAlert } from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
/* ================================================================== */
/* TYPES                                                               */
/* ================================================================== */
type Priority = 'low' | 'medium' | 'high' | 'critical';
type TicketStatus = 'open' | 'on_progress' | 'resolved' | 'closed';
type SlaStatus = 'running' | 'stop_clock' | 'resolved' | 'not_applicable';
interface UserRef {
    id: number;
    name: string;
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
interface CustomerInfo {
    id: number;
    customer_name: string;
    site_name: string;
    no_jaringan: string | null;
}
interface LatestUpdate {
    id: number;
    user: UserRef | null;
}
interface Ticket {
    id: number;
    ticket_number: string;
    ticket_type: 'individual' | 'gamas';
    priority: Priority;
    current_priority: Priority;
    status: TicketStatus;
    description: string | null;
    reported_at: string;
    sla_timer_seconds: number;
    /** Waktu server saat nilai dasar SLA dihitung (ISO 8601). */
    sla_timer_calculated_at?: string | null;
    sla_timer_running: boolean;
    sla_timer_status: SlaStatus;
    ticket_duration_seconds: number;
    customers: CustomerInfo[];
    creator: UserRef | null;
    latest_update: LatestUpdate | null;
    latest_incident: LatestIncident | null;
}
interface TicketCreatedEvent {
    ticketId: number;
    ticketNumber: string;
    ticketType: 'individual' | 'gamas';
    priority: Priority;
    currentPriority: Priority;
    status: TicketStatus;
    reportedAt: string;
    slaTimerSeconds: number;
    slaTimerCalculatedAt?: string | null;
    slaTimerRunning: boolean;
    slaTimerStatus: 'running' | 'stop_clock' | 'not_applicable';
    ticketDurationSeconds: number;
    creator: UserRef | null;
    latestUpdate: LatestUpdate | null;
    customers: CustomerInfo[];
    latestIncident: LatestIncident | null;
}
interface TicketUpdatedEvent {
    ticketId: number;
    status: TicketStatus;
    currentPriority: Priority;
    latestUpdate: LatestUpdate | null;
}
interface StopClockEvent {
    ticketId: number;
    slaTimerSeconds: number;
    slaTimerCalculatedAt?: string | null;
    slaTimerRunning: boolean;
}
interface TicketResolvedEvent {
    ticketId: number;
    status: TicketStatus;
    currentPriority: Priority;
    resolvedAt: string | null;
    slaTimerSeconds: number;
    slaTimerCalculatedAt?: string | null;
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
    priority: Priority;
    currentPriority: Priority;
    status: TicketStatus;
    reportedAt: string;
    slaTimerSeconds: number;
    slaTimerCalculatedAt?: string | null;
    slaTimerRunning: boolean;
    slaTimerStatus: SlaStatus;
    ticketDurationSeconds: number;
    creator: UserRef | null;
    latestUpdate: LatestUpdate | null;
    customers: CustomerInfo[];
    latestIncident: LatestIncident | null;
}
interface TicketEditedEvent {
    ticketId: number;
    ticketNumber: string;
    ticketType: 'individual' | 'gamas';
    priority: Priority;
    currentPriority: Priority;
    status: TicketStatus;
    description: string | null;
    reportedAt: string;
    customers: CustomerInfo[];
    latestIncident: {
        id: number;
        incident_number: number;
        kendala_id: number;
        reported_at: string;
        resolved_at: string | null;
        category: Kendala | null;
    } | null;
    slaTimerSeconds: number;
    slaTimerCalculatedAt?: string | null;
    slaTimerRunning: boolean;
    slaTimerStatus: SlaStatus;
    ticketDurationSeconds: number;
    creator: UserRef | null;
    latestUpdate: LatestUpdate | null;
}
interface TicketDeletedEvent {
    ticketId: number;
}
interface TicketStats {
    active: number;
    resolved_today: number;
    closed_today: number;
    critical_active: number;
}
const props = defineProps<{
    tickets: Ticket[];
    ticketStats: TicketStats;
}>();
/**
 * Data lokal untuk monitoring realtime.
 *
 * props.tickets : data awal dari Laravel saat halaman dibuka.
 * tickets       : data lokal yang berubah ketika menerima event Reverb.
 */
type MonitoringTicket = Ticket & {
    sla_timer_anchor_at: number;
};

const resolveSlaAnchor = (calculatedAt?: string | null): number => {
    if (calculatedAt) {
        const parsed = Date.parse(calculatedAt);
        if (Number.isFinite(parsed)) {
            // Basis SLA disimpan dalam detik penuh; anchor juga harus pada batas detik.
            return Math.floor(parsed / 1000) * 1000;
        }
    }
    // Fallback jika payload backend/event belum menyertakan waktu acuan.
    return Math.floor(Date.now() / 1000) * 1000;
};

const toMonitoringTicket = (ticket: Ticket): MonitoringTicket => ({
    ...ticket,
    sla_timer_anchor_at: resolveSlaAnchor(ticket.sla_timer_calculated_at),
});

const tickets = ref<MonitoringTicket[]>(props.tickets.map(toMonitoringTicket));
/**
 * Counter harian untuk kartu "Resolved Hari Ini" & "Closed Hari Ini".
 * Nilai awal dari props.ticketStats (sama dengan halaman index),
 * lalu naik/turun mengikuti event realtime.
 *
 * "Ticket Berjalan" dan "Critical Aktif" dihitung langsung dari
 * daftar ticket lokal (stats.all & stats.critical), jadi ikut
 * berubah realtime termasuk eskalasi prioritas.
 */
const resolvedToday = ref(props.ticketStats?.resolved_today ?? 0);
const closedToday = ref(props.ticketStats?.closed_today ?? 0);
/* ================================================================== */
/* FORMATTER                                                           */
/* ================================================================== */
/** 08 Okt 2026 • 10:44 WIB */
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
/** 00:00:00 */
const formatSlaTimer = (seconds: number) => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;
    return [hours, minutes, remainingSeconds]
        .map((value) => String(value).padStart(2, '0'))
        .join(':');
};
/** 00:25:10 */
const formatTicketDuration = (seconds: number) => {
    const totalSeconds = Math.floor(seconds);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const remainingSeconds = totalSeconds % 60;
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
};
/* ================================================================== */
/* TIMER LOKAL (setiap detik)                                          */
/*                                                                     */
/* Ticket Duration : terus berjalan sampai CLOSED                      */
/* SLA Timer       : hanya berjalan jika status SLA = running          */
/* ================================================================== */
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
/** Jam di header */
const now = ref(Date.now());
const updateTimers = () => {
    now.value = Date.now();
    tickets.value.forEach((ticket) => {
        // Durasi ticket tetap berjalan sampai ticket ditutup.
        if (ticket.status !== 'closed') {
            ticket.ticket_duration_seconds++;
        }
        // Nilai dasar SLA tidak di-increment di sini.
        // SLA ditampilkan dari nilai dasar + waktu sejak sla_timer_anchor_at.
        ticket.current_priority = calculateCurrentPriority(ticket);
    });
};

const getSlaDisplaySeconds = (ticket: MonitoringTicket): number => {
    // Ketergantungan reaktif ini membuat nilai dihitung ulang setiap timer tick.
    const currentTime = now.value;
    const baseSeconds = Math.max(0, Math.floor(ticket.sla_timer_seconds || 0));
    if (ticket.sla_timer_status !== 'running') return baseSeconds;
    const elapsedSeconds = Math.max(
        0,
        Math.floor((currentTime - ticket.sla_timer_anchor_at) / 1000),
    );
    return baseSeconds + elapsedSeconds;
};

const applySlaTimer = (
    ticket: MonitoringTicket,
    seconds: number,
    status: SlaStatus,
    calculatedAt?: string | null,
    running?: boolean,
) => {
    ticket.sla_timer_seconds = Math.max(0, Math.floor(seconds || 0));
    ticket.sla_timer_status = status;
    ticket.sla_timer_running = running ?? status === 'running';
    ticket.sla_timer_calculated_at = calculatedAt ?? null;
    ticket.sla_timer_anchor_at = resolveSlaAnchor(calculatedAt);
    now.value = Date.now();
};
const timerInterval = setInterval(() => {
    updateTimers();
}, 1000);

const clockTime = computed(() => {
    const date = new Date(now.value);
    return date.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
        timeZone: 'Asia/Jakarta',
    });
});
const clockDate = computed(() =>
    new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(now.value)),
);
/* ================================================================== */
/* FILTER & PENCARIAN                                                  */
/* ================================================================== */
type QuickFilter =
    'all' | 'open' | 'on_progress' | 'resolved' | 'critical' | 'stop_clock';
const quickFilter = ref<QuickFilter>('all');
const search = ref('');
const stats = computed(() => {
    const list = tickets.value;
    return {
        all: list.length,
        open: list.filter((t) => t.status === 'open').length,
        on_progress: list.filter((t) => t.status === 'on_progress').length,
        resolved: list.filter((t) => t.status === 'resolved').length,
        critical: list.filter((t) => t.current_priority === 'critical').length,
        stop_clock: list.filter((t) => t.sla_timer_status === 'stop_clock')
            .length,
    };
});
const filterChips: { key: QuickFilter; label: string }[] = [
    { key: 'all', label: 'Semua' },
    { key: 'open', label: 'Open' },
    { key: 'on_progress', label: 'On progress' },
    { key: 'resolved', label: 'Resolved' },
    { key: 'critical', label: 'Critical' },
    { key: 'stop_clock', label: 'Stop clock' },
];
function toggleQuickFilter(key: QuickFilter) {
    quickFilter.value = quickFilter.value === key ? 'all' : key;
}
const filteredTickets = computed(() => {
    const q = search.value.trim().toLowerCase();
    return tickets.value.filter((t) => {
        switch (quickFilter.value) {
            case 'open':
            case 'on_progress':
            case 'resolved':
                if (t.status !== quickFilter.value) return false;
                break;
            case 'critical':
                if (t.current_priority !== 'critical') return false;
                break;
            case 'stop_clock':
                if (t.sla_timer_status !== 'stop_clock') return false;
                break;
        }
        if (!q) return true;
        const haystack = [
            t.ticket_number,
            t.ticket_type,
            t.latest_incident?.category?.name,
            t.creator?.name,
            t.latest_update?.user?.name,
            ...t.customers.flatMap((c) => [
                c.customer_name,
                c.site_name,
                c.no_jaringan,
            ]),
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();
        return haystack.includes(q);
    });
});
/* ================================================================== */
/* AUTO HIGHLIGHT                                                      */
/*                                                                     */
/* Sorotan dilacak lewat ID ticket (bukan nomor baris), sehingga tidak */
/* "loncat" ketika ada ticket baru / ticket ditutup secara realtime.   */
/* ================================================================== */
const autoMode = ref(true);
const manualPaused = ref(false);
const hovering = ref(false);
const intervalSec = ref(6);
const intervalOptions = [4, 6, 10, 15];
const activeId = ref<number | null>(null);
const progressKey = ref(0);
const activeIndex = computed(() => {
    const i = filteredTickets.value.findIndex((t) => t.id === activeId.value);
    return i === -1 ? 0 : i;
});
const activeTicket = computed(
    () => filteredTickets.value[activeIndex.value] ?? null,
);
const paused = computed(() => manualPaused.value || hovering.value);
const rotating = computed(
    () => autoMode.value && !paused.value && filteredTickets.value.length > 1,
);
const scroller = ref<HTMLElement | null>(null);
const rowEls = new Map<number, HTMLElement>();
let rotateTimer: ReturnType<typeof setInterval> | null = null;
function setRowRef(id: number, el: unknown) {
    if (el) rowEls.set(id, el as HTMLElement);
    else rowEls.delete(id);
}
function stopRotate() {
    if (rotateTimer) clearInterval(rotateTimer);
    rotateTimer = null;
}
function startRotate() {
    stopRotate();
    progressKey.value++;
    if (!rotating.value) return;
    rotateTimer = setInterval(next, intervalSec.value * 1000);
}
function goTo(index: number) {
    const list = filteredTickets.value;
    if (list.length === 0) return;
    const n = ((index % list.length) + list.length) % list.length;
    activeId.value = list[n].id;
    startRotate();
}
const next = () => goTo(activeIndex.value + 1);
const prev = () => goTo(activeIndex.value - 1);
function scrollToActive() {
    const ticket = activeTicket.value;
    const container = scroller.value;
    if (!ticket || !container) return;
    const row = rowEls.get(ticket.id);
    if (!row) return;
    const c = container.getBoundingClientRect();
    const r = row.getBoundingClientRect();
    const headerH = 44; // tinggi thead sticky (perkiraan)
    const target =
        r.top - c.top - (container.clientHeight - headerH) / 2 + r.height / 2;
    container.scrollBy({ top: target, behavior: 'smooth' });
}
watch(activeId, () => nextTick(scrollToActive));
// Filter / pencarian berubah → mulai lagi dari atas
watch([quickFilter, search], () => {
    activeId.value = null;
    nextTick(() => scroller.value?.scrollTo({ top: 0, behavior: 'smooth' }));
});
watch([autoMode, paused, intervalSec, () => filteredTickets.value.length], () =>
    startRotate(),
);
/**
 * Ticket baru / dibuka kembali:
 * - baris berkedip hijau selama ±8 detik
 * - sorotan langsung pindah ke ticket tersebut (jika tidak sedang difilter)
 */
const freshIds = ref<number[]>([]);
function flashTicket(id: number) {
    freshIds.value.push(id);
    setTimeout(() => {
        freshIds.value = freshIds.value.filter((x) => x !== id);
    }, 8000);
    if (quickFilter.value === 'all' && !search.value.trim()) {
        activeId.value = id;
        startRotate();
    }
}
/* ================================================================== */
/* LAYAR PENUH & KEYBOARD                                              */
/* ================================================================== */
const isFullscreen = ref(false);
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen?.();
    } else {
        document.exitFullscreen?.();
    }
}
function onFullscreenChange() {
    isFullscreen.value = !!document.fullscreenElement;
}
function onKeydown(e: KeyboardEvent) {
    const tag = (e.target as HTMLElement)?.tagName;
    if (tag === 'INPUT' || tag === 'SELECT') return;
    if (e.code === 'Space') {
        e.preventDefault();
        manualPaused.value = !manualPaused.value;
    } else if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
        e.preventDefault();
        next();
    } else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
        e.preventDefault();
        prev();
    } else if (e.key.toLowerCase() === 'f') {
        toggleFullscreen();
    }
}
onMounted(() => {
    now.value = Date.now();
    document.addEventListener('fullscreenchange', onFullscreenChange);
    window.addEventListener('keydown', onKeydown);
    startRotate();
});
onBeforeUnmount(() => {
    clearInterval(timerInterval);
    stopRotate();
    document.removeEventListener('fullscreenchange', onFullscreenChange);
    window.removeEventListener('keydown', onKeydown);
});
/* ================================================================== */
/* STYLE MAP                                                           */
/* ================================================================== */
const priorityClass: Record<string, string> = {
    low: 'border-slate-500/20 bg-slate-500/10 text-slate-600 dark:text-slate-400',
    medium: 'border-blue-500/20 bg-blue-500/10 text-blue-600 dark:text-blue-400',
    high: 'border-orange-500/20 bg-orange-500/10 text-orange-600 dark:text-orange-400',
    critical: 'border-red-500/30 bg-red-500/15 text-red-600 dark:text-red-400',
};
const statusClass: Record<string, string> = {
    open: 'border-blue-500/20 bg-blue-500/10 text-blue-600 dark:text-blue-400',
    on_progress:
        'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400',
    resolved:
        'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    closed: 'border-slate-500/20 bg-slate-500/10 text-slate-600 dark:text-slate-400',
};
const slaBox: Record<string, string> = {
    running: 'border-emerald-500/30 bg-emerald-500/5',
    stop_clock: 'border-amber-500/30 bg-amber-500/5',
    resolved: 'border-slate-500/20 bg-slate-500/5',
    not_applicable: 'border-slate-500/20 bg-slate-500/5',
};
const slaText: Record<string, string> = {
    running: 'text-emerald-600 dark:text-emerald-400',
    stop_clock: 'text-amber-600 dark:text-amber-400',
    resolved: 'text-slate-500 dark:text-slate-400',
    not_applicable: 'text-slate-500 dark:text-slate-400',
};
const slaDot: Record<string, string> = {
    running: 'animate-pulse bg-emerald-500',
    stop_clock: 'bg-amber-500',
    resolved: 'bg-slate-400',
    not_applicable: 'bg-slate-400',
};
const slaLabel: Record<string, string> = {
    running: 'Running',
    stop_clock: 'Stop clock',
    resolved: 'Resolved',
    not_applicable: 'N/A',
};
/* ================================================================== */
/* REALTIME EVENTS (Reverb)                                            */
/* ================================================================== */
/** TICKET CREATED — ticket baru ditaruh paling atas. */
useEchoPublic(
    'ticket-monitor',
    'TicketCreated',
    (event: TicketCreatedEvent) => {
        console.log('🎫 MONITORING - TICKET CREATED:', event);
        // Cegah duplicate row jika event diterima lebih dari sekali.
        const alreadyExists = tickets.value.some(
            (ticket) => ticket.id === event.ticketId,
        );
        if (alreadyExists) {
            return;
        }
        const newTicketData: Ticket = {
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
            sla_timer_calculated_at: event.slaTimerCalculatedAt ?? null,
            sla_timer_running: event.slaTimerRunning,
            sla_timer_status: event.slaTimerStatus,
            ticket_duration_seconds: event.ticketDurationSeconds,
            creator: event.creator,
            latest_update: event.latestUpdate,
            latest_incident: event.latestIncident,
        };
        const newTicket = toMonitoringTicket(newTicketData);
        tickets.value.unshift(newTicket);
        flashTicket(newTicket.id);
    },
);
/** STOP CLOCK STARTED — SLA berhenti, durasi ticket tetap jalan. */
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
        applySlaTimer(
            ticket,
            event.slaTimerSeconds,
            'stop_clock',
            event.slaTimerCalculatedAt,
            false,
        );
    },
);
/** STOP CLOCK ENDED — SLA berjalan kembali. */
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
        applySlaTimer(
            ticket,
            event.slaTimerSeconds,
            'running',
            event.slaTimerCalculatedAt,
            true,
        );
    },
);
/** TICKET UPDATED */
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
        ticket.status = event.status;
        ticket.current_priority = event.currentPriority;
        ticket.latest_update = event.latestUpdate;
    },
);
/** TICKET RESOLVED — SLA berhenti. */
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
        if (ticket.status !== 'resolved') {
            resolvedToday.value++;
        }
        ticket.status = event.status;
        ticket.current_priority = event.currentPriority;
        applySlaTimer(
            ticket,
            event.slaTimerSeconds,
            event.slaTimerStatus,
            event.slaTimerCalculatedAt,
            false,
        );
    },
);
/** TICKET CLOSED — dihapus dari monitoring. */
useEchoPublic<TicketClosedEvent>('ticket-monitor', 'TicketClosed', (event) => {
    console.log('🔒 MONITORING - TICKET CLOSED:', event);
    if (tickets.value.some((ticket) => ticket.id === event.ticketId)) {
        closedToday.value++;
    }
    tickets.value = tickets.value.filter(
        (ticket) => ticket.id !== event.ticketId,
    );
});
/** TICKET REOPENED */
useEchoPublic<TicketReopenedEvent>(
    'ticket-monitor',
    'TicketReopened',
    (event) => {
        console.log('🔓 MONITORING - TICKET REOPENED:', event);
        const ticket = tickets.value.find(
            (ticket) => ticket.id === event.ticketId,
        );
        if (ticket) {
            if (ticket.status === 'resolved' && event.status !== 'resolved') {
                resolvedToday.value = Math.max(0, resolvedToday.value - 1);
            }
            ticket.status = event.status;
            ticket.current_priority = event.currentPriority;
            applySlaTimer(
                ticket,
                event.slaTimerSeconds,
                event.slaTimerStatus,
                event.slaTimerCalculatedAt,
                event.slaTimerRunning,
            );
            ticket.latest_update = event.latestUpdate;
            ticket.ticket_duration_seconds = event.ticketDurationSeconds;
            flashTicket(ticket.id);
            return;
        }
        // Ticket sebelumnya sudah dihapus dari Monitoring ketika Closed.
        const reopenedTicketData: Ticket = {
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
            sla_timer_calculated_at: event.slaTimerCalculatedAt ?? null,
            sla_timer_running: event.slaTimerRunning,
            sla_timer_status: event.slaTimerStatus,
            ticket_duration_seconds: event.ticketDurationSeconds,
            creator: event.creator,
            latest_update: event.latestUpdate,
            latest_incident: event.latestIncident,
        };
        const reopenedTicket = toMonitoringTicket(reopenedTicketData);
        // Sebelumnya Closed → kurangi counter closed hari ini.
        closedToday.value = Math.max(0, closedToday.value - 1);
        tickets.value.unshift(reopenedTicket);
        flashTicket(reopenedTicket.id);
    },
);
/** TICKET EDITED */
useEchoPublic<TicketEditedEvent>('ticket-monitor', '.TicketEdited', (event) => {
    console.log('TicketEdited diterima di Vue:', event);
    const index = tickets.value.findIndex(
        (ticket) => ticket.id === event.ticketId,
    );
    // Jika tiket tidak ada di tabel monitoring, tidak perlu diperbarui.
    if (index === -1) return;
    const existingTicket = tickets.value[index];
    tickets.value[index] = {
        ...existingTicket,
        ticket_number: event.ticketNumber,
        ticket_type: event.ticketType,
        priority: event.priority,
        current_priority: event.currentPriority,
        status: event.status,
        description: event.description,
        reported_at: event.reportedAt,
        customers: event.customers,
        latest_incident: event.latestIncident,
        sla_timer_seconds: event.slaTimerSeconds,
        sla_timer_calculated_at: event.slaTimerCalculatedAt ?? null,
        sla_timer_anchor_at: resolveSlaAnchor(event.slaTimerCalculatedAt),
        sla_timer_running: event.slaTimerRunning,
        sla_timer_status: event.slaTimerStatus,
        ticket_duration_seconds: event.ticketDurationSeconds,
        creator: event.creator,
        latest_update: event.latestUpdate,
    };
});
/** TICKET DELETED */
useEchoPublic<TicketDeletedEvent>(
    'ticket-monitor',
    '.TicketDeleted',
    (event) => {
        console.log('MONITORING - TICKET DELETED:', event);
        tickets.value = tickets.value.filter(
            (ticket) => ticket.id !== event.ticketId,
        );
    },
);
</script>
<template>
    <Head title="Ticket Monitoring" />
    <div
        class="flex h-screen flex-col gap-4 overflow-hidden bg-background p-4 text-foreground lg:p-6"
    >
        <!-- ============================================================ -->
        <!-- HEADER                                                       -->
        <!-- ============================================================ -->
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h1
                            class="text-2xl font-semibold tracking-tight 2xl:text-4xl"
                        >
                            Ticket Monitoring
                        </h1>
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
                    <p class="mt-1 text-sm text-muted-foreground 2xl:text-base">
                        Monitoring ticket dan SLA secara realtime.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <!-- Jam -->
                <div class="text-right">
                    <div class="mt-1 text-xl text-muted-foreground">
                        {{ clockDate }}
                    </div>
                </div>
                <Link
                    href="/dashboard"
                    class="rounded-lg border bg-card px-3 py-2 text-sm font-medium transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                >
                    Dashboard
                </Link>
            </div>
        </header>
        <!-- ============================================================ -->
        <!-- STAT CARDS (klik untuk filter)                               -->
        <!-- ============================================================ -->
        <section class="grid grid-cols-2 gap-3 xl:grid-cols-4">
            <!-- Ticket Berjalan -->
            <button
                type="button"
                class="flex items-center gap-4 rounded-xl border bg-card px-5 py-4 text-left transition-colors hover:border-blue-500/40 focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                title="Tampilkan semua ticket"
                @click="quickFilter = 'all'"
            >
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-500 2xl:h-14 2xl:w-14"
                >
                    <Activity class="h-6 w-6" />
                </div>
                <div>
                    <div class="text-sm text-muted-foreground 2xl:text-base">
                        Ticket Berjalan
                    </div>
                    <div
                        class="text-2xl font-semibold tabular-nums 2xl:text-4xl"
                    >
                        {{ stats.all }}
                    </div>
                </div>
            </button>
            <!-- Resolved Hari Ini -->
            <div
                class="flex items-center gap-4 rounded-xl border bg-card px-5 py-4"
            >
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500 2xl:h-14 2xl:w-14"
                >
                    <CircleCheck class="h-6 w-6" />
                </div>
                <div>
                    <div class="text-sm text-muted-foreground 2xl:text-base">
                        Resolved Hari Ini
                    </div>
                    <div
                        class="text-2xl font-semibold tabular-nums 2xl:text-4xl"
                    >
                        {{ resolvedToday }}
                    </div>
                </div>
            </div>
            <!-- Closed Hari Ini -->
            <div
                class="flex items-center gap-4 rounded-xl border bg-card px-5 py-4"
            >
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-500/10 text-slate-400 2xl:h-14 2xl:w-14"
                >
                    <Lock class="h-6 w-6" />
                </div>
                <div>
                    <div class="text-sm text-muted-foreground 2xl:text-base">
                        Closed Hari Ini
                    </div>
                    <div
                        class="text-2xl font-semibold tabular-nums 2xl:text-4xl"
                    >
                        {{ closedToday }}
                    </div>
                </div>
            </div>
            <!-- Critical Aktif -->
            <button
                type="button"
                class="flex items-center gap-4 rounded-xl border bg-card px-5 py-4 text-left transition-colors focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                :class="
                    quickFilter === 'critical'
                        ? 'border-red-500/60 bg-red-500/5'
                        : 'hover:border-red-500/40'
                "
                title="Tampilkan hanya ticket critical"
                @click="toggleQuickFilter('critical')"
            >
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-red-500/10 text-red-500 2xl:h-14 2xl:w-14"
                >
                    <TriangleAlert class="h-6 w-6" />
                </div>
                <div>
                    <div class="text-sm text-muted-foreground 2xl:text-base">
                        Critical Aktif
                    </div>
                    <div
                        class="text-2xl font-semibold tabular-nums 2xl:text-4xl"
                    >
                        {{ stats.critical }}
                    </div>
                </div>
            </button>
        </section>
        <!-- ============================================================ -->
        <!-- TABLE CARD                                                   -->
        <!-- ============================================================ -->
        <section
            class="relative flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border bg-card"
            @mouseenter="hovering = true"
            @mouseleave="hovering = false"
        >
            <!-- Progress bar auto-highlight -->
            <div class="absolute inset-x-0 top-0 z-20 h-0.5 bg-transparent">
                <div
                    v-if="rotating"
                    :key="progressKey"
                    class="progress-bar h-full origin-left bg-primary"
                    :style="{ animationDuration: `${intervalSec}s` }"
                ></div>
            </div>
            <!-- Toolbar -->
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b bg-muted/20 px-5 py-3"
            >
                <div class="flex items-center gap-3">
                    <div>
                        <h2 class="font-medium 2xl:text-xl">Active Ticket</h2>
                        <p class="text-xs text-muted-foreground 2xl:text-sm">
                            <template v-if="activeTicket">
                                Menyorot
                                <span class="font-medium text-foreground">{{
                                    activeTicket.ticket_number
                                }}</span>
                                ({{ activeIndex + 1 }} dari
                                {{ filteredTickets.length }})
                            </template>
                            <template v-else>Tidak ada ticket</template>
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Search -->
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Cari ticket, customer, site…"
                        class="h-9 w-56 rounded-lg border bg-background px-3 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-primary"
                    />
                    <!-- Auto highlight -->
                    <div
                        class="flex items-center gap-1 rounded-lg border bg-background p-1"
                    >
                        <button
                            type="button"
                            class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
                            :class="
                                autoMode
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-muted'
                            "
                            @click="autoMode = !autoMode"
                        >
                            Auto sorot {{ autoMode ? 'ON' : 'OFF' }}
                        </button>
                        <select
                            v-model.number="intervalSec"
                            class="rounded-md bg-transparent px-1.5 py-1 text-xs outline-none"
                            title="Durasi tiap sorotan"
                        >
                            <option
                                v-for="s in intervalOptions"
                                :key="s"
                                :value="s"
                            >
                                {{ s }} detik
                            </option>
                        </select>
                        <button
                            type="button"
                            class="rounded-md px-2 py-1 text-xs hover:bg-muted"
                            title="Sebelumnya (←)"
                            @click="prev"
                        >
                            ‹
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-2 py-1 text-xs font-medium hover:bg-muted"
                            title="Jeda / lanjut (Spasi)"
                            @click="manualPaused = !manualPaused"
                        >
                            {{ manualPaused ? 'Lanjut' : 'Jeda' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-2 py-1 text-xs hover:bg-muted"
                            title="Berikutnya (→)"
                            @click="next"
                        >
                            ›
                        </button>
                    </div>
                    <span
                        v-if="autoMode && paused"
                        class="rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-600 dark:text-amber-400"
                    >
                        Dijeda
                    </span>
                </div>
            </div>
            <!-- Filter status -->
            <div class="flex flex-wrap items-center gap-2 border-b px-5 py-2">
                <button
                    v-for="chip in filterChips"
                    :key="chip.key"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none 2xl:text-sm"
                    :class="
                        quickFilter === chip.key
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted'
                    "
                    @click="quickFilter = chip.key"
                >
                    {{ chip.label }}
                    <span class="tabular-nums opacity-80">{{
                        stats[chip.key]
                    }}</span>
                </button>
            </div>
            <!-- Scroll area -->
            <div
                ref="scroller"
                class="min-h-0 flex-1 overflow-auto"
                :class="autoMode ? 'scroll-hidden' : 'scroll-slim'"
            >
                <table class="w-full text-sm 2xl:text-base">
                    <thead class="sticky top-0 z-10">
                        <tr class="border-b bg-card">
                            <th
                                class="px-5 py-3 text-left text-xs font-medium whitespace-nowrap text-muted-foreground 2xl:text-sm"
                            >
                                Ticket
                            </th>
                            <th
                                class="px-5 py-3 text-left text-xs font-medium whitespace-nowrap text-muted-foreground 2xl:text-sm"
                            >
                                Customer / Site
                            </th>
                            <th
                                class="px-5 py-3 text-left text-xs font-medium whitespace-nowrap text-muted-foreground 2xl:text-sm"
                            >
                                Kendala
                            </th>
                            <th
                                class="px-5 py-3 text-left text-xs font-medium whitespace-nowrap text-muted-foreground 2xl:text-sm"
                            >
                                Priority
                            </th>
                            <th
                                class="px-5 py-3 text-left text-xs font-medium whitespace-nowrap text-muted-foreground 2xl:text-sm"
                            >
                                Status
                            </th>
                            <th
                                class="px-5 py-3 text-left text-xs font-medium whitespace-nowrap text-muted-foreground 2xl:text-sm"
                            >
                                Durasi Ticket
                            </th>
                            <th
                                class="px-5 py-3 text-left text-xs font-medium whitespace-nowrap text-muted-foreground 2xl:text-sm"
                            >
                                Dibuat Oleh
                            </th>
                            <th
                                class="px-5 py-3 text-left text-xs font-medium whitespace-nowrap text-muted-foreground 2xl:text-sm"
                            >
                                Handle Saat Ini
                            </th>
                            <th
                                class="px-5 py-3 text-left text-xs font-medium whitespace-nowrap text-muted-foreground 2xl:text-sm"
                            >
                                Dilaporkan
                            </th>
                            <th
                                class="px-5 py-3 text-left text-xs font-medium whitespace-nowrap text-muted-foreground 2xl:text-sm"
                            >
                                SLA Timer
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="(ticket, index) in filteredTickets"
                            :key="ticket.id"
                            :ref="(el) => setRowRef(ticket.id, el)"
                            class="relative cursor-pointer transition-colors duration-500"
                            :class="[
                                index === activeIndex
                                    ? 'bg-primary/10'
                                    : ticket.current_priority === 'critical'
                                      ? 'bg-red-500/[0.04] hover:bg-muted/30'
                                      : 'hover:bg-muted/30',
                                autoMode && index !== activeIndex
                                    ? 'opacity-75'
                                    : '',
                            ]"
                            :data-fresh="
                                freshIds.includes(ticket.id) ? 'true' : null
                            "
                            @click="goTo(index)"
                        >
                            <!-- Ticket -->
                            <td class="relative px-5 py-4 align-top">
                                <span
                                    v-if="index === activeIndex"
                                    class="absolute inset-y-0 left-0 w-1 bg-primary"
                                ></span>
                                <div class="font-semibold">
                                    {{ ticket.ticket_number }}
                                </div>
                                <div
                                    class="mt-1 text-xs text-muted-foreground capitalize 2xl:text-sm"
                                >
                                    {{ ticket.ticket_type }}
                                </div>
                            </td>
                            <!-- Customer / Site -->
                            <td class="px-5 py-4 align-top">
                                <div class="font-medium">
                                    {{
                                        ticket.customers[0]?.customer_name ??
                                        '-'
                                    }}
                                </div>
                                <div
                                    class="mt-1 text-xs text-muted-foreground 2xl:text-sm"
                                >
                                    {{ ticket.customers[0]?.site_name ?? '-' }}
                                </div>
                                <div
                                    v-if="ticket.customers[0]?.no_jaringan"
                                    class="mt-1 font-mono text-xs text-muted-foreground 2xl:text-sm"
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
                            <!-- Kendala -->
                            <td class="px-5 py-4 align-top">
                                <span
                                    class="inline-flex items-center rounded-md border border-border/70 bg-muted/40 px-2.5 py-1 text-xs font-medium text-foreground 2xl:text-sm"
                                >
                                    {{
                                        ticket.latest_incident?.category
                                            ?.name ?? '-'
                                    }}
                                </span>
                            </td>
                            <!-- Priority -->
                            <td class="px-5 py-4 align-top">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize 2xl:text-sm"
                                    :class="
                                        priorityClass[ticket.current_priority]
                                    "
                                >
                                    <span
                                        v-if="
                                            ticket.current_priority ===
                                            'critical'
                                        "
                                        class="h-1.5 w-1.5 animate-pulse rounded-full bg-current"
                                    ></span>
                                    {{ ticket.current_priority }}
                                </span>
                            </td>
                            <!-- Status -->
                            <td class="px-5 py-4 align-top">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold 2xl:text-sm"
                                    :class="statusClass[ticket.status]"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-current"
                                    ></span>
                                    <span class="capitalize">
                                        {{ ticket.status.replace('_', ' ') }}
                                    </span>
                                </span>
                            </td>
                            <!-- Durasi -->
                            <td class="px-5 py-4 align-top whitespace-nowrap">
                                <div
                                    class="font-mono font-semibold tabular-nums"
                                >
                                    {{
                                        formatTicketDuration(
                                            ticket.ticket_duration_seconds,
                                        )
                                    }}
                                </div>
                                <div
                                    class="mt-1 text-xs text-muted-foreground 2xl:text-sm"
                                >
                                    sejak dibuat
                                </div>
                            </td>
                            <!-- Dibuat oleh -->
                            <td class="px-5 py-4 align-top">
                                <div class="font-medium">
                                    {{ ticket.creator?.name ?? '-' }}
                                </div>
                                <div
                                    class="mt-1 text-xs text-muted-foreground 2xl:text-sm"
                                >
                                    Pembuat
                                </div>
                            </td>
                            <!-- Handle saat ini -->
                            <td class="px-5 py-4 align-top">
                                <div class="font-medium">
                                    {{
                                        ticket.latest_update?.user?.name ?? '-'
                                    }}
                                </div>
                                <div
                                    class="mt-1 text-xs text-muted-foreground 2xl:text-sm"
                                >
                                    {{
                                        ticket.latest_update
                                            ? 'Update terakhir'
                                            : 'Belum ada update'
                                    }}
                                </div>
                            </td>
                            <!-- Dilaporkan -->
                            <td
                                class="px-5 py-4 align-top whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDateTime(ticket.reported_at) }}
                            </td>
                            <!-- SLA -->
                            <td class="px-5 py-4 align-top">
                                <div
                                    class="inline-flex min-w-[130px] flex-col rounded-lg border px-3 py-2"
                                    :class="slaBox[ticket.sla_timer_status]"
                                >
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="h-2 w-2 rounded-full"
                                            :class="
                                                slaDot[ticket.sla_timer_status]
                                            "
                                        ></span>
                                        <span
                                            class="text-[11px] font-medium 2xl:text-xs"
                                            :class="
                                                slaText[ticket.sla_timer_status]
                                            "
                                        >
                                            {{
                                                slaLabel[
                                                    ticket.sla_timer_status
                                                ] ?? 'N/A'
                                            }}
                                        </span>
                                    </div>
                                    <div
                                        v-if="
                                            ticket.sla_timer_status !==
                                            'not_applicable'
                                        "
                                        class="mt-1 font-mono text-base font-semibold tabular-nums 2xl:text-xl"
                                    >
                                        {{
                                            formatSlaTimer(
                                                getSlaDisplaySeconds(ticket),
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
                        <!-- Empty state -->
                        <tr v-if="filteredTickets.length === 0">
                            <td colspan="10" class="px-5 py-16 text-center">
                                <div
                                    class="mx-auto flex max-w-sm flex-col items-center"
                                >
                                    <div
                                        class="flex h-12 w-12 items-center justify-center rounded-full bg-muted text-xl text-muted-foreground"
                                    >
                                        —
                                    </div>
                                    <p class="mt-4 font-medium">
                                        {{
                                            tickets.length === 0
                                                ? 'Tidak ada ticket yang sedang dimonitor'
                                                : 'Tidak ada ticket yang cocok'
                                        }}
                                    </p>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{
                                            tickets.length === 0
                                                ? 'Ticket baru akan muncul otomatis ketika diterima melalui realtime monitoring.'
                                                : 'Ubah filter atau kata pencarian untuk melihat ticket lain.'
                                        }}
                                    </p>
                                    <button
                                        v-if="tickets.length > 0"
                                        type="button"
                                        class="mt-4 rounded-lg border px-3 py-1.5 text-sm font-medium hover:bg-muted"
                                        @click="
                                            quickFilter = 'all';
                                            search = '';
                                        "
                                    >
                                        Reset filter
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Footer hint -->
            <div
                class="flex flex-wrap items-center justify-between gap-2 border-t bg-muted/20 px-5 py-2 text-xs text-muted-foreground"
            >
                <span>
                    Menampilkan {{ filteredTickets.length }} dari
                    {{ tickets.length }} ticket
                </span>
                <span class="hidden md:inline">
                    Spasi: jeda · ← →: pindah sorotan · F: layar penuh
                </span>
            </div>
        </section>
    </div>
</template>
<style scoped>
/* Auto sorot ON → scrollbar disembunyikan (tabel bergulir otomatis).
   Auto sorot OFF → scrollbar tipis agar tetap bisa digulung manual. */
.scroll-hidden {
    scrollbar-width: none;
}
.scroll-hidden::-webkit-scrollbar {
    display: none;
}
.scroll-slim {
    scrollbar-width: thin;
    scrollbar-color: rgb(120 120 120 / 0.45) transparent;
}
.scroll-slim::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.scroll-slim::-webkit-scrollbar-thumb {
    background: rgb(120 120 120 / 0.45);
    border-radius: 9999px;
}
.progress-bar {
    animation-name: ticket-progress;
    animation-timing-function: linear;
    animation-fill-mode: forwards;
}
@keyframes ticket-progress {
    from {
        transform: scaleX(0);
    }
    to {
        transform: scaleX(1);
    }
}
tr[data-fresh='true'] {
    animation: ticket-fresh 1.6s ease-out 3;
}
@keyframes ticket-fresh {
    0%,
    100% {
        background-color: transparent;
    }
    50% {
        background-color: rgb(16 185 129 / 0.25);
    }
}
@media (prefers-reduced-motion: reduce) {
    tr[data-fresh='true'] {
        animation: none;
    }
}
@media (prefers-reduced-motion: reduce) {
    .progress-bar {
        animation: none;
        transform: scaleX(1);
    }
}
</style>
