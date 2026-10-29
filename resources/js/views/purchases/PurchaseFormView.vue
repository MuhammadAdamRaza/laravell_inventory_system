<template>
    <section class="mx-auto max-w-5xl space-y-5">
        <PageHeader
            :title="isEdit ? 'Edit purchase' : 'New purchase'"
            subtitle="Choose a supplier and warehouse, then add the products you need to order."
        >
            <template #actions
                ><router-link
                    :to="{ name: 'purchases' }"
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm"
                    >Back to purchases</router-link
                ></template
            >
        </PageHeader>
        <div v-if="error" class="rounded-md bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>
        <form class="space-y-5" @submit.prevent="save">
            <div
                class="surface-card grid grid-cols-1 gap-4 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-2"
            >
                <label class="space-y-1 text-sm font-medium"
                    >Supplier<select
                        v-model="form.supplier_id"
                        required
                        class="min-h-11 w-full rounded-md border border-slate-300 px-3"
                    >
                        <option value="">Select supplier</option>
                        <option v-for="s in options.suppliers" :key="s.id" :value="s.id">
                            {{ s.company_name || s.name }}
                        </option></select
                    ><small class="text-red-600">{{ fieldError('supplier_id') }}</small></label
                >
                <label class="space-y-1 text-sm font-medium"
                    >Warehouse<select
                        v-model="form.warehouse_id"
                        required
                        class="min-h-11 w-full rounded-md border border-slate-300 px-3"
                    >
                        <option value="">Select warehouse</option>
                        <option v-for="w in options.warehouses" :key="w.id" :value="w.id">
                            {{ w.name }}
                        </option></select
                    ><small class="text-red-600">{{ fieldError('warehouse_id') }}</small></label
                >
                <label class="space-y-1 text-sm font-medium"
                    >Order date<input
                        v-model="form.order_date"
                        required
                        type="date"
                        class="min-h-11 w-full rounded-md border border-slate-300 px-3"
                /></label>
                <label class="space-y-1 text-sm font-medium"
                    >Expected delivery<input
                        v-model="form.expected_date"
                        type="date"
                        class="min-h-11 w-full rounded-md border border-slate-300 px-3"
                    /><small class="text-red-600">{{ fieldError('expected_date') }}</small></label
                >
            </div>
            <div class="surface-card space-y-3 rounded-lg border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Items</h2>
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                        @click="addItem"
                    >
                        Add item
                    </button>
                </div>
                <div
                    v-for="(line, index) in form.items"
                    :key="index"
                    class="grid grid-cols-1 items-end gap-3 rounded-md border border-slate-100 p-3 sm:grid-cols-[minmax(0,2fr)_1fr_1fr_1fr_auto] sm:border-0 sm:p-0"
                >
                    <label class="text-sm"
                        >Product<select
                            v-model="line.product_id"
                            required
                            class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3"
                            @change="selectProduct(line)"
                        >
                            <option value="">Select product</option>
                            <option v-for="p in options.products" :key="p.id" :value="p.id">
                                {{ p.name }} ({{ p.sku }})
                            </option></select
                        ><small class="text-red-600">{{
                            fieldError('items.' + index + '.product_id')
                        }}</small></label
                    >
                    <label class="text-sm"
                        >Quantity<input
                            v-model="line.quantity"
                            min="1"
                            step="1"
                            required
                            type="number"
                            class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3"
                        /><small class="text-red-600">{{
                            fieldError('items.' + index + '.ordered_quantity')
                        }}</small></label
                    >
                    <label class="text-sm"
                        >Unit price<input
                            v-model="line.unit_price"
                            inputmode="decimal"
                            autocomplete="off"
                            required
                            type="text"
                            class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3"
                        /><small class="text-red-600">{{
                            fieldError('items.' + index + '.unit_price')
                        }}</small></label
                    >
                    <p class="text-sm sm:pb-3">
                        <span class="text-xs text-slate-500 sm:hidden">Line total </span
                        ><b>PKR {{ formatMoney(lineTotals[index] || '0.00') }}</b>
                    </p>
                    <button
                        type="button"
                        :disabled="form.items.length === 1"
                        class="min-h-11 rounded-md px-3 text-red-700 disabled:opacity-40"
                        @click="form.items.splice(index, 1)"
                    >
                        Remove
                    </button>
                </div>
                <div class="grid grid-cols-1 gap-4 border-t pt-4 md:grid-cols-[1fr_1fr]">
                    <div class="space-y-3">
                        <label class="block max-w-xs text-sm"
                            >Discount amount<input
                                v-model="form.discount_amount"
                                inputmode="decimal"
                                type="text"
                                class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3"
                            /><small class="text-red-600">{{
                                fieldError('discount_amount')
                            }}</small></label
                        >
                        <label class="block text-sm"
                            >Notes<textarea
                                v-model="form.notes"
                                rows="2"
                                class="mt-1 w-full rounded-md border border-slate-300 p-3"
                            ></textarea>
                        </label>
                    </div>
                    <dl class="space-y-2 rounded-md bg-slate-50 p-4 text-sm">
                        <div class="flex justify-between">
                            <dt>Subtotal</dt>
                            <dd>PKR {{ formatMoney(centsToDecimal(totals.subtotal)) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Discount</dt>
                            <dd>- PKR {{ formatMoney(centsToDecimal(totals.discount)) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Tax ({{ options.tax_rate || '0.00' }}%)</dt>
                            <dd>PKR {{ formatMoney(centsToDecimal(totals.tax)) }}</dd>
                        </div>
                        <div class="flex justify-between border-t pt-2 text-base font-bold">
                            <dt>Grand total</dt>
                            <dd>PKR {{ formatMoney(centsToDecimal(totals.grand)) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <router-link
                    :to="{ name: 'purchases' }"
                    class="rounded-md border border-slate-300 px-4 py-3 text-center text-sm"
                    >Cancel</router-link
                ><button
                    :disabled="saving"
                    class="rounded-md bg-primary-app px-5 py-3 text-sm font-semibold text-white disabled:opacity-50"
                >
                    {{ saving ? 'Saving...' : 'Save draft' }}
                </button>
            </div>
        </form>
    </section>
</template>
<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../api/axios';
import { centsToDecimal, formatMoney, moneyToCents } from '../../api/money';
import PageHeader from '../../components/PageHeader.vue';
const route = useRoute();
const router = useRouter();
const isEdit = computed(() => Boolean(route.params.id));
const saving = ref(false);
const error = ref('');
const errors = ref({});
const options = reactive({ suppliers: [], warehouses: [], products: [], tax_rate: '0.00' });
const form = reactive({
    supplier_id: '',
    warehouse_id: '',
    order_date: new Date().toISOString().slice(0, 10),
    expected_date: '',
    discount_amount: '0.00',
    notes: '',
    items: [{ product_id: '', quantity: '1', unit_price: '0.00' }],
});
function fieldError(name) {
    return errors.value[name]?.[0] || '';
}
function addItem() {
    form.items.push({ product_id: '', quantity: '1', unit_price: '0.00' });
}
function selectProduct(line) {
    const product = options.products.find((item) => String(item.id) === String(line.product_id));
    if (product) line.unit_price = String(product.purchase_price || '0.00');
}
function safeQuantity(value) {
    return /^\d+$/.test(String(value)) ? BigInt(value) : 0n;
}
const lineTotals = computed(() =>
    form.items.map((line) => {
        const quantity = safeQuantity(line.quantity);
        const unit = moneyToCents(line.unit_price);
        return centsToDecimal(quantity * (unit ?? 0n));
    }),
);
const totals = computed(() => {
    const subtotal = form.items.reduce(
        (sum, line) => sum + safeQuantity(line.quantity) * (moneyToCents(line.unit_price) ?? 0n),
        0n,
    );
    const rawDiscount = moneyToCents(form.discount_amount) ?? 0n;
    const discount = rawDiscount > subtotal ? subtotal : rawDiscount;
    const taxable = subtotal - discount;
    const rate = moneyToCents(options.tax_rate) ?? 0n;
    const tax = (taxable * rate + 5000n) / 10000n;
    return { subtotal, discount, tax, grand: taxable + tax };
});
async function load() {
    try {
        const response = await api.get('/api/purchases/options');
        Object.assign(options, response.data.data);
        if (isEdit.value) {
            const result = await api.get('/api/purchases/' + route.params.id);
            const p = result.data.data;
            Object.assign(form, {
                supplier_id: p.supplier.id,
                warehouse_id: p.warehouse.id,
                order_date: p.order_date,
                expected_date: p.expected_date || '',
                discount_amount: p.discount_amount,
                notes: p.notes || '',
                items: p.items.map((i) => ({
                    product_id: i.product_id,
                    quantity: String(i.ordered_quantity),
                    unit_price: String(i.unit_price),
                })),
            });
        }
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load purchase form data.';
    }
}
async function save() {
    saving.value = true;
    error.value = '';
    errors.value = {};
    try {
        const payload = {
            ...form,
            discount_amount: String(form.discount_amount),
            items: form.items.map((line) => ({
                product_id: Number(line.product_id),
                ordered_quantity: Number(line.quantity),
                unit_price: String(line.unit_price),
            })),
        };
        const result = isEdit.value
            ? await api.put('/api/purchases/' + route.params.id, payload)
            : await api.post('/api/purchases', payload);
        await router.push({ name: 'purchase-detail', params: { id: result.data.data.id } });
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        error.value = e.response?.data?.message || 'Could not save purchase order.';
    } finally {
        saving.value = false;
    }
}
onMounted(load);
</script>
