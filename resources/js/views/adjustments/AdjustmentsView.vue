<template>
    <section class="space-y-5">
        <PageHeader
            title="Stock adjustments"
            subtitle="Review immutable stock corrections and reasons"
            ><template #actions
                ><router-link
                    v-if="auth.hasPermission('inventory.adjust')"
                    :to="{ name: 'stock-adjustment-create' }"
                    class="min-h-11 rounded bg-primary-app px-4 py-2 text-sm font-semibold text-white"
                    >New adjustment</router-link
                ></template
            ></PageHeader
        >
        <div class="filter-bar grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <input
                v-model="search"
                placeholder="Search reference, product, or reason"
                class="min-h-11 rounded border px-3 text-sm"
                @input="fetch(1)"
            /><select
                v-model="reasonCode"
                class="min-h-11 rounded border bg-white px-3 text-sm"
                @change="fetch(1)"
            >
                <option value="">All reason codes</option>
                <option v-for="r in reasons" :key="r" :value="r">
                    {{ r.replaceAll('_', ' ') }}
                </option></select
            ><select
                v-model="warehouseId"
                class="min-h-11 rounded border bg-white px-3 text-sm"
                @change="fetch(1)"
            >
                <option value="">All warehouses</option>
                <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
            </select>
            <div class="grid grid-cols-2 gap-2">
                <input
                    v-model="from"
                    type="date"
                    class="min-h-11 min-w-0 rounded border px-2 text-sm"
                    @change="fetch(1)"
                /><input
                    v-model="to"
                    type="date"
                    class="min-h-11 min-w-0 rounded border px-2 text-sm"
                    @change="fetch(1)"
                />
            </div>
        </div>
        <p v-if="error" class="rounded bg-red-50 p-3 text-sm text-red-700">
            {{ error }} <button class="underline" @click="fetch(page.current_page)">Retry</button>
        </p>
        <div v-else-if="busy" class="rounded bg-white p-8 text-center text-slate-500">
            Loading adjustments...
        </div>
        <div
            v-else-if="!rows.length"
            class="rounded border border-dashed bg-white p-10 text-center text-sm text-slate-500"
        >
            No stock adjustments found.
        </div>
        <div v-else class="surface-card overflow-hidden rounded-lg border bg-white">
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="p-3">Adjustment</th>
                            <th class="p-3">Product</th>
                            <th class="p-3">Warehouse</th>
                            <th class="p-3">Change</th>
                            <th class="p-3">Reason</th>
                            <th class="p-3">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="row in rows" :key="row.id">
                            <td class="p-3">
                                <router-link
                                    class="font-semibold text-primary-app"
                                    :to="{
                                        name: 'stock-adjustment-detail',
                                        params: { id: row.id },
                                    }"
                                    >{{ row.adjustment_number }}</router-link
                                >
                            </td>
                            <td class="p-3">{{ row.product?.name }}</td>
                            <td class="p-3">{{ row.warehouse?.name }}</td>
                            <td
                                class="p-3 font-semibold"
                                :class="
                                    row.quantity_change > 0 ? 'text-emerald-700' : 'text-red-700'
                                "
                            >
                                {{ row.quantity_change > 0 ? '+' : '' }}{{ row.quantity_change }}
                            </td>
                            <td class="max-w-xs p-3">
                                <b>{{ row.reason_code.replaceAll('_', ' ') }}</b
                                ><small class="block truncate text-slate-500">{{
                                    row.reason
                                }}</small>
                            </td>
                            <td class="p-3">{{ date(row.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="divide-y md:hidden">
                <router-link
                    v-for="row in rows"
                    :key="row.id"
                    :to="{ name: 'stock-adjustment-detail', params: { id: row.id } }"
                    class="block space-y-2 p-4"
                    ><div class="flex justify-between gap-2">
                        <b class="text-primary-app">{{ row.adjustment_number }}</b
                        ><b :class="row.quantity_change > 0 ? 'text-emerald-700' : 'text-red-700'"
                            >{{ row.quantity_change > 0 ? '+' : '' }}{{ row.quantity_change }}</b
                        >
                    </div>
                    <p class="text-sm">{{ row.product?.name }} · {{ row.warehouse?.name }}</p>
                    <p class="text-xs text-slate-600">
                        {{ row.reason_code.replaceAll('_', ' ') }} — {{ row.reason }}
                    </p>
                    <small class="text-slate-500">{{ date(row.created_at) }}</small></router-link
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
const auth = useAuthStore();
const rows = ref([]);
const warehouses = ref([]);
const busy = ref(true);
const error = ref('');
const search = ref('');
const reasonCode = ref('');
const warehouseId = ref('');
const from = ref('');
const to = ref('');
const reasons = ['count_correction', 'damage', 'loss', 'found', 'other'];
const page = reactive({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
const date = (v) => (v ? new Date(v).toLocaleDateString() : '');
async function fetch(number = 1) {
    busy.value = true;
    error.value = '';
    try {
        const [r, w] = await Promise.all([
            api.get('/api/stock-adjustments', {
                params: {
                    page: number,
                    search: search.value || undefined,
                    reason_code: reasonCode.value || undefined,
                    warehouse_id: warehouseId.value || undefined,
                    date_from: from.value || undefined,
                    date_to: to.value || undefined,
                },
            }),
            api.get('/api/warehouses'),
        ]);
        rows.value = r.data.data;
        Object.assign(page, r.data.meta);
        warehouses.value = w.data.data;
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load adjustments.';
    } finally {
        busy.value = false;
    }
}
onMounted(() => fetch());
</script>
