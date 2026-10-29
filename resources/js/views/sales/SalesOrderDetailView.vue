<template>
    <section class="mx-auto max-w-6xl space-y-5">
        <PageHeader
            :title="order?.order_number || 'Sales order'"
            subtitle="Review the order, stock status, and confirmation history"
            ><template #actions
                ><router-link
                    :to="{ name: 'sales-orders' }"
                    class="min-h-11 rounded-md border border-slate-300 px-4 py-2 text-sm"
                    >All sales orders</router-link
                ></template
            ></PageHeader
        >
        <p v-if="error" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
            {{ error }} <button class="underline" @click="load">Retry</button>
        </p>
        <div v-else-if="!order" class="rounded-lg bg-white p-8 text-center text-sm text-slate-500">
            Loading sales order...
        </div>
        <template v-else
            ><div
                class="surface-card flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <StatusBadge :status="order.status" /><span class="text-sm text-slate-600"
                        >{{ order.customer?.name }} {{ order.warehouse?.name }}
                        {{ order.order_date }}</span
                    >
                </div>
                <div class="flex flex-wrap gap-2">
                    <router-link
                        v-if="order.allowed_actions.create_return"
                        :to="{ name: 'sales-return-create', query: { sales_order_id: order.id } }"
                        class="min-h-10 rounded-md bg-primary-app px-3 py-2 text-sm font-semibold text-white"
                        >Record return</router-link
                    ><router-link
                        v-if="order.allowed_actions.edit"
                        :to="{ name: 'sales-order-edit', params: { id: order.id } }"
                        class="min-h-10 rounded-md border px-3 py-2 text-sm"
                        >Edit draft</router-link
                    ><button
                        v-if="order.allowed_actions.confirm"
                        :disabled="busy"
                        class="min-h-10 rounded-md bg-emerald-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        @click="confirmAction = 'confirm'"
                    >
                        Confirm order</button
                    ><button
                        v-if="order.allowed_actions.cancel"
                        :disabled="busy"
                        class="min-h-10 rounded-md border border-red-300 px-3 py-2 text-sm text-red-700"
                        @click="confirmAction = 'cancel'"
                    >
                        Cancel draft
                    </button>
                </div>
            </div>
            <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(260px,1fr)]">
                <div
                    class="surface-card overflow-hidden rounded-lg border border-slate-200 bg-white"
                >
                    <h2 class="border-b px-4 py-3 font-semibold">Items</h2>
                    <div class="divide-y divide-slate-100">
                        <div
                            v-for="item in order.items"
                            :key="item.id"
                            class="grid grid-cols-2 gap-2 p-4 text-sm sm:grid-cols-[2fr_1fr_1fr_1fr]"
                        >
                            <div class="col-span-2 sm:col-span-1">
                                <p class="font-medium">{{ item.product?.name }}</p>
                                <p class="text-xs text-slate-500">{{ item.product?.sku }}</p>
                            </div>
                            <p>
                                Sold <b>{{ item.quantity }}</b> Returned
                                <b>{{ item.returned_quantity || 0 }}</b>
                            </p>
                            <p>
                                Available for return
                                <b>{{ item.returnable_quantity ?? item.quantity }}</b>
                            </p>
                            <p>
                                Available <b>{{ availability[item.product_id] ?? '' }}</b>
                            </p>
                            <p class="text-right font-medium">
                                PKR {{ formatMoney(item.line_total) }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="surface-card rounded-lg border border-slate-200 bg-white p-4">
                        <h2 class="mb-3 font-semibold">Order total</h2>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt>Subtotal</dt>
                                <dd>PKR {{ formatMoney(order.subtotal) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt>Discount</dt>
                                <dd>- PKR {{ formatMoney(order.discount_amount) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt>Tax</dt>
                                <dd>PKR {{ formatMoney(order.tax_amount) }}</dd>
                            </div>
                            <div class="flex justify-between border-t pt-2 text-base font-bold">
                                <dt>Total</dt>
                                <dd>PKR {{ formatMoney(order.grand_total) }}</dd>
                            </div>
                        </dl>
                    </div>
                    <div
                        class="surface-card rounded-lg border border-slate-200 bg-white p-4 text-sm"
                    >
                        <h2 class="mb-2 font-semibold">Order details</h2>
                        <p>Created by {{ order.creator?.name || 'Unknown' }}</p>
                        <p v-if="order.confirmer" class="mt-1">
                            Confirmed by {{ order.confirmer.name }}
                        </p>
                        <p v-if="order.confirmed_at" class="mt-1 text-slate-500">
                            {{ formatDate(order.confirmed_at) }}
                        </p>
                        <p v-if="order.notes" class="mt-3 whitespace-pre-wrap text-slate-600">
                            {{ order.notes }}
                        </p>
                    </div>
                </div>
            </div>
            <div
                v-if="order.returns?.length"
                class="surface-card overflow-hidden rounded-lg border border-slate-200 bg-white"
            >
                <h2 class="border-b px-4 py-3 font-semibold">Customer returns</h2>
                <div class="divide-y divide-slate-100">
                    <router-link
                        v-for="returnRow in order.returns"
                        :key="returnRow.id"
                        :to="{ name: 'sales-return-detail', params: { id: returnRow.id } }"
                        class="flex flex-wrap items-center justify-between gap-2 p-4 text-sm hover:bg-slate-50"
                    >
                        <span class="font-semibold text-primary-app">{{
                            returnRow.return_number
                        }}</span>
                        <span
                            >{{ returnRow.return_date }} {{ returnRow.quantity_total }} units
                            {{ returnRow.status }}</span
                        >
                    </router-link>
                </div>
            </div>
        </template>
        <ConfirmDialog
            :is-open="confirmAction !== ''"
            :title="dialogTitle"
            :message="dialogMessage"
            :confirm-text="dialogButton"
            :is-danger="confirmAction === 'cancel'"
            @confirm="performAction"
            @cancel="confirmAction = ''"
        />
    </section>
</template>
<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../api/axios';
import { formatMoney } from '../../api/money';
import PageHeader from '../../components/PageHeader.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import ConfirmDialog from '../../components/ConfirmDialog.vue';
const route = useRoute();
const order = ref(null);
const error = ref('');
const busy = ref(false);
const confirmAction = ref('');
const availability = ref({});
const dialogTitle = computed(() =>
    confirmAction.value === 'confirm' ? 'Confirm sales order' : 'Cancel draft order',
);
const dialogMessage = computed(() =>
    confirmAction.value === 'confirm'
        ? `Confirm ${order.value?.order_number}? Stock will be deducted from ${order.value?.warehouse?.name}.`
        : 'Cancel this draft sales order? No stock has been moved.',
);
const dialogButton = computed(() =>
    confirmAction.value === 'confirm' ? 'Confirm order' : 'Cancel order',
);
const formatDate = (value) => (value ? new Date(value).toLocaleString() : '');
async function load() {
    error.value = '';
    try {
        order.value = (await api.get(`/api/sales-orders/${route.params.id}`)).data.data;
        const ids = order.value.items.map((item) => item.product_id);
        if (ids.length) {
            const response = await api.get('/api/inventory/availability', {
                params: { warehouse_id: order.value.warehouse.id, product_ids: ids },
            });
            availability.value = Object.fromEntries(
                response.data.data.map((item) => [item.product_id, item.available_quantity]),
            );
        }
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load sales order.';
    }
}
async function performAction() {
    const action = confirmAction.value;
    confirmAction.value = '';
    busy.value = true;
    error.value = '';
    try {
        await api.post(`/api/sales-orders/${order.value.id}/${action}`);
        await load();
    } catch (e) {
        error.value = e.response?.data?.message || `Could not ${action} this order.`;
    } finally {
        busy.value = false;
    }
}
onMounted(load);
</script>
