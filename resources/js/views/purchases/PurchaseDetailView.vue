<template>
    <section class="mx-auto max-w-6xl space-y-5">
        <PageHeader
            :title="purchase?.purchase_number || 'Purchase order'"
            subtitle="Review items, delivery progress, and approval history"
        >
            <template #actions
                ><router-link
                    :to="{ name: 'purchases' }"
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm"
                    >All purchases</router-link
                ></template
            >
        </PageHeader>
        <div v-if="error" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
            {{ error }} <button @click="load" class="underline">Retry</button>
        </div>
        <div v-else-if="!purchase" class="rounded-md bg-white p-8 text-center text-slate-500">
            Loading purchase...
        </div>
        <template v-else>
            <div
                class="surface-card flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <StatusBadge :status="purchase.status" /><span class="text-sm text-slate-600"
                        >Ordered {{ purchase.order_date }} | {{ purchase.supplier?.name }} |
                        {{ purchase.warehouse?.name }}</span
                    >
                </div>
                <div class="flex flex-wrap gap-2">
                    <router-link
                        v-if="purchase.allowed_actions.edit"
                        :to="{ name: 'purchase-edit', params: { id: purchase.id } }"
                        class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        >Edit draft</router-link
                    >
                    <button
                        v-if="purchase.allowed_actions.submit"
                        :disabled="busy"
                        @click="perform('submit')"
                        class="rounded-md bg-primary-app px-3 py-2 text-sm font-semibold text-white"
                    >
                        Submit for approval
                    </button>
                    <button
                        v-if="purchase.allowed_actions.approve"
                        :disabled="busy"
                        @click="confirm('approve')"
                        class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-semibold text-white"
                    >
                        Approve
                    </button>
                    <button
                        v-if="purchase.allowed_actions.reject"
                        :disabled="busy"
                        @click="reject"
                        class="rounded-md border border-amber-400 px-3 py-2 text-sm text-amber-800"
                    >
                        Return to draft
                    </button>
                    <button
                        v-if="purchase.allowed_actions.receive"
                        :disabled="busy"
                        @click="openReceive"
                        class="rounded-md bg-primary-app px-3 py-2 text-sm font-semibold text-white"
                    >
                        Receive stock
                    </button>
                    <button
                        v-if="purchase.allowed_actions.cancel"
                        :disabled="busy"
                        @click="confirm('cancel')"
                        class="rounded-md border border-red-300 px-3 py-2 text-sm text-red-700"
                    >
                        Cancel order
                    </button>
                </div>
            </div>
            <div
                v-if="purchase.rejection_reason"
                class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"
            >
                Returned for changes: {{ purchase.rejection_reason }}
            </div>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(250px,1fr)]">
                <div
                    class="surface-card overflow-hidden rounded-lg border border-slate-200 bg-white"
                >
                    <h2 class="border-b border-slate-200 px-4 py-3 font-semibold">Items</h2>
                    <div class="divide-y divide-slate-100">
                        <div
                            v-for="item in purchase.items"
                            :key="item.id"
                            class="grid grid-cols-2 gap-2 p-4 text-sm sm:grid-cols-[2fr_1fr_1fr_1fr_1fr]"
                        >
                            <div class="col-span-2 sm:col-span-1">
                                <p class="font-medium">{{ item.product?.name }}</p>
                                <p class="text-xs text-slate-500">{{ item.product?.sku }}</p>
                            </div>
                            <p>
                                Ordered <b>{{ item.ordered_quantity }}</b>
                            </p>
                            <p>
                                Received <b>{{ item.received_quantity }}</b>
                            </p>
                            <p>
                                Remaining <b>{{ item.remaining_quantity }}</b>
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
                                <dd>PKR {{ formatMoney(purchase.subtotal) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt>Discount</dt>
                                <dd>- PKR {{ formatMoney(purchase.discount_amount) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt>Tax</dt>
                                <dd>PKR {{ formatMoney(purchase.tax_amount) }}</dd>
                            </div>
                            <div class="flex justify-between border-t pt-2 text-base font-bold">
                                <dt>Total</dt>
                                <dd>PKR {{ formatMoney(purchase.grand_total) }}</dd>
                            </div>
                        </dl>
                    </div>
                    <div
                        class="surface-card rounded-lg border border-slate-200 bg-white p-4 text-sm"
                    >
                        <h2 class="mb-2 font-semibold">Delivery</h2>
                        <p>Expected: {{ purchase.expected_date || 'Not set' }}</p>
                        <p class="mt-1 text-slate-500">
                            Created by {{ purchase.creator?.name || 'Unknown' }}
                        </p>
                        <p v-if="purchase.approver" class="mt-1 text-slate-500">
                            Approved by {{ purchase.approver.name }}
                        </p>
                    </div>
                </div>
            </div>
            <div class="surface-card rounded-lg border border-slate-200 bg-white p-4">
                <h2 class="mb-3 font-semibold">Receipts</h2>
                <p v-if="!purchase.receipts?.length" class="text-sm text-slate-500">
                    No deliveries recorded yet.
                </p>
                <div
                    v-for="receipt in purchase.receipts"
                    :key="receipt.id"
                    class="border-t py-3 text-sm"
                >
                    <div class="flex flex-wrap justify-between gap-2">
                        <b>{{ receipt.receipt_number }}</b
                        ><span
                            >{{ formatDate(receipt.received_at) }} | {{ receipt.warehouse?.name }} |
                            {{ receipt.receiver?.name }}</span
                        >
                    </div>
                    <p class="mt-1 text-slate-600">
                        {{
                            receipt.items
                                .map((line) => line.product_name + ' x ' + line.quantity_received)
                                .join(', ')
                        }}
                    </p>
                </div>
            </div>
        </template>
        <div
            v-if="receiveOpen && purchase"
            class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-black/40 p-4"
            role="dialog"
            aria-modal="true"
        >
            <form
                class="my-auto w-full max-w-xl space-y-4 rounded-lg bg-white p-5 shadow-xl"
                @submit.prevent="confirm('receive')"
            >
                <div class="flex justify-between">
                    <h2 class="text-lg font-semibold">Receive delivery</h2>
                    <button type="button" aria-label="Close" @click="receiveOpen = false">X</button>
                </div>
                <p class="text-sm text-slate-600">
                    Enter delivered quantities. Stock will be added to
                    {{ purchase.warehouse?.name }}.
                </p>
                <label
                    v-for="item in purchase.items.filter((line) => line.remaining_quantity > 0)"
                    :key="item.id"
                    class="grid grid-cols-1 gap-2 border-t py-3 text-sm sm:grid-cols-[1fr_130px]"
                    ><span
                        >{{ item.product?.name
                        }}<small class="block text-slate-500"
                            >Remaining: {{ item.remaining_quantity }}</small
                        ></span
                    ><input
                        v-model="receiveQuantities[item.id]"
                        type="number"
                        min="0"
                        :max="item.remaining_quantity"
                        step="1"
                        placeholder="0"
                        class="min-h-11 rounded-md border border-slate-300 px-3"
                /></label>
                <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-md border px-4 py-2"
                        @click="receiveOpen = false"
                    >
                        Close</button
                    ><button
                        :disabled="busy"
                        class="rounded-md bg-primary-app px-4 py-2 font-semibold text-white"
                    >
                        Review receipt
                    </button>
                </div>
            </form>
        </div>
        <ConfirmDialog
            :is-open="confirmAction !== ''"
            :title="dialogTitle"
            :message="dialogMessage"
            :confirm-text="dialogConfirm"
            :is-danger="confirmAction === 'cancel'"
            @confirm="performConfirmed"
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
const purchase = ref(null);
const error = ref('');
const busy = ref(false);
const receiveOpen = ref(false);
const confirmAction = ref('');
const receiveQuantities = ref({});
const idempotencyKey = ref('');
const dialogTitle = computed(
    () =>
        ({
            approve: 'Approve purchase',
            cancel: 'Cancel purchase',
            receive: 'Record stock receipt',
        })[confirmAction.value] || 'Confirm',
);
const dialogMessage = computed(
    () =>
        ({
            approve: 'Approve this purchase order?',
            cancel: 'Cancel this order? This cannot be undone.',
            receive: 'Post the entered quantities into inventory?',
        })[confirmAction.value] || '',
);
const dialogConfirm = computed(
    () =>
        ({ approve: 'Approve', cancel: 'Cancel order', receive: 'Record receipt' })[
            confirmAction.value
        ] || 'Confirm',
);
const formatDate = (value) => (value ? new Date(value).toLocaleString() : '');
async function load() {
    error.value = '';
    try {
        purchase.value = (await api.get('/api/purchases/' + route.params.id)).data.data;
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load purchase.';
    }
}
function confirm(action) {
    confirmAction.value = action;
}
function openReceive() {
    error.value = '';
    receiveQuantities.value = {};
    idempotencyKey.value = crypto.randomUUID();
    receiveOpen.value = true;
}
async function reject() {
    const reason = window.prompt('Reason to return this purchase for changes:');
    if (reason?.trim()) await perform('reject', { reason: reason.trim() });
}
async function performConfirmed() {
    const action = confirmAction.value;
    confirmAction.value = '';
    await perform(action);
}
async function perform(action, extra = {}) {
    busy.value = true;
    try {
        let payload = extra;
        if (action === 'receive') {
            const items = purchase.value.items
                .filter((line) => Number(receiveQuantities.value[line.id] || 0) > 0)
                .map((line) => ({
                    purchase_item_id: line.id,
                    quantity_received: Number(receiveQuantities.value[line.id]),
                }));
            if (!items.length) {
                error.value = 'Enter at least one received quantity.';
                return;
            }
            payload = {
                idempotency_key: idempotencyKey.value,
                warehouse_id: purchase.value.warehouse.id,
                items,
            };
        }
        await api.post('/api/purchases/' + purchase.value.id + '/' + action, payload);
        receiveOpen.value = false;
        receiveQuantities.value = {};
        idempotencyKey.value = '';
        await load();
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not update this purchase.';
    } finally {
        busy.value = false;
    }
}
onMounted(load);
</script>
