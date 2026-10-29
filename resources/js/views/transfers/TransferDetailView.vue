<template>
    <section class="mx-auto max-w-6xl space-y-5">
        <PageHeader
            :title="transfer?.transfer_number || 'Warehouse transfer'"
            subtitle="Track stock dispatched, accepted, or returned to its source"
            ><template #actions
                ><router-link
                    :to="{ name: 'transfers' }"
                    class="min-h-11 rounded border px-4 py-2 text-sm"
                    >All transfers</router-link
                ></template
            ></PageHeader
        >
        <p v-if="error" class="rounded bg-red-50 p-3 text-sm text-red-700">
            {{ error }} <button class="underline" @click="load">Retry</button>
        </p>
        <div v-else-if="!transfer" class="rounded-lg bg-white p-8 text-center text-slate-500">
            Loading transfer...
        </div>
        <template v-else>
            <div
                class="surface-card flex flex-col gap-4 rounded-lg border bg-white p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <StatusBadge :status="transfer.status" /><span class="text-sm text-slate-600"
                        >{{ transfer.source_warehouse?.name }} →
                        {{ transfer.destination_warehouse?.name }}</span
                    >
                </div>
                <div class="flex flex-wrap gap-2">
                    <router-link
                        v-if="transfer.allowed_actions.edit"
                        :to="{ name: 'transfer-edit', params: { id: transfer.id } }"
                        class="min-h-10 rounded border px-3 py-2 text-sm"
                        >Edit draft</router-link
                    ><button
                        v-if="transfer.allowed_actions.dispatch"
                        :disabled="busy"
                        class="min-h-10 rounded bg-primary-app px-3 py-2 text-sm font-semibold text-white"
                        @click="confirmAction = 'dispatch'"
                    >
                        Dispatch stock</button
                    ><button
                        v-if="transfer.allowed_actions.receive"
                        :disabled="busy"
                        class="min-h-10 rounded bg-emerald-700 px-3 py-2 text-sm font-semibold text-white"
                        @click="openReceive"
                    >
                        Receive transfer</button
                    ><button
                        v-if="transfer.allowed_actions.cancel"
                        :disabled="busy"
                        class="min-h-10 rounded border border-red-300 px-3 py-2 text-sm text-red-700"
                        @click="confirmAction = 'cancel'"
                    >
                        Cancel draft
                    </button>
                </div>
            </div>
            <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(250px,1fr)]">
                <div class="surface-card overflow-hidden rounded-lg border bg-white">
                    <h2 class="border-b px-4 py-3 font-semibold">Transfer items</h2>
                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase">
                                <tr>
                                    <th class="p-3">Product</th>
                                    <th class="p-3">Dispatched</th>
                                    <th class="p-3">Accepted</th>
                                    <th class="p-3">Returned</th>
                                    <th class="p-3">Available at source</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="item in transfer.items" :key="item.id">
                                    <td class="p-3">
                                        <b>{{ item.product?.name }}</b
                                        ><small class="block text-slate-500">{{
                                            item.product?.sku
                                        }}</small>
                                    </td>
                                    <td class="p-3">{{ item.dispatched_quantity }}</td>
                                    <td class="p-3">{{ item.accepted_quantity }}</td>
                                    <td class="p-3">{{ item.returned_quantity }}</td>
                                    <td class="p-3">{{ availability[item.product_id] ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="divide-y md:hidden">
                        <article
                            v-for="item in transfer.items"
                            :key="item.id"
                            class="space-y-2 p-4 text-sm"
                        >
                            <div>
                                <b>{{ item.product?.name }}</b
                                ><small class="ml-2 text-slate-500">{{ item.product?.sku }}</small>
                            </div>
                            <dl class="grid grid-cols-2 gap-2 text-slate-600">
                                <div>
                                    Dispatched <b>{{ item.dispatched_quantity }}</b>
                                </div>
                                <div>
                                    Accepted <b>{{ item.accepted_quantity }}</b>
                                </div>
                                <div>
                                    Returned <b>{{ item.returned_quantity }}</b>
                                </div>
                                <div>
                                    Source stock <b>{{ availability[item.product_id] ?? '—' }}</b>
                                </div>
                            </dl>
                        </article>
                    </div>
                    <p v-if="transfer.notes" class="border-t p-4 text-sm text-slate-600">
                        {{ transfer.notes }}
                    </p>
                </div>
                <aside class="surface-card rounded-lg border bg-white p-4">
                    <h2 class="mb-4 font-semibold">Transfer timeline</h2>
                    <ol class="space-y-4 border-l-2 border-slate-200 pl-4 text-sm">
                        <li>
                            <b>Created</b>
                            <p class="text-slate-500">
                                {{ transfer.creator?.name }} · {{ date(transfer.created_at) }}
                            </p>
                        </li>
                        <li v-if="transfer.dispatched_at">
                            <b>Dispatched</b>
                            <p class="text-slate-500">
                                {{ transfer.dispatcher?.name }} · {{ date(transfer.dispatched_at) }}
                            </p>
                        </li>
                        <li v-if="transfer.received_at">
                            <b>Received</b>
                            <p class="text-slate-500">
                                {{ transfer.receiver?.name }} · {{ date(transfer.received_at) }}
                            </p>
                        </li>
                        <li v-if="transfer.cancelled_at">
                            <b>Cancelled</b>
                            <p class="text-slate-500">{{ date(transfer.cancelled_at) }}</p>
                        </li>
                    </ol>
                </aside>
            </div></template
        >
        <div
            v-if="receiveOpen && transfer"
            class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-black/40 p-4"
            role="dialog"
            aria-modal="true"
        >
            <form
                class="my-auto w-full max-w-2xl space-y-4 rounded-lg bg-white p-5 shadow-xl"
                @submit.prevent="confirmAction = 'receive'"
            >
                <div class="flex justify-between">
                    <h2 class="text-lg font-semibold">Receive transfer</h2>
                    <button type="button" aria-label="Close" @click="receiveOpen = false">×</button>
                </div>
                <p class="text-sm text-slate-600">
                    For each product, accepted plus returned units must equal dispatched units.
                    Returned items go back to the source warehouse.
                </p>
                <div
                    v-for="line in receiveLines"
                    :key="line.product_id"
                    class="grid grid-cols-1 gap-3 rounded border p-3 sm:grid-cols-[minmax(0,1fr)_120px_120px]"
                >
                    <div class="text-sm">
                        <b>{{ line.product_name }}</b
                        ><small class="block text-slate-500"
                            >Dispatched {{ line.dispatched_quantity }}</small
                        >
                    </div>
                    <label class="text-xs"
                        >Accepted<input
                            v-model.number="line.accepted_quantity"
                            type="number"
                            min="0"
                            :max="line.dispatched_quantity"
                            class="mt-1 min-h-11 w-full rounded border px-3"
                            @input="updateReturned(line)" /></label
                    ><label class="text-xs"
                        >Returned<input
                            v-model.number="line.returned_quantity"
                            type="number"
                            min="0"
                            :max="line.dispatched_quantity"
                            class="mt-1 min-h-11 w-full rounded border px-3"
                    /></label>
                    <p
                        v-if="
                            Number(line.accepted_quantity) + Number(line.returned_quantity) !==
                            line.dispatched_quantity
                        "
                        class="text-xs text-red-700 sm:col-span-3"
                    >
                        Accepted and returned must total {{ line.dispatched_quantity }}.
                    </p>
                </div>
                <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="min-h-11 rounded border px-4"
                        @click="receiveOpen = false"
                    >
                        Close</button
                    ><button
                        :disabled="busy || !reconciled"
                        class="min-h-11 rounded bg-emerald-700 px-4 font-semibold text-white disabled:opacity-50"
                    >
                        Review receipt
                    </button>
                </div>
            </form>
        </div>
        <ConfirmDialog
            :is-open="confirmAction !== '' && confirmAction !== 'receive'"
            :title="dialogTitle"
            :message="dialogMessage"
            :confirm-text="dialogConfirm"
            :is-danger="confirmAction === 'cancel'"
            @confirm="performAction"
            @cancel="confirmAction = ''"
        /><ConfirmDialog
            :is-open="confirmAction === 'receive'"
            title="Confirm transfer receipt"
            message="Post the accepted quantity to the destination and return remaining units to the source?"
            confirm-text="Record receipt"
            :is-danger="false"
            @confirm="performAction"
            @cancel="confirmAction = ''"
        />
    </section>
</template>
<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../api/axios';
import PageHeader from '../../components/PageHeader.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import ConfirmDialog from '../../components/ConfirmDialog.vue';
const route = useRoute();
const transfer = ref(null);
const error = ref('');
const busy = ref(false);
const confirmAction = ref('');
const receiveOpen = ref(false);
const idempotencyKey = ref('');
const receiveLines = ref([]);
const availability = ref({});
const date = (v) => (v ? new Date(v).toLocaleString() : '');
const reconciled = computed(
    () =>
        receiveLines.value.length > 0 &&
        receiveLines.value.every(
            (x) =>
                Number(x.accepted_quantity) + Number(x.returned_quantity) === x.dispatched_quantity,
        ),
);
const dialogTitle = computed(() =>
    confirmAction.value === 'dispatch' ? 'Dispatch transfer' : 'Cancel draft transfer',
);
const dialogMessage = computed(() =>
    confirmAction.value === 'dispatch'
        ? `Dispatch stock from ${transfer.value?.source_warehouse?.name}? Source stock will be reduced.`
        : 'Cancel this draft transfer? Stock has not moved.',
);
const dialogConfirm = computed(() =>
    confirmAction.value === 'dispatch' ? 'Dispatch' : 'Cancel transfer',
);
async function load() {
    error.value = '';
    try {
        transfer.value = (await api.get(`/api/transfers/${route.params.id}`)).data.data;
        const ids = transfer.value.items.map((i) => i.product_id);
        if (ids.length) {
            const r = await api.get('/api/inventory/availability', {
                params: { warehouse_id: transfer.value.source_warehouse.id, product_ids: ids },
            });
            availability.value = Object.fromEntries(
                r.data.data.map((x) => [x.product_id, x.available_quantity]),
            );
        }
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load transfer.';
    }
}
function openReceive() {
    receiveLines.value = transfer.value.items.map((i) => ({
        product_id: i.product_id,
        product_name: i.product?.name,
        dispatched_quantity: i.dispatched_quantity,
        accepted_quantity: i.dispatched_quantity,
        returned_quantity: 0,
    }));
    idempotencyKey.value = crypto.randomUUID();
    receiveOpen.value = true;
}
function updateReturned(line) {
    line.returned_quantity = Math.max(
        0,
        line.dispatched_quantity - Number(line.accepted_quantity || 0),
    );
}
async function performAction() {
    const action = confirmAction.value;
    confirmAction.value = '';
    busy.value = true;
    error.value = '';
    try {
        const payload =
            action === 'receive'
                ? {
                      idempotency_key: idempotencyKey.value,
                      items: receiveLines.value.map((x) => ({
                          product_id: x.product_id,
                          accepted_quantity: Number(x.accepted_quantity),
                          returned_quantity: Number(x.returned_quantity),
                      })),
                  }
                : {};
        await api.post(`/api/transfers/${transfer.value.id}/${action}`, payload);
        receiveOpen.value = false;
        await load();
    } catch (e) {
        error.value = e.response?.data?.message || `Could not ${action} transfer.`;
        if (action === 'receive') receiveOpen.value = true;
    } finally {
        busy.value = false;
    }
}
onMounted(load);
</script>
