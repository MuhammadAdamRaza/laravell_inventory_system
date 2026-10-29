<template>
    <div class="space-y-6">
        <PageHeader
            title="Reports & insights"
            subtitle="Turn inventory and order activity into clear, actionable information."
        />
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-2">
            <router-link
                v-for="report in availableReports"
                :key="report.path"
                :to="`/reports/${report.path}`"
                class="surface-card group p-6 transition hover:border-blue-300 hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <span
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-primary-app"
                        ><AppIcon
                            :name="
                                report.path === 'inventory-valuation'
                                    ? 'warehouse'
                                    : report.path === 'purchases'
                                      ? 'purchase'
                                      : 'chart'
                            "
                            class="h-6 w-6" /></span
                    ><AppIcon
                        name="arrow"
                        class="h-5 w-5 text-slate-300 transition group-hover:translate-x-1 group-hover:text-blue-600"
                    />
                </div>
                <h2 class="mt-6 text-base font-semibold tracking-tight text-main">
                    {{ report.title }}
                </h2>
                <p class="mt-2 max-w-md text-xs leading-6 text-muted">{{ report.description }}</p>
                <span
                    class="mt-6 inline-flex items-center gap-2 text-xs font-semibold text-primary-app"
                    >Explore report<AppIcon name="chevron" class="h-3 w-3"
                /></span>
            </router-link>
        </section>
    </div>
</template>
<script setup>
import { computed } from 'vue';
import AppIcon from '../../components/AppIcon.vue';
import PageHeader from '../../components/PageHeader.vue';
import { useAuthStore } from '../../stores/auth';
const auth = useAuthStore();
const reports = [
    {
        path: 'purchases',
        title: 'Purchases',
        description: 'Review purchases by date, warehouse, supplier and status.',
        permission: 'purchases.view',
    },
    {
        path: 'sales',
        title: 'Sales',
        description: 'Review confirmed customer orders and sales totals.',
        permission: 'sales.view',
    },
    {
        path: 'top-products',
        title: 'Top selling products',
        description: 'Compare products by confirmed units sold and sales value.',
        permission: 'sales.view',
    },
    {
        path: 'inventory-valuation',
        title: 'Inventory valuation',
        description: 'See stock quantities and purchase-cost value by product and warehouse.',
        permission: 'inventory.view',
    },
];
const availableReports = computed(() =>
    reports.filter((report) => auth.hasPermission(report.permission)),
);
</script>
