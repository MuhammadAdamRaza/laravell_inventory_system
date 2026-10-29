<template>
    <section class="space-y-5">
        <PageHeader title="Sales orders" subtitle="Create customer orders and track stock dispatch"
            ><template #actions
                ><router-link
                    v-if="auth.hasPermission('sales.create')"
                    :to="{ name: 'sales-order-create' }"
                    class="min-h-11 rounded-md bg-primary-app px-4 py-2 text-sm font-semibold text-white bg-primary-hover"
                    >New sales order</router-link
                ></template
            ></PageHeader
        >
        <div class="filter-bar grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <input
                v-model="search"
                placeholder="Search order or customer"
                class="min-h-11 rounded-md border border-app px-3 text-sm"
                @input="fetch(1)"
            /><select
                v-model="status"
                class="min-h-11 rounded-md border border-app bg-white px-3 text-sm"
                @change="fetch(1)"
            >
                <option value="">All statuses</option>
                <option v-for="value in statuses" :key="value" :value="value">
                    {{ value.replaceAll('_', ' ') }}
                </option></select
            ><label class="text-xs text-slate-500"
                >From<input
                    v-model="dateFrom"
                    type="date"
                    class="mt-1 min-h-11 w-full rounded-md border border-app px-3 text-sm text-slate-800"
                    @change="fetch(1)" /></label
            ><label class="text-xs text-slate-500"
                >To<input
                    v-model="dateTo"
                    type="date"
                    class="mt-1 min-h-11 w-full rounded-md border border-app px-3 text-sm text-slate-800"
                    @change="fetch(1)"
            /></label>
        </div>
        <p v-if="error" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
            {{ error }} <button class="underline" @click="fetch(page.current_page)">Retry</button>
        </p>
        <div v-else-if="busy" class="rounded-lg bg-white p-8 text-center text-sm text-slate-500">
            Loading sales orders...
        </div>
        <div
            v-else-if="!rows.length"
            class="surface-card rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500"
        >
            No sales orders found.
        </div>
        <div v-else class="surface-card overflow-hidden rounded-lg border border-app bg-white">
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Dispatch warehouse</th>
                            <th class="px-4 py-3">
                                <button @click="sort('order_date')">
                                    Date {{ marker('order_date') }}
                                </button>
                            </th>
                            <th class="px-4 py-3">
                                <button @click="sort('grand_total')">
                                    Total {{ marker('grand_total') }}
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
                                    :to="{ name: 'sales-order-detail', params: { id: row.id } }"
                                    >{{ row.order_number }}</router-link
                                >
                            </td>
                            <td class="px-4 py-3">{{ row.customer?.name }}</td>
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
                    :to="{ name: 'sales-order-detail', params: { id: row.id } }"
                    class="block space-y-2 p-4"
                    ><div class="flex items-center justify-between gap-2">
                        <strong class="text-primary-app">{{ row.order_number }}</strong
                        ><StatusBadge :status="row.status" />
                    </div>
                    <p class="text-sm text-slate-700">
                        {{ row.customer?.name }} · {{ row.warehouse?.name }}
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
import { onMounted, reactive, ref } from 'vue';
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
const direction = ref('desc');
const statuses = ['draft', 'confirmed', 'cancelled'];
const page = reactive({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
function marker(field) {
    return sortBy.value === field ? (direction.value === 'asc' ? '↑' : '↓') : '';
}
function sort(field) {
    if (sortBy.value === field) direction.value = direction.value === 'asc' ? 'desc' : 'asc';
    else {
        sortBy.value = field;
        direction.value = 'desc';
    }
    fetch(1);
}
async function fetch(number = 1) {
    busy.value = true;
    error.value = '';
    try {
        const response = await api.get('/api/sales-orders', {
            params: {
                page: number,
                search: search.value || undefined,
                status: status.value || undefined,
                date_from: dateFrom.value || undefined,
                date_to: dateTo.value || undefined,
                sort_by: sortBy.value,
                sort_direction: direction.value,
            },
        });
        rows.value = response.data.data;
        Object.assign(page, response.data.meta);
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load sales orders.';
    } finally {
        busy.value = false;
    }
}
onMounted(() => fetch());
</script>
