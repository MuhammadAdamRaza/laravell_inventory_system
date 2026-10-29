<template>
    <div class="space-y-6">
        <PageHeader
            title="Workspace overview"
            subtitle="A clearer view of your stock, purchasing and sales activity."
        >
            <template #actions
                ><button type="button" class="ui-button" :disabled="loading" @click="load">
                    <AppIcon
                        name="refresh"
                        class="h-4 w-4"
                        :class="{ 'animate-spin': loading }"
                    />{{ loading ? 'Refreshing...' : 'Refresh overview' }}
                </button></template
            >
        </PageHeader>

        <form
            class="dashboard-filters surface-card flex flex-wrap items-end gap-3 p-4"
            @submit.prevent="load"
        >
            <div class="mr-auto self-center">
                <p class="text-xs font-semibold text-main">Reporting period</p>
                <div class="mt-2 flex gap-1">
                    <button
                        type="button"
                        class="rounded-md px-2.5 py-1.5 text-[11px] font-medium"
                        :class="
                            period === 'month'
                                ? 'bg-blue-50 text-primary-app'
                                : 'text-muted hover:bg-slate-50'
                        "
                        @click="setPeriod('month')"
                    >
                        This month</button
                    ><button
                        type="button"
                        class="rounded-md px-2.5 py-1.5 text-[11px] font-medium"
                        :class="
                            period === 'six'
                                ? 'bg-blue-50 text-primary-app'
                                : 'text-muted hover:bg-slate-50'
                        "
                        @click="setPeriod('six')"
                    >
                        Last 6 months
                    </button>
                </div>
            </div>
            <label class="min-w-0 flex-1 text-[11px] font-medium text-muted sm:max-w-44"
                >From<input
                    v-model="filters.from"
                    type="date"
                    class="mt-1.5 w-full px-3 text-xs"
                    required
                    @input="period = 'custom'"
            /></label>
            <label class="min-w-0 flex-1 text-[11px] font-medium text-muted sm:max-w-44"
                >To<input
                    v-model="filters.to"
                    type="date"
                    class="mt-1.5 w-full px-3 text-xs"
                    :min="filters.from"
                    required
                    @input="period = 'custom'"
            /></label>
            <label class="w-full text-[11px] font-medium text-muted sm:w-52"
                >Warehouse<select v-model="filters.warehouse_id" class="mt-1.5 w-full px-3 text-xs">
                    <option value="">All assigned warehouses</option>
                    <option
                        v-for="warehouse in dashboard?.filters?.warehouses || []"
                        :key="warehouse.id"
                        :value="String(warehouse.id)"
                    >
                        {{ warehouse.name }}
                    </option>
                </select></label
            >
            <button type="submit" class="ui-button primary w-full sm:w-auto" :disabled="loading">
                Apply filters
            </button>
        </form>

        <div
            v-if="error"
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"
            role="alert"
        >
            <span>{{ error }}</span
            ><button type="button" class="font-semibold underline" @click="load">Try again</button>
        </div>

        <section class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3" aria-label="Summary totals">
            <article v-for="card in cards" :key="card.key" class="surface-card metric-card">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs font-medium text-muted">{{ card.label }}</p>
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                        :class="card.color"
                        ><AppIcon :name="card.icon" class="h-[18px] w-[18px]"
                    /></span>
                </div>
                <div v-if="loading" class="my-4 h-8 w-3/4 animate-pulse rounded bg-slate-100"></div>
                <p v-else class="metric-value">
                    {{ formatValue(card.key, dashboard?.totals?.[card.key]) }}
                </p>
                <p class="mt-2 text-[10px] leading-relaxed text-muted">{{ card.note }}</p>
            </article>
        </section>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
            <section
                v-if="canSeeTrends"
                class="surface-card min-w-0 p-5 sm:p-6"
                aria-labelledby="activity-heading"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="activity-heading" class="text-sm font-semibold text-main">
                            Purchases &amp; sales
                        </h2>
                        <p class="mt-1 text-xs text-muted">Monthly order value in PKR</p>
                    </div>
                    <div class="flex gap-3">
                        <button
                            v-for="series in availableSeries"
                            :key="series.key"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded px-1 py-1 text-[11px] text-muted"
                            :aria-pressed="visibleSeries[series.key]"
                            :class="{ 'opacity-40': !visibleSeries[series.key] }"
                            @click="visibleSeries[series.key] = !visibleSeries[series.key]"
                        >
                            <span class="h-2 w-2 rounded-sm" :class="series.color"></span
                            >{{ series.label }}
                        </button>
                    </div>
                </div>
                <div v-if="loading" class="mt-6 h-64 animate-pulse rounded-xl bg-slate-50"></div>
                <div v-else-if="dashboard?.trend?.months?.length" class="mt-8 flex gap-3">
                    <div
                        class="flex h-52 w-12 shrink-0 flex-col justify-between pb-0 text-right text-[9px] text-muted"
                    >
                        <span v-for="fraction in [1, 0.75, 0.5, 0.25, 0]" :key="fraction">{{
                            compact(chartMaximum * fraction)
                        }}</span>
                    </div>
                    <div class="min-w-0 flex-1 overflow-x-auto pb-1">
                        <div
                            :style="{
                                minWidth: `${Math.max(200, dashboard.trend.months.length * 42)}px`,
                            }"
                        >
                            <div
                                class="chart-grid relative flex h-52 items-end gap-2 border-b border-slate-200 px-2"
                            >
                                <div
                                    v-for="(month, index) in dashboard.trend.months"
                                    :key="month"
                                    class="flex h-full min-w-8 flex-1 items-end justify-center gap-1.5"
                                >
                                    <div
                                        v-for="series in availableSeries.filter(
                                            (item) => visibleSeries[item.key],
                                        )"
                                        :key="series.key"
                                        class="chart-bar"
                                        :class="series.color"
                                        :style="{
                                            height: `${(Number(dashboard.trend[series.key][index]) / chartMaximum) * 100}%`,
                                        }"
                                        tabindex="0"
                                        role="img"
                                        :aria-label="`${monthLabel(month)} ${series.label}: ${money(dashboard.trend[series.key][index])}`"
                                    >
                                        <span class="chart-tooltip"
                                            >{{ series.label }}:
                                            {{ money(dashboard.trend[series.key][index]) }}</span
                                        >
                                    </div>
                                </div>
                                <p
                                    v-if="!hasTrendValues"
                                    class="absolute inset-0 flex items-center justify-center px-3 text-center text-xs text-muted"
                                >
                                    No order activity in this period.
                                </p>
                            </div>
                            <div class="mt-3 flex gap-2 px-2">
                                <span
                                    v-for="month in dashboard.trend.months"
                                    :key="month"
                                    class="min-w-8 flex-1 text-center text-[10px] text-muted"
                                    >{{ monthLabel(month) }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="py-20 text-center text-xs text-muted">
                    No trend data for this period.
                </p>
                <table class="sr-only">
                    <caption>
                        Monthly purchase and sales totals in PKR
                    </caption>
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th v-for="series in availableSeries" :key="series.key">
                                {{ series.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(month, index) in dashboard?.trend?.months || []" :key="month">
                            <th>{{ month }}</th>
                            <td v-for="series in availableSeries" :key="series.key">
                                {{ dashboard.trend[series.key][index] }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section
                v-if="auth.hasPermission('inventory.view')"
                class="surface-card p-5 sm:p-6"
                :class="{ 'xl:col-span-2': !canSeeTrends }"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-main">Stock health</h2>
                        <p class="mt-1 text-xs text-muted">Across your warehouse scope</p>
                    </div>
                    <AppIcon name="stock" class="h-5 w-5 text-muted" />
                </div>
                <div v-if="loading" class="my-6 h-36 animate-pulse rounded-xl bg-slate-50"></div>
                <template v-else
                    ><div class="my-6 flex items-center justify-center">
                        <div
                            class="flex h-36 w-36 items-center justify-center rounded-full"
                            :style="{ background: healthGradient }"
                            role="img"
                            :aria-label="`${healthPercent}% of product and warehouse balances are above minimum stock`"
                        >
                            <div
                                class="flex h-28 w-28 flex-col items-center justify-center rounded-full bg-white"
                            >
                                <strong class="text-3xl font-semibold tracking-tight text-main">{{
                                    stockEntries ? `${healthPercent}%` : '—'
                                }}</strong
                                ><span class="mt-1 text-[10px] text-muted">Healthy balances</span>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-3 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2 text-muted"
                                ><i class="h-2 w-2 rounded-full bg-blue-600"></i>Above minimum</span
                            ><strong class="font-semibold">{{ healthyBalances }}</strong>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2 text-muted"
                                ><i class="h-2 w-2 rounded-full bg-amber-400"></i>Low stock</span
                            ><strong class="font-semibold">{{
                                dashboard?.stock_alerts?.low_stock_count ?? '—'
                            }}</strong>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2 text-muted"
                                ><i class="h-2 w-2 rounded-full bg-rose-400"></i>Out of stock</span
                            ><strong class="font-semibold">{{
                                dashboard?.stock_alerts?.out_of_stock_count ?? '—'
                            }}</strong>
                        </div>
                    </div></template
                >
                <router-link
                    to="/inventory/balances"
                    class="mt-5 flex items-center justify-center gap-2 border-t border-app pt-4 text-xs font-semibold text-primary-app"
                    >Explore stock balances<AppIcon name="arrow" class="h-4 w-4"
                /></router-link>
            </section>
        </div>

        <section
            v-if="quickActions.length"
            class="flex flex-wrap items-center gap-3 rounded-xl border border-blue-100 bg-blue-50/60 px-5 py-4"
            aria-label="Quick actions"
        >
            <p class="mr-auto text-xs font-semibold text-main">Keep work moving</p>
            <router-link
                v-for="action in quickActions"
                :key="action.to"
                :to="action.to"
                class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-blue-100 bg-white px-3 text-xs font-medium text-primary-app hover:border-blue-300"
                ><AppIcon :name="action.icon" class="h-4 w-4" />{{ action.label
                }}<AppIcon name="plus" class="h-3.5 w-3.5"
            /></router-link>
        </section>

        <div v-if="auth.hasPermission('inventory.view')" class="grid gap-5 xl:grid-cols-2">
            <section class="surface-card overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-app p-5">
                    <div>
                        <h2 class="text-sm font-semibold text-main">Stock needs attention</h2>
                        <p class="mt-1 text-xs text-muted">Low and out-of-stock balances</p>
                    </div>
                    <span
                        class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700"
                        >{{ alertCount }}</span
                    >
                </div>
                <div v-if="loading" class="m-5 h-44 animate-pulse rounded bg-slate-50"></div>
                <div v-else-if="alerts.length" class="divide-y divide-slate-100">
                    <div
                        v-for="item in alerts"
                        :key="`${item.product_id}-${item.warehouse_id}`"
                        class="flex items-center gap-3 px-5 py-4"
                    >
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                            :class="
                                Number(item.quantity) === 0
                                    ? 'bg-rose-50 text-rose-500'
                                    : 'bg-amber-50 text-amber-600'
                            "
                            ><AppIcon name="box" class="h-4 w-4"
                        /></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold text-main">
                                {{ item.product_name }}
                            </p>
                            <p class="mt-1 truncate text-[10px] text-muted">
                                {{ item.sku }} · {{ item.warehouse_name }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p
                                class="text-xs font-semibold"
                                :class="
                                    Number(item.quantity) === 0 ? 'text-rose-600' : 'text-amber-700'
                                "
                            >
                                {{ item.quantity }} units
                            </p>
                            <p class="mt-1 text-[10px] text-muted">Min. {{ item.minimum_stock }}</p>
                        </div>
                    </div>
                </div>
                <div v-else class="px-5 py-12 text-center">
                    <AppIcon name="check" class="mx-auto mb-3 h-7 w-7 text-emerald-500" />
                    <p class="text-xs text-muted">All stock levels are above their minimums.</p>
                </div>
            </section>
            <section class="surface-card overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-app p-5">
                    <div>
                        <h2 class="text-sm font-semibold text-main">Latest movements</h2>
                        <p class="mt-1 text-xs text-muted">A record of your inventory activity</p>
                    </div>
                    <router-link
                        to="/inventory/movements"
                        class="text-xs font-semibold text-primary-app"
                        >View all</router-link
                    >
                </div>
                <div v-if="loading" class="m-5 h-44 animate-pulse rounded bg-slate-50"></div>
                <div
                    v-else-if="dashboard?.recent_movements?.length"
                    class="divide-y divide-slate-100"
                >
                    <div
                        v-for="movement in dashboard.recent_movements.slice(0, 6)"
                        :key="movement.id"
                        class="flex items-center gap-3 px-5 py-4"
                    >
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                            :class="
                                movement.quantity_change > 0
                                    ? 'bg-emerald-50 text-emerald-600'
                                    : 'bg-blue-50 text-primary-app'
                            "
                            ><AppIcon
                                name="arrow"
                                class="h-4 w-4"
                                :class="movement.quantity_change > 0 ? 'rotate-45' : '-rotate-45'"
                        /></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold text-main">
                                {{ movement.product.name }}
                            </p>
                            <p class="mt-1 truncate text-[10px] text-muted">
                                {{ movement.warehouse.name }} ·
                                {{ labelize(movement.movement_type) }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p
                                class="text-xs font-semibold"
                                :class="
                                    movement.quantity_change > 0 ? 'text-emerald-700' : 'text-main'
                                "
                            >
                                {{ movement.quantity_change > 0 ? '+' : ''
                                }}{{ movement.quantity_change }}
                            </p>
                            <time class="mt-1 block text-[10px] text-muted">{{
                                shortDate(movement.created_at)
                            }}</time>
                        </div>
                    </div>
                </div>
                <p v-else class="px-5 py-14 text-center text-xs text-muted">
                    Stock movements will appear here as work is completed.
                </p>
            </section>
        </div>

        <section v-if="auth.hasPermission('inventory.view')" class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-main">Your warehouses</h2>
                    <p class="mt-1 text-xs text-muted">Stock distribution at a glance</p>
                </div>
                <router-link
                    v-if="auth.hasPermission('warehouses.view')"
                    to="/warehouses"
                    class="text-xs font-semibold text-primary-app"
                    >View warehouses</router-link
                >
            </div>
            <div v-if="loading" class="h-32 animate-pulse rounded-xl bg-slate-100"></div>
            <div
                v-else-if="dashboard?.warehouse_summary?.length"
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="warehouse in dashboard.warehouse_summary"
                    :key="warehouse.warehouse_id"
                    class="surface-card p-5"
                >
                    <div class="flex items-start gap-3">
                        <span class="rounded-lg bg-slate-50 p-2 text-muted"
                            ><AppIcon name="warehouse" class="h-5 w-5"
                        /></span>
                        <div class="min-w-0">
                            <h3 class="text-xs font-semibold text-main">{{ warehouse.name }}</h3>
                            <p class="mt-1 text-[10px] text-muted">
                                {{ warehouse.products }} products ·
                                {{ Number(warehouse.units).toLocaleString() }} units
                            </p>
                        </div>
                    </div>
                    <div
                        class="mb-2 mt-5 h-1.5 overflow-hidden rounded-full bg-slate-100"
                        aria-hidden="true"
                    >
                        <div
                            class="h-full rounded-full bg-blue-400"
                            :style="{
                                width: `${(Number(warehouse.units) / maxWarehouseUnits) * 100}%`,
                            }"
                        ></div>
                    </div>
                    <p class="text-[11px] text-muted">
                        Inventory value
                        <strong class="font-semibold text-main">{{
                            money(warehouse.value)
                        }}</strong>
                    </p>
                </article>
            </div>
            <p v-else class="surface-card p-10 text-center text-xs text-muted">
                No warehouse data for this selection.
            </p>
        </section>
        <p v-if="updatedAt" class="text-right text-[10px] text-muted">
            Last refreshed {{ updatedAt }}
        </p>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import api from '../../api/axios';
import PageHeader from '../../components/PageHeader.vue';
import AppIcon from '../../components/AppIcon.vue';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Karachi' }).format(new Date());
const initialFrom = new Date(`${today}T12:00:00`);
initialFrom.setMonth(initialFrom.getMonth() - 5, 1);
const sixMonthStart = `${initialFrom.getFullYear()}-${String(initialFrom.getMonth() + 1).padStart(2, '0')}-01`;
const filters = reactive({ from: sixMonthStart, to: today, warehouse_id: '' });
const period = ref('six');
const dashboard = ref(null);
const loading = ref(true);
const error = ref('');
const updatedAt = ref('');
const visibleSeries = reactive({ sales: true, purchases: true });
let requestId = 0;

const cardDefinitions = [
    {
        key: 'inventory_value',
        label: 'Inventory value',
        icon: 'money',
        color: 'bg-blue-50 text-blue-600',
        note: 'Current stock at purchase cost · PKR',
        permission: 'inventory.view',
    },
    {
        key: 'products',
        label: 'Stocked products',
        icon: 'box',
        color: 'bg-violet-50 text-violet-600',
        note: 'Products in your warehouse scope',
        permission: 'products.view',
    },
    {
        key: 'warehouses',
        label: 'Warehouses',
        icon: 'warehouse',
        color: 'bg-slate-100 text-slate-600',
        note: 'Locations in your access scope',
        permission: 'warehouses.view',
    },
    {
        key: 'purchases_total',
        label: 'Purchase value',
        icon: 'purchase',
        color: 'bg-amber-50 text-amber-600',
        note: 'Purchase orders in this period · PKR',
        permission: 'purchases.view',
    },
    {
        key: 'sales_total',
        label: 'Confirmed sales',
        icon: 'sale',
        color: 'bg-emerald-50 text-emerald-600',
        note: 'Confirmed orders in this period · PKR',
        permission: 'sales.view',
    },
    {
        key: 'suppliers',
        label: 'Suppliers',
        icon: 'truck',
        color: 'bg-cyan-50 text-cyan-700',
        note: 'Suppliers linked to your purchases',
        permission: 'suppliers.view',
    },
];
const cards = computed(() =>
    cardDefinitions.filter(
        (card) =>
            auth.hasPermission(card.permission) &&
            (loading.value || dashboard.value?.totals?.[card.key] != null),
    ),
);
const alerts = computed(() =>
    [
        ...(dashboard.value?.stock_alerts?.out_of_stock_items || []),
        ...(dashboard.value?.stock_alerts?.low_stock_items || []),
    ].slice(0, 6),
);
const alertCount = computed(
    () =>
        Number(dashboard.value?.stock_alerts?.low_stock_count || 0) +
        Number(dashboard.value?.stock_alerts?.out_of_stock_count || 0),
);
const stockEntries = computed(() =>
    (dashboard.value?.warehouse_summary || []).reduce(
        (total, item) => total + Number(item.products || 0),
        0,
    ),
);
const healthyBalances = computed(() => Math.max(0, stockEntries.value - alertCount.value));
const healthPercent = computed(() =>
    stockEntries.value ? Math.round((healthyBalances.value / stockEntries.value) * 100) : 0,
);
const healthGradient = computed(() => {
    if (!stockEntries.value) return '#edf1f7';
    const healthy = (healthyBalances.value / stockEntries.value) * 100;
    const low =
        (Number(dashboard.value?.stock_alerts?.low_stock_count || 0) / stockEntries.value) * 100;
    return `conic-gradient(#2563eb 0 ${healthy}%, #fbbf24 ${healthy}% ${healthy + low}%, #fb7185 ${healthy + low}% 100%)`;
});
const maxWarehouseUnits = computed(() =>
    Math.max(1, ...(dashboard.value?.warehouse_summary || []).map((item) => Number(item.units))),
);
const canSeeTrends = computed(
    () => auth.hasPermission('purchases.view') || auth.hasPermission('sales.view'),
);
const availableSeries = computed(() =>
    [
        { key: 'sales', label: 'Sales', color: 'bg-blue-600' },
        { key: 'purchases', label: 'Purchases', color: 'bg-blue-200' },
    ].filter((series) => dashboard.value?.trend?.[series.key] != null),
);
const chartAmounts = computed(() =>
    availableSeries.value
        .filter((series) => visibleSeries[series.key])
        .flatMap((series) => dashboard.value?.trend?.[series.key] || [])
        .map(Number),
);
const chartMaximum = computed(() => {
    const maximum = Math.max(1, ...chartAmounts.value);
    const scale = 10 ** Math.floor(Math.log10(maximum));
    return Math.ceil(maximum / scale) * scale;
});
const hasTrendValues = computed(() => chartAmounts.value.some((amount) => amount > 0));
const quickActions = computed(() =>
    [
        {
            label: 'New purchase',
            to: '/purchases/create',
            permission: 'purchases.create',
            icon: 'purchase',
        },
        {
            label: 'New sales order',
            to: '/sales-orders/create',
            permission: 'sales.create',
            icon: 'sale',
        },
        {
            label: 'New transfer',
            to: '/transfers/create',
            permission: 'transfers.create',
            icon: 'transfer',
        },
    ].filter((action) => auth.hasPermission(action.permission)),
);

async function load() {
    const currentRequest = ++requestId;
    loading.value = true;
    error.value = '';
    try {
        const { data } = await api.get('/api/reports/dashboard', {
            params: { ...filters, warehouse_id: filters.warehouse_id || undefined },
        });
        if (currentRequest !== requestId) return;
        dashboard.value = data;
        updatedAt.value = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    } catch (exception) {
        if (currentRequest === requestId)
            error.value =
                exception.response?.data?.message || 'Dashboard data could not be loaded.';
    } finally {
        if (currentRequest === requestId) loading.value = false;
    }
}
function setPeriod(value) {
    period.value = value;
    filters.from = value === 'month' ? `${today.slice(0, 8)}01` : sixMonthStart;
    filters.to = today;
    load();
}
const money = (value) =>
    `PKR ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const formatValue = (key, value) =>
    value == null
        ? '—'
        : ['inventory_value', 'purchases_total', 'sales_total'].includes(key)
          ? money(value).replace('PKR ', '')
          : Number(value).toLocaleString();
const compact = (value) =>
    Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
const monthLabel = (value) =>
    new Date(`${value}-01T12:00:00`).toLocaleDateString('en', { month: 'short' });
const shortDate = (value) => {
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime())
        ? String(value).slice(0, 10)
        : date.toLocaleDateString('en', { month: 'short', day: 'numeric' });
};
const labelize = (value) => String(value).replaceAll('_', ' ');
onMounted(load);
</script>
