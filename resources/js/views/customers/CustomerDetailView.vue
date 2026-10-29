<template>
    <section class="space-y-5">
        <PageHeader
            :title="customer?.name || 'Customer'"
            subtitle="Customer details and sales order history"
            ><template #actions
                ><router-link
                    :to="{ name: 'customers' }"
                    class="min-h-11 rounded-md border border-slate-300 px-4 py-2 text-sm"
                    >All customers</router-link
                ></template
            ></PageHeader
        >
        <p v-if="error" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
            {{ error }} <button class="underline" @click="load">Retry</button>
        </p>
        <div v-else-if="busy" class="rounded-lg bg-white p-8 text-center text-sm text-slate-500">
            Loading customer...
        </div>
        <template v-else-if="customer"
            ><div
                class="surface-card grid gap-3 rounded-lg border border-slate-200 bg-white p-4 text-sm sm:grid-cols-2 lg:grid-cols-4"
            >
                <div>
                    <span class="text-slate-500">Phone</span>
                    <p class="mt-1 font-medium">{{ customer.phone || '—' }}</p>
                </div>
                <div>
                    <span class="text-slate-500">Email</span>
                    <p class="mt-1 break-all font-medium">{{ customer.email || '—' }}</p>
                </div>
                <div>
                    <span class="text-slate-500">Status</span>
                    <p class="mt-1"><StatusBadge :status="customer.status" /></p>
                </div>
                <div>
                    <span class="text-slate-500">Address</span>
                    <p class="mt-1 font-medium">{{ customer.address || '—' }}</p>
                </div>
            </div>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Order history</h2>
                <span class="text-sm text-slate-500">{{ page.total }} orders</span>
            </div>
            <div
                v-if="orders.length"
                class="surface-card overflow-hidden rounded-lg border border-slate-200 bg-white"
            >
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Order</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Warehouse</th>
                                <th class="px-4 py-3">Total</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="order in orders" :key="order.id">
                                <td class="px-4 py-3">
                                    <router-link
                                        class="font-semibold text-primary-app"
                                        :to="{
                                            name: 'sales-order-detail',
                                            params: { id: order.id },
                                        }"
                                        >{{ order.order_number }}</router-link
                                    >
                                </td>
                                <td class="px-4 py-3">{{ order.order_date }}</td>
                                <td class="px-4 py-3">{{ order.warehouse?.name }}</td>
                                <td class="px-4 py-3">PKR {{ formatMoney(order.grand_total) }}</td>
                                <td class="px-4 py-3"><StatusBadge :status="order.status" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="divide-y md:hidden">
                    <router-link
                        v-for="order in orders"
                        :key="order.id"
                        :to="{ name: 'sales-order-detail', params: { id: order.id } }"
                        class="block space-y-2 p-4"
                        ><div class="flex justify-between gap-2">
                            <b class="text-primary-app">{{ order.order_number }}</b
                            ><StatusBadge :status="order.status" />
                        </div>
                        <div class="flex justify-between text-sm text-slate-500">
                            <span>{{ order.order_date }} · {{ order.warehouse?.name }}</span
                            ><b class="text-slate-800">PKR {{ formatMoney(order.grand_total) }}</b>
                        </div></router-link
                    >
                </div>
            </div>
            <p
                v-else
                class="surface-card rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500"
            >
                No sales orders for this customer yet.
            </p>
            <Pagination :meta="page" @page-change="loadOrders"
        /></template>
    </section>
</template>
<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../api/axios';
import { formatMoney } from '../../api/money';
import PageHeader from '../../components/PageHeader.vue';
import Pagination from '../../components/Pagination.vue';
import StatusBadge from '../../components/StatusBadge.vue';
const route = useRoute();
const customer = ref(null);
const orders = ref([]);
const busy = ref(true);
const error = ref('');
const page = reactive({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
async function loadOrders(number = 1) {
    const response = await api.get(`/api/customers/${route.params.id}/orders`, {
        params: { page: number },
    });
    orders.value = response.data.data;
    Object.assign(page, response.data.meta);
}
async function load() {
    busy.value = true;
    error.value = '';
    try {
        customer.value = (await api.get(`/api/customers/${route.params.id}`)).data.data;
        await loadOrders();
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load customer details.';
    } finally {
        busy.value = false;
    }
}
onMounted(load);
</script>
