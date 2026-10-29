<template>
    <section class="mx-auto max-w-7xl space-y-5">
        <PageHeader
            title="Customer returns"
            subtitle="Review, record and complete customer return requests"
        >
            <template #actions
                ><router-link
                    v-if="auth.hasPermission('returns.create')"
                    :to="{ name: 'sales-return-create' }"
                    class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary-app px-4 py-2 text-sm font-semibold text-white bg-primary-hover"
                    >Record return</router-link
                ></template
            >
        </PageHeader>
        <div class="filter-bar grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <input
                v-model="filters.search"
                aria-label="Search returns"
                placeholder="Search return, order, customer"
                class="min-h-11 rounded-md border border-slate-300 px-3 text-sm"
                @input="load(1)"
            />
            <select
                v-model="filters.status"
                aria-label="Filter by status"
                class="min-h-11 rounded-md border border-slate-300 bg-white px-3 text-sm"
                @change="load(1)"
            >
                <option value="">All statuses</option>
                <option value="draft">Draft</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <label class="text-xs text-slate-500"
                >From date<input
                    v-model="filters.date_from"
                    type="date"
                    class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-800"
                    @change="load(1)"
            /></label>
            <label class="text-xs text-slate-500"
                >To date<input
                    v-model="filters.date_to"
                    type="date"
                    class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-800"
                    @change="load(1)"
            /></label>
        </div>
        <p v-if="error" role="alert" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
            {{ error }} <button class="underline" @click="load(meta.current_page)">Retry</button>
        </p>
        <div
            v-else-if="loading"
            class="surface-card rounded-lg border border-slate-200 bg-white p-8 text-center text-sm text-slate-500"
        >
            Loading customer returns
        </div>
        <div
            v-else-if="!rows.length"
            class="surface-card rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500"
        >
            No returns match these filters.
        </div>
        <div
            v-else
            class="surface-card overflow-hidden rounded-lg border border-slate-200 bg-white"
        >
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Return</th>
                            <th class="px-4 py-3">Sales order</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Units</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in rows" :key="row.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <router-link
                                    class="font-semibold text-primary-app hover:underline"
                                    :to="{ name: 'sales-return-detail', params: { id: row.id } }"
                                    >{{ row.return_number }}</router-link
                                >
                            </td>
                            <td class="px-4 py-3">{{ row.sales_order?.order_number }}</td>
                            <td class="px-4 py-3">{{ row.sales_order?.customer?.name || '' }}</td>
                            <td class="px-4 py-3">{{ row.return_date }}</td>
                            <td class="px-4 py-3">{{ row.quantity_total }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="row.status" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="divide-y divide-slate-100 md:hidden">
                <router-link
                    v-for="row in rows"
                    :key="row.id"
                    :to="{ name: 'sales-return-detail', params: { id: row.id } }"
                    class="block space-y-2 p-4"
                    ><div class="flex items-center justify-between gap-3">
                        <strong class="text-primary-app">{{ row.return_number }}</strong
                        ><StatusBadge :status="row.status" />
                    </div>
                    <p class="text-sm text-slate-700">
                        {{ row.sales_order?.order_number }}
                        {{ row.sales_order?.customer?.name || 'Customer' }}
                    </p>
                    <div class="flex justify-between text-xs text-slate-500">
                        <span>{{ row.return_date }}</span
                        ><span>{{ row.quantity_total }} units</span>
                    </div></router-link
                >
            </div>
        </div>
        <div
            v-if="meta.last_page > 1"
            class="flex flex-wrap items-center justify-between gap-3 text-sm"
        >
            <span class="text-slate-500"
                >Showing {{ meta.from || 0 }}{{ meta.to || 0 }} of {{ meta.total }}</span
            >
            <div class="flex gap-2">
                <button
                    :disabled="meta.current_page <= 1"
                    class="min-h-10 rounded-md border px-3 disabled:opacity-40"
                    @click="load(meta.current_page - 1)"
                >
                    Previous</button
                ><span class="px-2 py-2">Page {{ meta.current_page }} of {{ meta.last_page }}</span
                ><button
                    :disabled="meta.current_page >= meta.last_page"
                    class="min-h-10 rounded-md border px-3 disabled:opacity-40"
                    @click="load(meta.current_page + 1)"
                >
                    Next
                </button>
            </div>
        </div>
    </section>
</template>
<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../../api/axios';
import { useAuthStore } from '../../stores/auth';
import PageHeader from '../../components/PageHeader.vue';
import StatusBadge from '../../components/StatusBadge.vue';
const auth = useAuthStore();
const rows = ref([]);
const loading = ref(true);
const error = ref('');
const filters = reactive({ search: '', status: '', date_from: '', date_to: '' });
const meta = reactive({ current_page: 1, last_page: 1, from: 0, to: 0, total: 0 });
async function load(page = 1) {
    loading.value = true;
    error.value = '';
    try {
        const response = await api.get('/api/sales-returns', { params: { page, ...filters } });
        rows.value = response.data.data;
        Object.assign(meta, response.data.meta);
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Could not load customer returns.';
    } finally {
        loading.value = false;
    }
}
onMounted(() => load());
</script>
