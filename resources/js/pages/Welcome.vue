<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    CircleCheck,
    Eye,
    Gauge,
    Lock,
    Ticket,
    Timer,
} from '@lucide/vue';

import { dashboard, login } from '@/routes';

const features = [
    {
        title: 'Tiket',
        description:
            'Catat tiket per customer dan site, lengkap dengan jenis, kendala, dan prioritas.',
        icon: Ticket,
    },
    {
        title: 'SLA',
        description:
            'Pantau umur tiket dan sisa waktu penyelesaian agar tidak melewati batas.',
        icon: Timer,
    },
    {
        title: 'KPI',
        description:
            'Pantau kinerja tim, seperti waktu respons dan tingkat penyelesaian.',
        icon: Gauge,
        soon: true,
    },
];

// Contoh data pratinjau, mengikuti tampilan dashboard dan daftar tiket.
const totalTickets = 128;

const stats = [
    {
        label: 'Open',
        value: 72,
        icon: Activity,
        tile: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
        bar: 'bg-blue-500',
    },
    {
        label: 'On Progress',
        value: 13,
        icon: Activity,
        tile: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        bar: 'bg-amber-500',
    },
    {
        label: 'Resolved',
        value: 32,
        icon: CircleCheck,
        tile: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        bar: 'bg-emerald-500',
    },
    {
        label: 'Closed',
        value: 11,
        icon: Lock,
        tile: 'bg-slate-500/10 text-slate-600 dark:text-slate-400',
        bar: 'bg-slate-400',
    },
].map((s) => ({ ...s, pct: Math.round((s.value / totalTickets) * 100) }));

const recentTickets = [
    {
        no: '141026.012',
        customer: 'PT. Aplikanusa Lintasarta',
        site: 'Nusantara Ekspress Kilat',
        kendala: 'Down',
        priority: 'Critical',
        status: 'Open',
        age: '18 jam 34 menit',
    },
    {
        no: '141026.013',
        customer: 'PT. Indosat',
        site: 'Paragon Ambon',
        kendala: 'Down',
        priority: 'Medium',
        status: 'Closed',
        age: '0 jam 13 menit',
    },
];

const priorityClass: Record<string, string> = {
    Critical: 'bg-red-500/15 text-red-700 dark:text-red-400',
    High: 'bg-orange-500/15 text-orange-700 dark:text-orange-400',
    Medium: 'bg-yellow-400/25 text-yellow-800 dark:text-yellow-300',
    Low: 'bg-slate-500/15 text-slate-600 dark:text-slate-300',
};

const statusClass: Record<string, string> = {
    Open: 'bg-blue-500/15 text-blue-700 dark:text-blue-300',
    'On Progress': 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    Resolved: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
    Closed: 'bg-slate-500/15 text-slate-600 dark:text-slate-300',
};

const year = new Date().getFullYear();
</script>

<template>
    <Head title="E-Ticketing">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div
        class="flex min-h-screen flex-col bg-stone-100 text-stone-900 dark:bg-neutral-950 dark:text-stone-100"
    >
        <!-- Header -->
        <header
            class="border-b border-dashed border-stone-300 dark:border-white/10"
        >
            <nav
                class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-6"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-8 items-center justify-center rounded-lg bg-amber-400 text-neutral-950"
                    >
                        <Ticket class="size-4.5" />
                    </span>
                    <span class="font-semibold tracking-tight"
                        >E-Ticketing</span
                    >
                </div>

                <Link
                    :href="$page.props.auth.user ? dashboard() : login()"
                    class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-medium text-neutral-950 transition-colors hover:bg-amber-300 focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:outline-none"
                >
                    {{ $page.props.auth.user ? 'Buka dashboard' : 'Masuk' }}
                </Link>
            </nav>
        </header>

        <main class="mx-auto flex w-full max-w-6xl flex-1 flex-col px-6">
            <!-- Hero -->
            <section
                class="grid items-center gap-14 py-14 lg:grid-cols-[1fr_1fr] lg:py-24"
            >
                <div>
                    <h1
                        class="max-w-xl text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl"
                    >
                        Tiket gangguan, SLA, dan KPI tim support dalam satu
                        tempat.
                    </h1>
                    <p
                        class="mt-6 max-w-md text-base leading-relaxed text-stone-600 dark:text-stone-400"
                    >
                        Alat kerja internal untuk menangani tiket gangguan,
                        menjaga target SLA, dan memantau kinerja tim.
                    </p>

                    <div class="mt-8">
                        <Link
                            :href="
                                $page.props.auth.user ? dashboard() : login()
                            "
                            class="inline-block rounded-xl bg-amber-400 px-6 py-3 text-sm font-semibold text-neutral-950 transition-all hover:bg-amber-300 focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 focus-visible:outline-none active:scale-[0.98] dark:focus-visible:ring-offset-neutral-950"
                        >
                            {{
                                $page.props.auth.user
                                    ? 'Buka dashboard'
                                    : 'Masuk ke akun'
                            }}
                        </Link>
                    </div>
                </div>

                <!-- Pratinjau tampilan aplikasi -->
                <div
                    class="flex justify-center lg:justify-end"
                    aria-hidden="true"
                >
                    <div
                        class="w-full max-w-lg space-y-4 transition-all duration-700 ease-out motion-reduce:transition-none starting:translate-y-6 starting:opacity-0"
                    >
                        <!-- Ringkasan status -->
                        <div
                            class="rounded-3xl bg-white p-5 shadow-xl ring-1 shadow-stone-900/10 ring-stone-900/5 dark:bg-neutral-900 dark:shadow-none dark:ring-white/10"
                        >
                            <div class="flex items-center justify-between">
                                <div
                                    class="flex items-center gap-2 font-semibold tracking-tight"
                                >
                                    <Ticket
                                        class="size-4.5 text-amber-600 dark:text-amber-400"
                                    />
                                    Ringkasan tiket
                                </div>
                                <span
                                    class="rounded-full bg-stone-100 px-2.5 py-0.5 text-xs font-medium text-stone-600 tabular-nums dark:bg-white/10 dark:text-stone-300"
                                >
                                    Total {{ totalTickets }}
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-3">
                                <div
                                    v-for="stat in stats"
                                    :key="stat.label"
                                    class="relative overflow-hidden rounded-2xl bg-stone-50 p-4 pb-5 ring-1 ring-stone-200 dark:bg-white/[0.03] dark:ring-white/10"
                                >
                                    <div
                                        class="flex items-start justify-between"
                                    >
                                        <p
                                            class="text-xs text-stone-500 dark:text-stone-400"
                                        >
                                            {{ stat.label }}
                                        </p>
                                        <span
                                            class="flex size-8 items-center justify-center rounded-lg"
                                            :class="stat.tile"
                                        >
                                            <component
                                                :is="stat.icon"
                                                class="size-4.5"
                                            />
                                        </span>
                                    </div>
                                    <p
                                        class="mt-1 text-2xl font-semibold tracking-tight tabular-nums"
                                    >
                                        {{ stat.value }}
                                    </p>
                                    <p
                                        class="text-xs text-stone-500 tabular-nums dark:text-stone-400"
                                    >
                                        {{ stat.pct }}% dari total
                                    </p>
                                    <div
                                        class="absolute inset-x-0 bottom-0 h-1 bg-stone-200 dark:bg-white/10"
                                    >
                                        <div
                                            class="h-full"
                                            :class="stat.bar"
                                            :style="{ width: stat.pct + '%' }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tiket terbaru -->
                        <div
                            class="rounded-3xl bg-white p-5 shadow-xl ring-1 shadow-stone-900/10 ring-stone-900/5 dark:bg-neutral-900 dark:shadow-none dark:ring-white/10"
                        >
                            <div
                                class="flex items-center gap-2 font-semibold tracking-tight"
                            >
                                <Timer
                                    class="size-4.5 text-amber-600 dark:text-amber-400"
                                />
                                Tiket terbaru
                            </div>

                            <ul
                                class="mt-4 divide-y divide-dashed divide-stone-200 dark:divide-white/10"
                            >
                                <li
                                    v-for="t in recentTickets"
                                    :key="t.no"
                                    class="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="text-sm font-semibold tabular-nums"
                                        >
                                            {{ t.no }}
                                        </p>
                                        <p
                                            class="truncate text-xs text-stone-500 dark:text-stone-400"
                                        >
                                            {{ t.customer }}, {{ t.site }}
                                        </p>
                                        <div
                                            class="mt-2 flex flex-wrap items-center gap-1.5"
                                        >
                                            <span
                                                class="rounded-md border border-stone-200 px-2 py-0.5 text-xs font-medium dark:border-white/15"
                                            >
                                                {{ t.kendala }}
                                            </span>
                                            <span
                                                class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                                :class="
                                                    priorityClass[t.priority]
                                                "
                                            >
                                                {{ t.priority }}
                                            </span>
                                            <span
                                                class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                                :class="statusClass[t.status]"
                                            >
                                                {{ t.status }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="hidden text-right sm:block">
                                        <p
                                            class="text-xs text-stone-500 dark:text-stone-400"
                                        >
                                            Umur tiket
                                        </p>
                                        <p
                                            class="text-xs font-medium tabular-nums"
                                        >
                                            {{ t.age }}
                                        </p>
                                    </div>

                                    <span
                                        class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-stone-100 text-stone-600 dark:bg-white/10 dark:text-stone-300"
                                    >
                                        <Eye class="size-4" />
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Fitur -->
            <section
                class="grid border-t border-dashed border-stone-300 sm:grid-cols-3 dark:border-white/10"
            >
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="py-8 sm:border-l sm:border-dashed sm:border-stone-300 sm:px-8 sm:first:border-l-0 sm:first:pl-0 dark:sm:border-white/10"
                >
                    <component
                        :is="feature.icon"
                        class="size-5 text-amber-600 dark:text-amber-400"
                    />
                    <h2
                        class="mt-4 flex items-center gap-2 font-semibold tracking-tight"
                    >
                        {{ feature.title }}
                    </h2>
                    <p
                        class="mt-1.5 max-w-xs text-sm leading-relaxed text-stone-600 dark:text-stone-400"
                    >
                        {{ feature.description }}
                    </p>
                </div>
            </section>
        </main>

        <footer
            class="mx-auto flex w-full max-w-6xl flex-wrap justify-between gap-2 px-6 py-6 text-sm text-stone-500"
        >
            <span>&copy; {{ year }} E-Ticketing @Rizal</span>
        </footer>
    </div>
</template>
