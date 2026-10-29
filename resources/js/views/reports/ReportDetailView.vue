<template>
    <div class="space-y-6">
        <PageHeader :title="config.title" :subtitle="config.description">
            <template #actions>
                <button
                    v-if="canExport"
                    type="button"
                    :disabled="loading || exporting"
                    class="min-h-11 rounded-md bg-primary-app px-4 text-sm font-semibold text-white bg-primary-hover disabled:opacity-50"
                    @click="exportCsv"
                >
                    {{ exporting ? 'Preparing…' : 'Export CSV' }}
                </button>
            </template>
        </PageHeader>

        <form
            class="surface-card grid gap-3 rounded-lg border border-app bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4"
            @submit.prevent="fetchReport(1)"
        >
            <label class="text-xs font-semibold text-muted"
                >From<input
                    v-model="filters.from"
                    type="date"
                    class="mt-1 min-h-11 w-full rounded-md border border-app px-3 text-sm text-main"
            /></label>
            <label class="text-xs font-semibold text-muted"
                >To<input
                    v-model="filters.to"
                    type="date"
                    class="mt-1 min-h-11 w-full rounded-md border border-app px-3 text-sm text-main"
            /></label>
            <label class="text-xs font-semibold text-muted"
                >Warehouse<select
                    v-model="filters.warehouse_id"
                    class="mt-1 min-h-11 w-full rounded-md border border-app bg-white px-3 text-sm text-main"
                >
                    <option value="">All assigned warehouses</option>
                    <option
                        v-for="item in options.warehouses"
                        :key="item.id"
                        :value="String(item.id)"
                    >
                        {{ item.name }}
                    </option>
                </select></label
            >
            <label v-if="reportKind === 'purchases'" class="text-xs font-semibold text-muted"
                >Supplier<select
                    v-model="filters.supplier_id"
                    class="mt-1 min-h-11 w-full rounded-md border border-app bg-white px-3 text-sm text-main"
                >
                    <option value="">All suppliers</option>
                    <option
                        v-for="item in options.suppliers"
                        :key="item.id"
                        :value="String(item.id)"
                    >
                        {{ item.name }}
                    </option>
                </select></label
            >
            <label v-if="reportKind === 'purchases'" class="text-xs font-semibold text-muted"
                >Status<select
                    v-model="filters.status"
                    class="mt-1 min-h-11 w-full rounded-md border border-app bg-white px-3 text-sm text-main"
                >
                    <option value="">All non-cancelled statuses</option>
                    <option v-for="status in purchaseStatuses" :key="status" :value="status">
                        {{ labelize(status) }}
                    </option>
                </select></label
            >
            <label v-if="reportKind === 'sales'" class="text-xs font-semibold text-muted"
                >Customer<select
                    v-model="filters.customer_id"
                    class="mt-1 min-h-11 w-full rounded-md border border-app bg-white px-3 text-sm text-main"
                >
                    <option value="">All customers</option>
                    <option
                        v-for="item in options.customers"
                        :key="item.id"
                        :value="String(item.id)"
                    >
                        {{ item.name }}
                    </option>
                </select></label
            >
            <label
                v-if="reportKind === 'inventory-valuation'"
                class="text-xs font-semibold text-muted"
                >Category<select
                    v-model="filters.category_id"
                    class="mt-1 min-h-11 w-full rounded-md border border-app bg-white px-3 text-sm text-main"
                >
                    <option value="">All categories</option>
                    <option
                        v-for="item in options.categories"
                        :key="item.id"
                        :value="String(item.id)"
                    >
                        {{ item.name }}
                    </option>
                </select></label
            >
            <label v-if="reportKind === 'top-products'" class="text-xs font-semibold text-muted"
                >Products to rank<input
                    v-model.number="filters.limit"
                    type="number"
                    min="1"
                    max="100"
                    class="mt-1 min-h-11 w-full rounded-md border border-app px-3 text-sm text-main"
            /></label>
            <div class="flex items-end gap-2">
                <button
                    class="min-h-11 flex-1 rounded-md border border-app bg-app-bg px-4 text-sm font-semibold text-main hover:bg-[#EAF2FC]"
                >
                    Apply filters</button
                ><button
                    type="button"
                    class="min-h-11 rounded-md px-3 text-sm font-medium text-muted hover:bg-app-bg"
                    @click="resetFilters"
                >
                    Reset
                </button>
            </div>
        </form>

        <div
            v-if="error"
            class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
            role="alert"
        >
            {{ error }}
            <button class="ml-2 font-semibold underline" @click="fetchReport(page)">Retry</button>
        </div>
        <section v-if="summaryCards.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="card in summaryCards"
                :key="card.label"
                class="surface-card rounded-lg border border-app bg-white p-4 shadow-sm"
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">
                    {{ card.label }}
                </p>
                <p class="mt-2 text-xl font-bold text-main">
                    {{ card.money ? money(card.value) : Number(card.value || 0).toLocaleString() }}
                </p>
            </article>
        </section>

        <section
            class="surface-card overflow-hidden rounded-lg border border-app bg-white shadow-sm"
        >
            <div v-if="loading" class="space-y-3 p-4">
                <div v-for="n in 7" :key="n" class="h-10 animate-pulse rounded bg-app-bg"></div>
            </div>
            <div v-else-if="!rows.length" class="p-12 text-center">
                <h2 class="font-semibold text-main">No records found</h2>
                <p class="mt-1 text-sm text-muted">Try a different date range or filter.</p>
            </div>
            <template v-else>
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-app-bg text-xs uppercase tracking-wide text-muted">
                            <tr>
                                <th
                                    v-for="column in config.columns"
                                    :key="column.key"
                                    class="whitespace-nowrap px-4 py-3"
                                >
                                    {{ column.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8EDF3]">
                            <tr
                                v-for="(row, index) in rows"
                                :key="row.id || row.product_id || index"
                                class="hover:bg-[#F9FBFD]"
                            >
                                <td
                                    v-for="column in config.columns"
                                    :key="column.key"
                                    class="whitespace-nowrap px-4 py-3 text-main"
                                >
                                    {{ cell(row, column) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="divide-y divide-[#E8EDF3] md:hidden">
                    <article
                        v-for="(row, index) in rows"
                        :key="row.id || row.product_id || index"
                        class="space-y-2 p-4"
                    >
                        <div
                            v-for="column in config.columns"
                            :key="column.key"
                            class="flex items-start justify-between gap-4 text-sm"
                        >
                            <span class="text-muted">{{ column.label }}</span
                            ><span class="text-right font-medium text-main">{{
                                cell(row, column)
                            }}</span>
                        </div>
                    </article>
                </div>
            </template>
            <div
                v-if="pagination?.total"
                class="flex flex-col gap-3 border-t border-app p-4 text-sm text-muted sm:flex-row sm:items-center sm:justify-between"
            >
                <span
                    >Showing {{ pagination.from || 0 }}–{{ pagination.to || 0 }} of
                    {{ pagination.total }}</span
                >
                <div class="flex items-center justify-between gap-3 sm:justify-end">
                    <button
                        :disabled="pagination.current_page <= 1"
                        class="min-h-10 rounded-md border border-app px-3 disabled:opacity-40"
                        @click="fetchReport(pagination.current_page - 1)"
                    >
                        Previous</button
                    ><span>Page {{ pagination.current_page }} of {{ pagination.last_page }}</span
                    ><button
                        :disabled="pagination.current_page >= pagination.last_page"
                        class="min-h-10 rounded-md border border-app px-3 disabled:opacity-40"
                        @click="fetchReport(pagination.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../api/axios';
import PageHeader from '../../components/PageHeader.vue';
import { useAuthStore } from '../../stores/auth';
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Karachi' }).format(new Date());
const filters = reactive({
    from: `${today.slice(0, 8)}01`,
    to: today,
    warehouse_id: '',
    supplier_id: '',
    customer_id: '',
    category_id: '',
    status: '',
    limit: 10,
});
const options = reactive({ warehouses: [], suppliers: [], customers: [], categories: [] });
const report = ref(null);
const loading = ref(true);
const exporting = ref(false);
const error = ref('');
const page = ref(1);
const purchaseStatuses = [
    'draft',
    'pending_approval',
    'approved',
    'partially_received',
    'received',
];
const reportKind = computed(() => String(route.params.reportKind || 'purchases'));
const configs = {
    purchases: {
        title: 'Purchase report',
        description:
            'Supplier purchases in the selected period. Cancelled orders are excluded from totals.',
        endpoint: '/api/reports/purchases',
        export: '/api/reports/purchases/export',
        columns: [
            { key: 'purchase_number', label: 'Purchase' },
            { key: 'supplier_name', label: 'Supplier' },
            { key: 'warehouse_name', label: 'Warehouse' },
            { key: 'status', label: 'Status' },
            { key: 'order_date', label: 'Order date' },
            { key: 'grand_total', label: 'Total', money: true },
        ],
    },
    sales: {
        title: 'Sales report',
        description: 'Confirmed customer sales in the selected date range.',
        endpoint: '/api/reports/sales',
        export: '/api/reports/sales/export',
        columns: [
            { key: 'order_number', label: 'Order' },
            { key: 'customer_name', label: 'Customer' },
            { key: 'warehouse_name', label: 'Warehouse' },
            { key: 'order_date', label: 'Order date' },
            { key: 'grand_total', label: 'Total', money: true },
        ],
    },
    'top-products': {
        title: 'Top selling products',
        description: 'Products ranked by units on confirmed sales orders.',
        endpoint: '/api/reports/top-products',
        export: '/api/reports/top-products/export',
        columns: [
            { key: 'sku', label: 'SKU' },
            { key: 'product_name', label: 'Product' },
            { key: 'units_sold', label: 'Units sold' },
            { key: 'sales_total', label: 'Sales total', money: true },
        ],
    },
    'inventory-valuation': {
        title: 'Inventory valuation',
        description: 'Current quantity multiplied by each product’s purchase cost.',
        endpoint: '/api/reports/inventory-valuation',
        export: '/api/reports/inventory-valuation/export',
        columns: [
            { key: 'sku', label: 'SKU' },
            { key: 'product_name', label: 'Product' },
            { key: 'category_name', label: 'Category' },
            { key: 'warehouse_name', label: 'Warehouse' },
            { key: 'quantity', label: 'Units' },
            { key: 'purchase_price', label: 'Unit cost', money: true },
            { key: 'inventory_value', label: 'Value', money: true },
        ],
    },
};
const config = computed(() => configs[reportKind.value] || configs.purchases);
const rows = computed(() => report.value?.rows?.data || []);
const pagination = computed(() => report.value?.rows || null);
const canExport = computed(() => auth.hasPermission('reports.export'));
const summaryCards = computed(() => {
    const summary = report.value?.summary || {};
    return Object.entries(summary)
        .filter(
            ([key, value]) => key !== 'warehouses' && value !== null && typeof value !== 'object',
        )
        .map(([key, value]) => ({
            label: labelize(key),
            value,
            money: key.includes('total') || key.includes('value'),
        }));
});
const requestFilters = (currentPage = 1) =>
    Object.fromEntries(
        Object.entries({ ...filters, page: currentPage }).filter(
            ([, value]) => value !== '' && value !== null && value !== undefined,
        ),
    );
async function loadOptions() {
    try {
        const { data } = await api.get('/api/reports/options');
        Object.assign(options, data.data || {});
    } catch {
        /* report endpoint returns the relevant permission message */
    }
}
async function fetchReport(currentPage = 1) {
    page.value = currentPage;
    loading.value = true;
    error.value = '';
    try {
        const { data } = await api.get(config.value.endpoint, {
            params: requestFilters(currentPage),
        });
        report.value = data;
    } catch (e) {
        error.value = e.response?.data?.message || 'Report could not be loaded.';
        report.value = null;
    } finally {
        loading.value = false;
    }
}
async function exportCsv() {
    exporting.value = true;
    try {
        const response = await api.get(config.value.export, {
            params: requestFilters(1),
            responseType: 'blob',
        });
        if (response.status === 202) {
            const payload = JSON.parse(await response.data.text());
            error.value = payload.message || 'Your export is being generated.';
            router.push({ name: 'report-exports' });
            return;
        }
        const url = URL.createObjectURL(response.data);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${reportKind.value}-${filters.from}-to-${filters.to}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    } catch (e) {
        error.value = e.response?.data?.message || 'CSV export failed.';
    } finally {
        exporting.value = false;
    }
}
function resetFilters() {
    Object.assign(filters, {
        from: `${today.slice(0, 8)}01`,
        to: today,
        warehouse_id: '',
        supplier_id: '',
        customer_id: '',
        category_id: '',
        status: '',
        limit: 10,
    });
    fetchReport(1);
}
const money = (value) =>
    `PKR ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const cell = (row, column) =>
    column.money
        ? money(row[column.key])
        : column.key === 'status'
          ? labelize(row[column.key])
          : (row[column.key] ?? '—');
const labelize = (value) =>
    String(value || '')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
watch(reportKind, () => {
    report.value = null;
    fetchReport(1);
});
onMounted(() => {
    loadOptions();
    fetchReport(1);
});
</script>
