<template>
    <div class="space-y-6">
        <PageHeader title="Inventory dashboard" subtitle="A current view of stock, purchasing and sales activity.">
            <template #actions><button type="button" class="min-h-11 rounded-md bg-[#1E5AA8] px-4 text-sm font-semibold text-white hover:bg-[#174A8A]" @click="load">Refresh</button></template>
        </PageHeader>

        <form class="grid gap-3 rounded-lg border border-[#DDE3EA] bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="load">
            <label class="text-xs font-semibold text-[#637083]">From<input v-model="filters.from" type="date" class="mt-1 min-h-11 w-full rounded-md border border-[#DDE3EA] px-3 text-sm text-[#1F2933]" /></label>
            <label class="text-xs font-semibold text-[#637083]">To<input v-model="filters.to" type="date" class="mt-1 min-h-11 w-full rounded-md border border-[#DDE3EA] px-3 text-sm text-[#1F2933]" /></label>
            <label class="text-xs font-semibold text-[#637083]">Warehouse<select v-model="filters.warehouse_id" class="mt-1 min-h-11 w-full rounded-md border border-[#DDE3EA] bg-white px-3 text-sm text-[#1F2933]"><option value="">All assigned warehouses</option><option v-for="warehouse in dashboard?.filters?.warehouses || []" :key="warehouse.id" :value="String(warehouse.id)">{{ warehouse.name }}</option></select></label>
            <div class="flex items-end"><button class="min-h-11 w-full rounded-md border border-[#DDE3EA] bg-[#F5F7FA] px-4 text-sm font-semibold text-[#1F2933] hover:bg-[#EAF2FC]">Apply filters</button></div>
        </form>
        <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ error }} <button class="ml-2 font-semibold underline" @click="load">Retry</button></div>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6" aria-label="Summary totals">
            <article v-for="card in cards" :key="card.key" class="min-h-28 rounded-lg border border-[#DDE3EA] bg-white p-4 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-[#637083]">{{ card.label }}</p><div v-if="loading" class="mt-3 h-7 w-24 animate-pulse rounded bg-[#E8EDF3]"></div><p v-else class="mt-2 break-words text-xl font-bold text-[#1F2933]">{{ formatValue(card.key, dashboard?.totals?.[card.key]) }}</p></article>
        </section>

        <section v-if="canSeeTrends" class="rounded-lg border border-[#DDE3EA] bg-white p-4 shadow-sm sm:p-5">
            <div class="mb-4"><h2 class="font-semibold text-[#1F2933]">Purchases and sales trend</h2><p class="text-sm text-[#637083]">Monthly totals for the selected period.</p></div>
            <div v-if="loading" class="h-48 animate-pulse rounded bg-[#F5F7FA]"></div>
            <div v-else-if="dashboard?.trend?.months?.length" class="overflow-x-auto"><div class="flex min-w-[560px] items-end gap-3 border-b border-l border-[#DDE3EA] px-3 pb-2 pt-4" style="height: 220px">
                <div v-for="(month, index) in dashboard.trend.months" :key="month" class="flex h-full min-w-12 flex-1 flex-col justify-end gap-1 text-center"><div class="flex h-full items-end justify-center gap-1" :aria-label="`${month}: sales ${dashboard.trend.sales?.[index] ?? 'unavailable'}, purchases ${dashboard.trend.purchases?.[index] ?? 'unavailable'}`"><span v-if="dashboard.trend.sales !== null" class="w-3 rounded-t bg-[#1E5AA8]" :style="{ height: barHeight(dashboard.trend.sales[index]) }"></span><span v-if="dashboard.trend.purchases !== null" class="w-3 rounded-t bg-[#7CB5E8]" :style="{ height: barHeight(dashboard.trend.purchases[index]) }"></span></div><span class="text-[11px] text-[#637083]">{{ month }}</span></div>
            </div></div><p v-else class="py-10 text-center text-sm text-[#637083]">No trend data for this period.</p>
            <div class="mt-3 flex flex-wrap gap-4 text-xs text-[#637083]"><span v-if="dashboard?.trend?.sales !== null" class="inline-flex items-center gap-2"><i class="h-3 w-3 rounded bg-[#1E5AA8]"></i>Sales</span><span v-if="dashboard?.trend?.purchases !== null" class="inline-flex items-center gap-2"><i class="h-3 w-3 rounded bg-[#7CB5E8]"></i>Purchases</span></div>
            <div class="sr-only"><table><caption>Trend values by month</caption><thead><tr><th>Month</th><th>Sales</th><th>Purchases</th></tr></thead><tbody><tr v-for="(month, index) in dashboard?.trend?.months || []" :key="month"><th>{{ month }}</th><td>{{ dashboard.trend.sales?.[index] ?? 'Unavailable' }}</td><td>{{ dashboard.trend.purchases?.[index] ?? 'Unavailable' }}</td></tr></tbody></table></div>
        </section>

        <div class="grid gap-4 xl:grid-cols-2">
            <section v-if="auth.hasPermission('inventory.view')" class="rounded-lg border border-[#DDE3EA] bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between"><div><h2 class="font-semibold text-[#1F2933]">Stock alerts</h2><p class="text-sm text-[#637083]">Low and out-of-stock items</p></div><router-link to="/inventory/balances" class="text-sm font-semibold text-[#1E5AA8]">View stock</router-link></div>
                <p class="mb-3 text-sm text-[#637083]">{{ dashboard?.stock_alerts?.low_stock_count ?? 0 }} low · {{ dashboard?.stock_alerts?.out_of_stock_count ?? 0 }} out of stock</p>
                <div v-if="loading" class="h-24 animate-pulse rounded bg-[#F5F7FA]"></div><div v-else-if="alerts.length" class="divide-y divide-[#E8EDF3]"><div v-for="item in alerts" :key="`${item.product_id}-${item.warehouse_id}`" class="flex items-center justify-between gap-3 py-3 text-sm"><div class="min-w-0"><p class="truncate font-medium text-[#1F2933]">{{ item.product_name }}</p><p class="text-xs text-[#637083]">{{ item.sku }} · {{ item.warehouse_name }}</p></div><span :class="item.quantity === 0 ? 'text-red-700' : 'text-amber-700'" class="shrink-0 font-semibold">{{ item.quantity }} / {{ item.minimum_stock }}</span></div></div><p v-else class="py-6 text-center text-sm text-[#637083]">No stock alerts for this selection.</p>
            </section>
            <section v-if="auth.hasPermission('inventory.view')" class="rounded-lg border border-[#DDE3EA] bg-white p-4 shadow-sm">
                <div class="mb-3"><h2 class="font-semibold text-[#1F2933]">Recent stock movements</h2><p class="text-sm text-[#637083]">Latest changes in your warehouse scope</p></div>
                <div v-if="loading" class="space-y-2"><div v-for="n in 4" :key="n" class="h-10 animate-pulse rounded bg-[#F5F7FA]"></div></div><div v-else-if="dashboard?.recent_movements?.length" class="divide-y divide-[#E8EDF3]"><div v-for="movement in dashboard.recent_movements" :key="movement.id" class="flex items-center justify-between gap-3 py-3 text-sm"><div class="min-w-0"><p class="truncate font-medium text-[#1F2933]">{{ movement.product.name }}</p><p class="truncate text-xs text-[#637083]">{{ movement.warehouse.name }} · {{ movement.movement_type }} · {{ movement.created_at }}</p></div><span class="shrink-0 font-semibold" :class="movement.quantity_change > 0 ? 'text-emerald-700' : 'text-[#1F2933]'">{{ movement.quantity_change > 0 ? '+' : '' }}{{ movement.quantity_change }}</span></div></div><p v-else class="py-6 text-center text-sm text-[#637083]">No recent stock movements.</p>
            </section>
        </div>
        <section v-if="auth.hasPermission('inventory.view')" class="rounded-lg border border-[#DDE3EA] bg-white p-4 shadow-sm"><h2 class="font-semibold text-[#1F2933]">Warehouse stock summary</h2><div v-if="loading" class="mt-3 h-24 animate-pulse rounded bg-[#F5F7FA]"></div><div v-else-if="dashboard?.warehouse_summary?.length" class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3"><article v-for="warehouse in dashboard.warehouse_summary" :key="warehouse.warehouse_id" class="rounded-md border border-[#E8EDF3] p-4"><h3 class="font-medium text-[#1F2933]">{{ warehouse.name }}</h3><p class="mt-2 text-sm text-[#637083]">{{ warehouse.products }} products · {{ Number(warehouse.units).toLocaleString() }} units</p><p class="mt-1 text-sm font-semibold text-[#1F2933]">Inventory value {{ money(warehouse.value) }}</p></article></div><p v-else class="py-6 text-center text-sm text-[#637083]">No warehouse data available.</p></section>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import api from '../../api/axios';
import PageHeader from '../../components/PageHeader.vue';
import { useAuthStore } from '../../stores/auth';
const auth = useAuthStore();
const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Karachi' }).format(new Date());
const filters = reactive({ from: `${today.slice(0, 8)}01`, to: today, warehouse_id: '' });
const dashboard = ref(null); const loading = ref(true); const error = ref('');
const cards = computed(() => [{ key: 'products', label: 'Products' }, { key: 'suppliers', label: 'Suppliers' }, { key: 'warehouses', label: 'Warehouses' }, { key: 'inventory_value', label: 'Inventory value' }, { key: 'purchases_total', label: 'Purchases' }, { key: 'sales_total', label: 'Confirmed sales' }].filter((card) => loading.value || dashboard.value?.totals?.[card.key] !== null));
const alerts = computed(() => [...(dashboard.value?.stock_alerts?.out_of_stock_items || []), ...(dashboard.value?.stock_alerts?.low_stock_items || [])].slice(0, 8));
const canSeeTrends = computed(() => auth.hasPermission('purchases.view') || auth.hasPermission('sales.view'));
async function load() { loading.value = true; error.value = ''; try { const { data } = await api.get('/api/reports/dashboard', { params: { ...filters, warehouse_id: filters.warehouse_id || undefined } }); dashboard.value = data; } catch (e) { error.value = e.response?.data?.message || 'Dashboard data could not be loaded.'; } finally { loading.value = false; } }
const money = (value) => `PKR ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const formatValue = (key, value) => value == null ? '—' : ['inventory_value', 'purchases_total', 'sales_total'].includes(key) ? money(value) : Number(value).toLocaleString();
function barHeight(value) { const amounts = [...(dashboard.value?.trend?.sales || []), ...(dashboard.value?.trend?.purchases || [])].map(Number); return `${Math.max(2, (Number(value || 0) / Math.max(1, ...amounts)) * 100)}%`; }
onMounted(load);
</script>
