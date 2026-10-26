<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    Plus,
    Ticket,
    TriangleAlert,
    Undo2,
    Settings2,
    FlaskConical,
    CalendarDays,
    History,
} from '@lucide/vue';
import { computed } from 'vue';

import AppLogo from '@/components/AppLogo.vue';
import NavUser from '@/components/NavUser.vue';

import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';

import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

type MenuItem = NavItem & {
    /** Opsional: tampilkan angka kecil di kanan menu (mis. jumlah tiket baru) */
    badge?: string | number;
    /** true = hanya aktif jika URL persis sama (dipakai untuk Dashboard) */
    exact?: boolean;
};

type MenuGroup = {
    label: string;
    items: MenuItem[];
};

const menuGroups: MenuGroup[] = [
    {
        label: 'Platform',
        items: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutDashboard,
                exact: true,
            },
        ],
    },
    {
        label: 'Layanan',
        items: [
            {
                title: 'Ticket',
                href: '/tickets',
                icon: Ticket,
            },
        ],
    },
    {
        label: 'Manajemen SLA',
        items: [
            {
                title: 'Pengaturan Target SLA',
                href: '/sla-rules',
                icon: Settings2,
            },
            // {
            //     title: 'SLA Test',
            //     href: '/admin/sla-test',
            //     icon: FlaskConical,
            // },
            {
                title: 'Laporan SLA Bulanan',
                href: '/sla-reports',
                icon: CalendarDays,
                exact: true,
            },
            {
                title: 'Riwayat Laporan SLA',
                href: '/sla-reports/history',
                icon: History,
            },
        ],
    },
    {
        label: 'Master data',
        items: [
            {
                title: 'Kendala',
                href: '/master/kendala',
                icon: TriangleAlert,
            },
        ],
    },
];
const page = usePage();

// href bisa berupa string atau objek Wayfinder ({ url, method })
const toPath = (href: MenuItem['href']): string => {
    const raw = typeof href === 'string' ? href : href.url;
    const path = raw.split('?')[0];

    return path.length > 1 ? path.replace(/\/+$/, '') : path;
};

const currentPath = computed(() => toPath(page.url));

const isActive = (item: MenuItem): boolean => {
    const target = toPath(item.href);

    if (item.exact) {
        return currentPath.value === target;
    }

    return (
        currentPath.value === target ||
        currentPath.value.startsWith(`${target}/`)
    );
};
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="pb-1">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="rounded-xl transition-colors hover:bg-sidebar-accent"
                    >
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="overflow-x-hidden">
            <!-- Aksi utama: membuat tiket baru -->

            <template v-for="(group, index) in menuGroups" :key="group.label">
                <!-- Garis putus-putus seperti perforasi tiket -->
                <SidebarSeparator
                    v-if="index > 0"
                    class="mx-4 my-1 w-auto border-t border-dashed border-sidebar-border bg-transparent"
                />

                <SidebarGroup>
                    <SidebarGroupLabel
                        class="px-3 text-xs font-medium text-sidebar-foreground/50"
                    >
                        {{ group.label }}
                    </SidebarGroupLabel>

                    <SidebarGroupContent>
                        <SidebarMenu class="gap-1">
                            <SidebarMenuItem
                                v-for="item in group.items"
                                :key="item.title"
                                class="relative"
                            >
                                <!-- Penanda menu aktif -->
                                <span
                                    v-if="isActive(item)"
                                    aria-hidden="true"
                                    class="absolute top-1/2 -left-2 h-5 w-1 -translate-y-1/2 rounded-r-full bg-amber-500 group-data-[collapsible=icon]:hidden"
                                />

                                <SidebarMenuButton
                                    as-child
                                    :is-active="isActive(item)"
                                    :tooltip="item.title"
                                    class="h-10 rounded-xl px-3 text-sidebar-foreground/70 transition-colors duration-150 hover:bg-sidebar-accent hover:text-sidebar-foreground focus-visible:ring-2 focus-visible:ring-amber-500/60 data-[active=true]:bg-sidebar-accent data-[active=true]:font-medium data-[active=true]:text-sidebar-foreground [&>svg]:transition-colors data-[active=true]:[&>svg]:text-amber-600 dark:data-[active=true]:[&>svg]:text-amber-400"
                                >
                                    <Link :href="item.href">
                                        <component :is="item.icon" />
                                        <span>{{ item.title }}</span>
                                    </Link>
                                </SidebarMenuButton>

                                <SidebarMenuBadge
                                    v-if="item.badge"
                                    class="rounded-full bg-amber-500/15 px-1.5 text-amber-700 dark:text-amber-300"
                                >
                                    {{ item.badge }}
                                </SidebarMenuBadge>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </template>
        </SidebarContent>

        <SidebarFooter>
            <SidebarSeparator
                class="mx-2 w-auto border-t border-dashed border-sidebar-border bg-transparent"
            />
            <NavUser />
        </SidebarFooter>
    </Sidebar>

    <slot />
</template>
