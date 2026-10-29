<template>
    <section class="space-y-5">
        <PageHeader
            title="Purchases"
            :subtitle="
                auth.hasPermission('purchases.create')
                    ? 'Create purchase orders and track supplier deliveries'
                    : 'Review assigned warehouse orders and receive deliveries'
            "
        >
            <template #actions>
                <router-link
                    v-if="auth.hasPermission('purchases.create')"
                    :to="{ name: 'purchase-create' }"
                    class="rounded-md bg-primary-app px-4 py-2 text-sm font-semibold text-white bg-primary-hover"
                    >New purchase</router-link
                >
            </template>
        </PageHeader>
        <div class="filter-bar grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
            <input
                v-model="search"
                @input="fetch(1)"
                placeholder="Search purchase or supplier"
                class="min-h-11 rounded-md border border-app px-3 text-sm"
            />
            <select
                v-model="status"
                @change="fetch(1)"
                class="min-h-11 rounded-md border border-app bg-white px-3 text-sm"
            >
                <option value="">All statuses</option>
                <option v-for="item in statuses" :key="item" :value="item">
                    {{ item.replaceAll('_', ' ') }}
                </option>
            </select>
            <label class="text-xs text-slate-500"
                >From<input
                    v-model="dateFrom"
                    type="date"
                    @change="fetch(1)"
                    class="mt-1 min-h-11 w-full rounded-md border border-app px-3 text-sm text-slate-800"
            /></label>
            <label class="text-xs text-slate-500"
                >To<input
                    v-model="dateTo"
                    type="date"
                    @change="fetch(1)"
                    class="mt-1 min-h-11 w-full rounded-md border border-app px-3 text-sm text-slate-800"
            /></label>
        </div>
        <div
            v-if="error"
            class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800"
        >
            {{ error }}
            <button class="ml-2 underline" @click="fetch(page.current_page)">Retry</button>
        </div>
        <div v-else-if="busy" class="rounded-md bg-white p-8 text-center text-sm text-slate-500">
            Loading purchases...
        </div>
        <div
            v-else-if="rows.length === 0"
            class="rounded-md border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500"
        >
            No purchase orders found.
        </div>
        <div v-else class="surface-card overflow-hidden rounded-lg border border-app bg-white">
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Purchase</th>
                            <th class="px-4 py-3">Supplier</th>
                            <th class="px-4 py-3">Warehouse</th>
                            <th class="px-4 py-3">
                                <button class="font-semibold" @click="toggleSort('order_date')">
                                    Order date {{ sortMarker('order_date') }}
                                </button>
                            </th>
                            <th class="px-4 py-3">
                                <button class="font-semibold" @click="toggleSort('grand_total')">
                                    Amount {{ sortMarker('grand_total') }}
                                </button>
                            </th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in rows" :key="row.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <router-link
                                    class="font-semibold text-primary-app hover:underline"
                                    :to="{ name: 'purchase-detail', params: { id: row.id } }"
                                    >{{ row.purchase_number }}</router-link
                                >
                            </td>
                            <td class="px-4 py-3">{{ row.supplier?.name }}</td>
                            <td class="px-4 py-3">{{ row.warehouse?.name }}</td>
                            <td class="px-4 py-3">{{ row.order_date }}</td>
                            <td class="px-4 py-3">PKR {{ formatMoney(row.grand_total) }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="row.status" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="divide-y divide-slate-100 md:hidden">
                <router-link
                    v-for="row in rows"
                    :key="row.id"
                    :to="{ name: 'purchase-detail', params: { id: row.id } }"
                    class="block space-y-2 p-4 hover:bg-slate-50"
                    ><div class="flex items-center justify-between gap-2">
                        <strong class="text-primary-app">{{ row.purchase_number }}</strong
                        ><StatusBadge :status="row.status" />
                    </div>
                    <p class="text-sm text-slate-700">
                        {{ row.supplier?.name }} - {{ row.warehouse?.name }}
                    </p>
                    <div class="flex justify-between text-sm text-slate-500">
                        <span>{{ row.order_date }}</span
                        ><b class="text-slate-800">PKR {{ formatMoney(row.grand_total) }}</b>
                    </div></router-link
                >
            </div>
        </div>
        <Pagination :meta="page" @page-change="fetch" />
    </section>
</template>
<script setup>
import { onMounted, ref, reactive } from 'vue';
import api from '../../api/axios';
import { formatMoney } from '../../api/money';
import { useAuthStore } from '../../stores/auth';
import PageHeader from '../../components/PageHeader.vue';
import Pagination from '../../components/Pagination.vue';
import StatusBadge from '../../components/StatusBadge.vue';
const auth = useAuthStore();
const rows = ref([]);
const busy = ref(true);
const error = ref('');
const search = ref('');
const status = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const sortBy = ref('order_date');
const sortDirection = ref('desc');
const statuses = [
    'draft',
    'pending_approval',
    'approved',
    'partially_received',
    'received',
    'cancelled',
];
const page = reactive({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
function sortMarker(field) {
    return sortBy.value === field ? (sortDirection.value === 'asc' ? '\u2191' : '\u2193') : '';
}
function toggleSort(field) {
    if (sortBy.value === field)
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    else {
        sortBy.value = field;
        sortDirection.value = 'desc';
    }
    fetch(1);
}
async function fetch(pageNumber) {
    busy.value = true;
    error.value = '';
    try {
        const response = await api.get('/api/purchases', {
            params: {
                page: pageNumber,
                search: search.value || undefined,
                status: status.value || undefined,
                date_from: dateFrom.value || undefined,
                date_to: dateTo.value || undefined,
                sort_by: sortBy.value,
                sort_direction: sortDirection.value,
            },
        });
        rows.value = response.data.data;
        Object.assign(page, response.data.meta);
    } catch (err) {
        error.value = err.response?.data?.message || 'Could not load purchase orders.';
    } finally {
        busy.value = false;
    }
}
onMounted(() => fetch(1));
</script>
