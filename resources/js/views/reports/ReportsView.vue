<template>
    <div class="space-y-6">
        <PageHeader title="Reports" subtitle="Explore inventory, purchasing and sales performance." />
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <router-link v-for="report in availableReports" :key="report.path" :to="`/reports/${report.path}`" class="group rounded-lg border border-[#DDE3EA] bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-[#1E5AA8] hover:shadow-md">
                <div class="flex items-start justify-between gap-4"><div><h2 class="font-semibold text-[#1F2933]">{{ report.title }}</h2><p class="mt-2 text-sm leading-6 text-[#637083]">{{ report.description }}</p></div><span class="rounded-full bg-[#EAF2FC] px-3 py-1 text-xs font-semibold text-[#1E5AA8]">Open</span></div>
                <span class="mt-5 inline-flex text-sm font-semibold text-[#1E5AA8]">View report <span aria-hidden="true" class="ml-2">→</span></span>
            </router-link>
        </section>
    </div>
</template>
<script setup>
import { computed } from 'vue';
import PageHeader from '../../components/PageHeader.vue';
import { useAuthStore } from '../../stores/auth';
const auth = useAuthStore();
const reports = [
    { path: 'purchases', title: 'Purchases', description: 'Review purchases by date, warehouse, supplier and status.', permission: 'purchases.view' },
    { path: 'sales', title: 'Sales', description: 'Review confirmed customer orders and sales totals.', permission: 'sales.view' },
    { path: 'top-products', title: 'Top selling products', description: 'Compare products by confirmed units sold and sales value.', permission: 'sales.view' },
    { path: 'inventory-valuation', title: 'Inventory valuation', description: 'See stock quantities and purchase-cost value by product and warehouse.', permission: 'inventory.view' },
];
const availableReports = computed(() => reports.filter((report) => auth.hasPermission(report.permission)));
</script>
