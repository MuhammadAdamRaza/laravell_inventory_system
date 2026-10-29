<template>
    <section class="space-y-5">
        <PageHeader
            title="Warehouse transfers"
            subtitle="Move stock between warehouses and track each dispatch"
            ><template #actions
                ><router-link
                    v-if="auth.hasPermission('transfers.create')"
                    :to="{ name: 'transfer-create' }"
                    class="min-h-11 rounded-md bg-primary-app px-4 py-2 text-sm font-semibold text-white"
                    >New transfer</router-link
                ></template
            ></PageHeader
        >
        <div class="filter-bar grid gap-3 sm:grid-cols-2 xl:grid-cols-7">
            <input
                v-model="search"
                placeholder="Search transfer number"
                class="min-h-11 rounded-md border px-3 text-sm"
                @input="fetch(1)"
            /><select
                v-model="status"
                class="min-h-11 rounded-md border bg-white px-3 text-sm"
                @change="fetch(1)"
            >
                <option value="">All statuses</option>
                <option v-for="value in statuses" :key="value" :value="value">
                    {{ value.replace('_', ' ') }}
                </option></select
            ><select
                v-model="source"
                class="min-h-11 rounded-md border bg-white px-3 text-sm"
                @change="fetch(1)"
            >
                <option value="">All sources</option>
                <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">
                    {{ wh.name }}
                </option></select
            ><select
                v-model="destination"
                class="min-h-11 rounded-md border bg-white px-3 text-sm"
                @change="fetch(1)"
            >
                <option value="">All destinations</option>
                <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">
                    {{ wh.name }}
                </option></select
            ><label class="text-xs text-slate-500"
                >From<input
                    v-model="dateFrom"
                    type="date"
                    class="mt-1 min-h-11 w-full rounded-md border px-3 text-sm"
                    @change="fetch(1)" /></label
            ><label class="text-xs text-slate-500"
                >To<input
                    v-model="dateTo"
                    type="date"
                    class="mt-1 min-h-11 w-full rounded-md border px-3 text-sm"
                    @change="fetch(1)"
            /></label>
        </div>
        <p v-if="error" class="rounded bg-red-50 p-3 text-sm text-red-700">
            {{ error }} <button class="underline" @click="fetch(page.current_page)">Retry</button>
        </p>
        <div v-else-if="busy" class="rounded-lg bg-white p-8 text-center text-sm text-slate-500">
            Loading transfers...
        </div>
        <div
            v-else-if="!rows.length"
            class="surface-card rounded-lg border border-dashed bg-white p-10 text-center text-sm text-slate-500"
        >
            No transfers found.
        </div>
        <div v-else class="surface-card overflow-hidden rounded-lg border border-app bg-white">
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Transfer</th>
                            <th class="px-4 py-3">Source</th>
                            <th class="px-4 py-3">Destination</th>
                            <th class="px-4 py-3">
                                <button @click="sortDate">
                                    Created {{ sortDirection === 'desc' ? '?' : '?' }}
                                </button>
                            </th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="row in rows" :key="row.id">
                            <td class="px-4 py-3">
                                <router-link
                                    class="font-semibold text-primary-app"
                                    :to="{ name: 'transfer-detail', params: { id: row.id } }"
                                    >{{ row.transfer_number }}</router-link
                                >
                            </td>
                            <td class="px-4 py-3">{{ row.source_warehouse?.name }}</td>
                            <td class="px-4 py-3">{{ row.destination_warehouse?.name }}</td>
                            <td class="px-4 py-3">{{ date(row.created_at) }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="row.status" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="divide-y md:hidden">
                <router-link
                    v-for="row in rows"
                    :key="row.id"
                    :to="{ name: 'transfer-detail', params: { id: row.id } }"
                    class="block space-y-2 p-4"
                    ><div class="flex justify-between gap-2">
                        <b class="text-primary-app">{{ row.transfer_number }}</b
                        ><StatusBadge :status="row.status" />
                    </div>
                    <p class="text-sm text-slate-700">
                        {{ row.source_warehouse?.name }} → {{ row.destination_warehouse?.name }}
                    </p>
                    <p class="text-xs text-slate-500">{{ date(row.created_at) }}</p></router-link
                >
            </div>
        </div>
        <Pagination :meta="page" @page-change="fetch" />
    </section>
</template>
<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../../api/axios';
import { useAuthStore } from '../../stores/auth';
import PageHeader from '../../components/PageHeader.vue';
import Pagination from '../../components/Pagination.vue';
import StatusBadge from '../../components/StatusBadge.vue';
const auth = useAuthStore();
const rows = ref([]);
const warehouses = ref([]);
const search = ref('');
const status = ref('');
const source = ref('');
const destination = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const sortDirection = ref('desc');
const busy = ref(true);
const error = ref('');
const statuses = ['draft', 'in_transit', 'completed', 'cancelled'];
const page = reactive({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
const date = (value) => (value ? new Date(value).toLocaleDateString() : '');
function sortDate() {
    sortDirection.value = sortDirection.value === 'desc' ? 'asc' : 'desc';
    fetch(1);
}
async function fetch(number = 1) {
    busy.value = true;
    error.value = '';
    try {
        const [response, wh] = await Promise.all([
            api.get('/api/transfers', {
                params: {
                    page: number,
                    search: search.value || undefined,
                    status: status.value || undefined,
                    source_warehouse_id: source.value || undefined,
                    destination_warehouse_id: destination.value || undefined,
                    date_from: dateFrom.value || undefined,
                    date_to: dateTo.value || undefined,
                    sort_by: 'created_at',
                    sort_direction: sortDirection.value,
                },
            }),
            api.get('/api/warehouses'),
        ]);
        rows.value = response.data.data;
        Object.assign(page, response.data.meta);
        warehouses.value = wh.data.data;
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load transfers.';
    } finally {
        busy.value = false;
    }
}
onMounted(() => fetch());
</script>
