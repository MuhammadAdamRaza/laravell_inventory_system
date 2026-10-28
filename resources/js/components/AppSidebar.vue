<template>
    <div>
        <!-- Mobile backdrop -->
        <div
            v-if="isOpen"
            class="fixed inset-0 z-40 bg-black/50 lg:hidden"
            @click="$emit('close')"
        ></div>

        <!-- Sidebar container -->
        <aside
            :class="[
                'fixed top-0 bottom-0 left-0 z-50 w-64 max-w-[calc(100vw-1rem)] shrink-0 flex flex-col overflow-x-hidden transition-transform duration-200 ease-in-out lg:translate-x-0 bg-[#14213D] text-[#C9D3E0]',
                isOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <!-- Brand Logo / App Title -->
            <div
                class="h-16 flex items-center justify-between px-6 border-b border-white/10 flex-shrink-0"
            >
                <div class="flex items-center space-x-3">
                    <div
                        class="w-8 h-8 rounded bg-[#1E5AA8] flex items-center justify-center font-bold text-white text-base"
                    >
                        IM
                    </div>
                    <span class="font-semibold text-white text-base tracking-tight"
                        >Inventory Hub</span
                    >
                </div>
                <button
                    type="button"
                    class="lg:hidden text-[#C9D3E0] hover:text-white p-2"
                    @click="$emit('close')"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="sidebar-nav-scroll min-w-0 flex-1 overflow-x-hidden overflow-y-auto px-3 py-4 space-y-1">
                <template v-for="item in navItems" :key="item.name">
                    <router-link
                        v-if="hasAccess(item)"
                        :to="item.to"
                        :class="[
                            'flex w-full min-w-0 items-center overflow-hidden px-3 py-2.5 rounded-md text-sm font-medium transition-colors min-h-[44px]',
                            $route.path === item.to ||
                            ($route.path.startsWith(item.to) && item.to !== '/')
                                ? 'bg-[#1E3A66] text-white font-semibold'
                                : 'text-[#C9D3E0] hover:bg-white/5 hover:text-white',
                        ]"
                        @click="$emit('close')"
                    >
                        <!-- Monochrome Icon -->
                        <component :is="item.icon" class="w-5 h-5 mr-3 shrink-0 opacity-80" />
                        <span class="min-w-0 flex-1 truncate">{{ itemLabel(item) }}</span>
                    </router-link>
                </template>
            </nav>

            <!-- User footer inside sidebar -->
            <div
                class="p-4 border-t border-white/10 flex items-center justify-between flex-shrink-0"
            >
                <div class="truncate">
                    <p class="text-xs text-white/60">Signed in as</p>
                    <p class="truncate text-sm font-semibold text-white">{{ authStore.roleLabel }}</p>
                </div>
            </div>
        </aside>
    </div>
</template>

<script setup>
import { h } from 'vue';
import { useAuthStore } from '../stores/auth';

defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['close']);

const authStore = useAuthStore();

// Icons as lightweight render functions
const BoxIcon = () =>
    h('svg', { fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor' }, [
        h('path', {
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            strokeWidth: '2',
            d: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        }),
    ]);
const FolderIcon = () =>
    h('svg', { fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor' }, [
        h('path', {
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            strokeWidth: '2',
            d: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
        }),
    ]);
const BuildingIcon = () =>
    h('svg', { fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor' }, [
        h('path', {
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            strokeWidth: '2',
            d: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        }),
    ]);
const TruckIcon = () =>
    h('svg', { fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor' }, [
        h('path', {
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            strokeWidth: '2',
            d: 'M8 17a2 2 0 100 4 2 2 0 000-4zm10 0a2 2 0 100 4 2 2 0 000-4zM5 11l2-4h8l2 4M3 11h18v6H3v-6z',
        }),
    ]);
const ChartIcon = () =>
    h('svg', { fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor' }, [
        h('path', {
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            strokeWidth: '2',
            d: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        }),
    ]);
const HistoryIcon = () =>
    h('svg', { fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor' }, [
        h('path', {
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            strokeWidth: '2',
            d: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        }),
    ]);
const UsersIcon = () =>
    h('svg', { fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor' }, [
        h('path', {
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            strokeWidth: '2',
            d: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        }),
    ]);

const navItems = [
    {
        name: 'dashboard',
        label: 'Dashboard',
        to: '/dashboard',
        permission: 'reports.view',
        icon: ChartIcon,
    },
    {
        name: 'reports',
        label: 'Reports',
        to: '/reports',
        permission: 'reports.view',
        icon: HistoryIcon,
    },
    {
        name: 'report-exports',
        label: 'Report Exports',
        to: '/reports/exports',
        permission: 'reports.export',
        icon: HistoryIcon,
    },
    {
        name: 'balances',
        label: 'Stock Balances',
        to: '/inventory/balances',
        permission: 'inventory.view',
        icon: ChartIcon,
    },
    {
        name: 'movements',
        label: 'Movement Logs',
        to: '/inventory/movements',
        permission: 'inventory.view',
        icon: HistoryIcon,
    },
    {
        name: 'products',
        label: 'Products',
        to: '/products',
        permission: 'products.view',
        icon: BoxIcon,
    },
    {
        name: 'categories',
        label: 'Categories',
        to: '/categories',
        permission: 'categories.view',
        icon: FolderIcon,
    },
    {
        name: 'warehouses',
        label: 'Warehouses',
        to: '/warehouses',
        permission: 'warehouses.view',
        icon: BuildingIcon,
    },
    {
        name: 'suppliers',
        label: 'Suppliers',
        to: '/suppliers',
        permission: 'suppliers.view',
        icon: TruckIcon,
    },
    {
        name: 'purchases',
        label: 'Purchases',
        to: '/purchases',
        permission: 'purchases.view',
        icon: TruckIcon,
    },
    {
        name: 'sales-orders',
        label: 'Sales Orders',
        to: '/sales-orders',
        permission: 'sales.view',
        icon: TruckIcon,
    },
    {
        name: 'sales-returns',
        label: 'Customer Returns',
        to: '/sales-returns',
        permission: 'returns.view',
        icon: HistoryIcon,
    },
    {
        name: 'customers',
        label: 'Customers',
        to: '/customers',
        permission: 'customers.view',
        icon: UsersIcon,
    },
    {
        name: 'transfers',
        label: 'Warehouse Transfers',
        to: '/transfers',
        permission: 'transfers.view',
        icon: TruckIcon,
    },
    {
        name: 'stock-adjustments',
        label: 'Stock Adjustments',
        to: '/stock-adjustments',
        permission: 'adjustments.view',
        icon: HistoryIcon,
    },
    {
        name: 'system-settings',
        label: 'System Settings',
        to: '/system/settings',
        permission: 'users.manage',
        icon: BuildingIcon,
    },
    {
        name: 'users',
        label: 'User Management',
        to: '/users',
        permission: 'users.manage',
        icon: UsersIcon,
    },
    {
        name: 'notifications',
        label: 'Notifications',
        to: '/notifications',
        showForAuthenticated: true,
        icon: HistoryIcon,
    },
    {
        name: 'failed-jobs',
        label: 'Failed Jobs',
        to: '/system/failed-jobs',
        permission: 'users.manage',
        icon: HistoryIcon,
    },
];

const hasAccess = (item) => {
    if (item.permission) return authStore.hasPermission(item.permission);
    return item.showForAuthenticated === true && authStore.isAuthenticated;
};

const staffLabels = {
    dashboard: 'Dashboard',
    reports: 'Reports',
    balances: 'Assigned Stock',
    movements: 'Stock Movements',
    warehouses: 'My Warehouse',
    purchases: 'Purchase Receiving',
    'sales-orders': 'Sales Orders',
    'sales-returns': 'Return Receiving',
    customers: 'Customers',
    transfers: 'Warehouse Transfers',
    'stock-adjustments': 'Adjustment History',
};

const itemLabel = (item) =>
    authStore.role === 'Warehouse Staff' ? staffLabels[item.name] || item.label : item.label;
</script>
