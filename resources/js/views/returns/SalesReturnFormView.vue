<template>
    <section class="mx-auto max-w-5xl space-y-5">
        <PageHeader
            :title="isEditing ? 'Edit return draft' : 'Record customer return'"
            subtitle="Select returned order lines and record their condition"
            ><template #actions
                ><router-link
                    :to="{ name: 'sales-returns' }"
                    class="inline-flex min-h-11 items-center rounded-md border border-slate-300 px-4 py-2 text-sm"
                    >Back to returns</router-link
                ></template
            ></PageHeader
        >
        <p v-if="error" role="alert" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
            {{ error }}
        </p>
        <div
            v-if="loading"
            class="surface-card rounded-lg border bg-white p-8 text-center text-sm text-slate-500"
        >
            Loading return details
        </div>
        <form v-else class="space-y-5" @submit.prevent="save">
            <div
                class="surface-card grid gap-4 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-2"
            >
                <label v-if="!isEditing" class="text-sm font-medium text-slate-700"
                    >Confirmed sales order <span class="text-red-600">*</span
                    ><select
                        v-model="form.sales_order_id"
                        required
                        class="mt-1 min-h-11 w-full rounded-md border border-slate-300 bg-white px-3"
                        @change="loadOrderLines"
                    >
                        <option value="">Choose an order</option>
                        <option v-for="order in orders" :key="order.id" :value="order.id">
                            {{ order.order_number }} {{ order.customer?.name }}
                        </option>
                    </select></label
                >
                <div v-else class="text-sm text-slate-700">
                    <span class="font-medium">Sales order</span>
                    <p class="mt-2">{{ currentOrderLabel }}</p>
                </div>
                <label class="text-sm font-medium text-slate-700"
                    >Return date <span class="text-red-600">*</span
                    ><input
                        v-model="form.return_date"
                        type="date"
                        required
                        class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3"
                /></label>
                <label class="text-sm font-medium text-slate-700 sm:col-span-2"
                    >Summary reason<textarea
                        v-model="form.reason_summary"
                        rows="2"
                        maxlength="5000"
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                        placeholder="Optional notes about this return"
                    ></textarea>
                </label>
            </div>
            <div class="surface-card overflow-hidden rounded-lg border border-slate-200 bg-white">
                <div class="border-b px-4 py-3">
                    <h2 class="font-semibold text-slate-900">Returned items</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Only quantities not already returned or reserved in another draft can be
                        selected.
                    </p>
                </div>
                <div v-if="!lines.length" class="p-6 text-sm text-slate-500">
                    Choose a confirmed order to view returnable items.
                </div>
                <div v-else class="divide-y divide-slate-100">
                    <div
                        v-for="line in lines"
                        :key="line.sales_order_item_id"
                        class="space-y-4 p-4"
                    >
                        <label class="flex items-start gap-3"
                            ><input
                                v-model="line.selected"
                                type="checkbox"
                                class="mt-1 size-4 rounded border-slate-300 text-primary-app"
                            /><span class="min-w-0 flex-1"
                                ><span class="block font-medium">{{ line.name }}</span
                                ><span class="block text-xs text-slate-500"
                                    >{{ line.sku }} Sold {{ line.quantity_sold }} Returned
                                    {{ line.returned_quantity }} Available
                                    {{ line.returnable_quantity }} {{ line.unit }}</span
                                ></span
                            ></label
                        >
                        <div v-if="line.selected" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <label class="text-xs font-medium text-slate-600"
                                >Quantity<input
                                    v-model.number="line.quantity_returned"
                                    type="number"
                                    min="1"
                                    :max="line.returnable_quantity"
                                    required
                                    class="mt-1 min-h-10 w-full rounded-md border border-slate-300 px-3 text-sm" /></label
                            ><label class="text-xs font-medium text-slate-600"
                                >Reason<select
                                    v-model="line.reason"
                                    required
                                    class="mt-1 min-h-10 w-full rounded-md border border-slate-300 bg-white px-2 text-sm"
                                >
                                    <option value="">Choose reason</option>
                                    <option value="damaged_in_transit">Damaged in transit</option>
                                    <option value="wrong_item">Wrong item supplied</option>
                                    <option value="customer_changed_mind">
                                        Customer changed mind
                                    </option>
                                    <option value="defective">Defective</option>
                                    <option value="other">Other</option>
                                </select></label
                            ><label class="text-xs font-medium text-slate-600"
                                >Condition<select
                                    v-model="line.condition"
                                    required
                                    class="mt-1 min-h-10 w-full rounded-md border border-slate-300 bg-white px-2 text-sm"
                                >
                                    <option value="">Choose condition</option>
                                    <option value="new_unopened">New, unopened</option>
                                    <option value="opened">Opened</option>
                                    <option value="damaged">Damaged</option>
                                    <option value="defective">Defective</option>
                                </select></label
                            ><label
                                class="flex min-h-10 items-center gap-2 self-end pb-2 text-sm text-slate-700"
                                ><input
                                    v-model="line.restock_eligible"
                                    type="checkbox"
                                    :disabled="!['new_unopened', 'opened'].includes(line.condition)"
                                    class="size-4 rounded border-slate-300 text-primary-app"
                                />Return to sellable stock</label
                            >
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <router-link
                    :to="{ name: 'sales-returns' }"
                    class="inline-flex min-h-11 items-center justify-center rounded-md border px-4 text-sm"
                    >Cancel</router-link
                ><button
                    :disabled="saving || !selectedLines.length"
                    class="min-h-11 rounded-md bg-primary-app px-5 text-sm font-semibold text-white disabled:opacity-50"
                >
                    {{ saving ? 'Saving' : isEditing ? 'Save draft' : 'Create return draft' }}
                </button>
            </div>
        </form>
    </section>
</template>
<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../api/axios';
import PageHeader from '../../components/PageHeader.vue';
const route = useRoute();
const router = useRouter();
const isEditing = computed(() => Boolean(route.params.id));
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const orders = ref([]);
const lines = ref([]);
const currentOrderLabel = ref('');
const form = reactive({
    sales_order_id: '',
    return_date: new Date().toISOString().slice(0, 10),
    reason_summary: '',
});
const selectedLines = computed(() => lines.value.filter((line) => line.selected));
async function loadOrderLines() {
    lines.value = [];
    if (!form.sales_order_id) return;
    try {
        const response = await api.get(
            '/api/sales-orders/' + form.sales_order_id + '/returnable-items',
        );
        lines.value = response.data.data.items.map((line) => ({
            ...line,
            selected: false,
            quantity_returned: 1,
            reason: '',
            condition: '',
            restock_eligible: false,
        }));
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Could not load returnable order items.';
    }
}
async function initialize() {
    loading.value = true;
    try {
        if (isEditing.value) {
            const response = await api.get('/api/sales-returns/' + route.params.id);
            const record = response.data.data;
            form.sales_order_id = String(record.sales_order_id);
            currentOrderLabel.value =
                (record.sales_order?.order_number || '') +
                '  ' +
                (record.sales_order?.customer?.name || '');
            form.return_date = record.return_date;
            form.reason_summary = record.reason_summary || '';
            const available = await api.get(
                '/api/sales-orders/' + record.sales_order_id + '/returnable-items',
                { params: { exclude_return_id: record.id } },
            );
            lines.value = available.data.data.items.map((line) => {
                const saved = record.items.find(
                    (item) => item.sales_order_item_id === line.sales_order_item_id,
                );
                return {
                    ...line,
                    selected: Boolean(saved),
                    quantity_returned: saved?.quantity_returned || 1,
                    reason: saved?.reason || '',
                    condition: saved?.condition || '',
                    restock_eligible: saved?.restock_eligible || false,
                };
            });
        } else {
            const response = await api.get('/api/sales-orders', {
                params: { status: 'confirmed', per_page: 100 },
            });
            orders.value = response.data.data.filter((order) => order.status === 'confirmed');
            if (route.query.sales_order_id) {
                form.sales_order_id = String(route.query.sales_order_id);
                await loadOrderLines();
            }
        }
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Could not load return form.';
    } finally {
        loading.value = false;
    }
}
async function save() {
    error.value = '';
    saving.value = true;
    const payload = {
        return_date: form.return_date,
        reason_summary: form.reason_summary || null,
        items: selectedLines.value.map((line) => ({
            sales_order_item_id: line.sales_order_item_id,
            quantity_returned: Number(line.quantity_returned),
            reason: line.reason,
            condition: line.condition,
            restock_eligible: line.restock_eligible,
        })),
    };
    try {
        const response = isEditing.value
            ? await api.put('/api/sales-returns/' + route.params.id, payload)
            : await api.post('/api/sales-returns', {
                  ...payload,
                  sales_order_id: Number(form.sales_order_id),
              });
        await router.push({ name: 'sales-return-detail', params: { id: response.data.data.id } });
    } catch (exception) {
        error.value =
            exception.response?.data?.message ||
            Object.values(exception.response?.data?.errors || {})
                .flat()
                .join(' ') ||
            'Could not save this return.';
    } finally {
        saving.value = false;
    }
}
onMounted(initialize);
</script>
