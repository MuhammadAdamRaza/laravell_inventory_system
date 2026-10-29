<template>
    <section class="mx-auto max-w-5xl space-y-5">
        <PageHeader
            :title="editing ? 'Edit sales order' : 'New sales order'"
            subtitle="Save a draft first; stock changes only when the order is confirmed"
            ><template #actions
                ><router-link
                    :to="{ name: 'sales-orders' }"
                    class="min-h-11 rounded-md border border-slate-300 px-4 py-2 text-sm"
                    >All sales orders</router-link
                ></template
            ></PageHeader
        >
        <p v-if="loadError" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
            {{ loadError }} <button class="underline" @click="load">Retry</button>
        </p>
        <div v-else-if="loading" class="rounded-lg bg-white p-8 text-center text-sm text-slate-500">
            Loading order form...
        </div>
        <form v-else class="space-y-5" @submit.prevent="save">
            <div
                class="surface-card grid gap-4 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-2"
            >
                <label class="text-sm font-medium"
                    >Customer *
                    <div class="mt-1 flex gap-2">
                        <select
                            v-model="form.customer_id"
                            required
                            class="min-h-11 min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-3"
                        >
                            <option value="">Select customer</option>
                            <option
                                v-for="customer in options.customers"
                                :key="customer.id"
                                :value="customer.id"
                            >
                                {{ customer.name }}
                            </option></select
                        ><button
                            v-if="canManageCustomers"
                            type="button"
                            class="min-h-11 shrink-0 rounded-md border px-3 text-sm"
                            @click="quickCustomerOpen = true"
                        >
                            Add
                        </button>
                    </div>
                    <span v-if="errors.customer_id" class="mt-1 block text-xs text-red-700">{{
                        errors.customer_id[0]
                    }}</span></label
                ><label class="text-sm font-medium"
                    >Dispatch warehouse *<select
                        v-model="form.warehouse_id"
                        required
                        class="mt-1 min-h-11 w-full rounded-md border border-slate-300 bg-white px-3"
                    >
                        <option value="">Select warehouse</option>
                        <option
                            v-for="warehouse in options.warehouses"
                            :key="warehouse.id"
                            :value="warehouse.id"
                        >
                            {{ warehouse.name }}
                        </option></select
                    ><span v-if="errors.warehouse_id" class="mt-1 block text-xs text-red-700">{{
                        errors.warehouse_id[0]
                    }}</span></label
                ><label class="text-sm font-medium"
                    >Order date *<input
                        v-model="form.order_date"
                        type="date"
                        required
                        class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3"
                    /><span v-if="errors.order_date" class="mt-1 block text-xs text-red-700">{{
                        errors.order_date[0]
                    }}</span></label
                ><label class="text-sm font-medium sm:col-span-2"
                    >Notes<textarea
                        v-model="form.notes"
                        rows="2"
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                    ></textarea>
                </label>
            </div>

            <div class="surface-card overflow-hidden rounded-lg border border-slate-200 bg-white">
                <div class="flex items-center justify-between gap-3 border-b px-4 py-3">
                    <div>
                        <h2 class="font-semibold">Items</h2>
                        <p class="text-xs text-slate-500">
                            Available stock is a preview; confirmation checks and deducts stock
                            securely.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="min-h-10 rounded-md border px-3 text-sm"
                        @click="addLine"
                    >
                        Add item
                    </button>
                </div>
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Product</th>
                                <th class="px-4 py-3">Qty</th>
                                <th class="px-4 py-3">Unit price (PKR)</th>
                                <th class="px-4 py-3">Available</th>
                                <th class="px-4 py-3">Line total</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(line, index) in form.items" :key="line.key">
                                <td class="min-w-56 px-4 py-3">
                                    <select
                                        v-model="line.product_id"
                                        class="min-h-10 w-full rounded border px-2"
                                        @change="productChanged(line)"
                                    >
                                        <option value="">Select product</option>
                                        <option
                                            v-for="product in options.products"
                                            :key="product.id"
                                            :value="product.id"
                                        >
                                            {{ product.name }} ({{ product.sku }})
                                        </option></select
                                    ><span
                                        v-if="errors[`items.${index}.product_id`]"
                                        class="text-xs text-red-700"
                                        >{{ errors[`items.${index}.product_id`][0] }}</span
                                    >
                                </td>
                                <td class="w-24 px-4 py-3">
                                    <input
                                        v-model="line.quantity"
                                        type="number"
                                        min="1"
                                        step="1"
                                        class="min-h-10 w-20 rounded border px-2"
                                        @input="refreshAvailability"
                                    />
                                </td>
                                <td class="w-40 px-4 py-3">
                                    <input
                                        v-model="line.unit_price"
                                        inputmode="decimal"
                                        class="min-h-10 w-32 rounded border px-2"
                                        @input="refreshPreview"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <span>{{ availableFor(line) }}</span
                                    ><span
                                        v-if="
                                            line.product_id &&
                                            Number(line.quantity || 0) > availableFor(line)
                                        "
                                        class="ml-1 text-xs font-medium text-amber-700"
                                        >Qty exceeds stock</span
                                    >
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-medium">
                                    PKR {{ money(lineTotal(line)) }}
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        type="button"
                                        class="min-h-10 px-2 text-red-700"
                                        aria-label="Remove item"
                                        :disabled="form.items.length === 1"
                                        @click="removeLine(index)"
                                    >
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="divide-y md:hidden">
                    <article
                        v-for="(line, index) in form.items"
                        :key="line.key"
                        class="space-y-3 p-4"
                    >
                        <div class="flex items-center justify-between">
                            <b>Item {{ index + 1 }}</b
                            ><button
                                type="button"
                                class="min-h-10 px-2 text-sm text-red-700"
                                :disabled="form.items.length === 1"
                                @click="removeLine(index)"
                            >
                                Remove
                            </button>
                        </div>
                        <label class="block text-sm"
                            >Product<select
                                v-model="line.product_id"
                                class="mt-1 min-h-11 w-full rounded border px-3"
                                @change="productChanged(line)"
                            >
                                <option value="">Select product</option>
                                <option
                                    v-for="product in options.products"
                                    :key="product.id"
                                    :value="product.id"
                                >
                                    {{ product.name }} ({{ product.sku }})
                                </option></select
                            ><span
                                v-if="errors[`items.${index}.product_id`]"
                                class="text-xs text-red-700"
                                >{{ errors[`items.${index}.product_id`][0] }}</span
                            ></label
                        >
                        <div class="grid grid-cols-2 gap-3">
                            <label class="text-sm"
                                >Quantity<input
                                    v-model="line.quantity"
                                    type="number"
                                    min="1"
                                    step="1"
                                    class="mt-1 min-h-11 w-full rounded border px-3"
                                    @input="refreshAvailability" /></label
                            ><label class="text-sm"
                                >Unit price<input
                                    v-model="line.unit_price"
                                    inputmode="decimal"
                                    class="mt-1 min-h-11 w-full rounded border px-3"
                                    @input="refreshPreview"
                            /></label>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span
                                >Available: {{ availableFor(line)
                                }}<b
                                    v-if="
                                        line.product_id &&
                                        Number(line.quantity || 0) > availableFor(line)
                                    "
                                    class="ml-1 text-amber-700"
                                    >(over stock)</b
                                ></span
                            ><b>PKR {{ money(lineTotal(line)) }}</b>
                        </div>
                    </article>
                </div>
                <p v-if="errors.items" class="px-4 py-2 text-sm text-red-700">
                    {{ errors.items[0] }}
                </p>
            </div>
            <div class="grid gap-5 lg:grid-cols-[1fr_320px]">
                <label class="text-sm font-medium"
                    >Discount (PKR)<input
                        v-model="form.discount_amount"
                        inputmode="decimal"
                        class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3"
                        @input="refreshPreview"
                    /><span v-if="errors.discount_amount" class="mt-1 block text-xs text-red-700">{{
                        errors.discount_amount[0]
                    }}</span></label
                >
                <div class="surface-card rounded-lg border border-slate-200 bg-white p-4">
                    <h2 class="mb-3 font-semibold">Order summary</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt>Subtotal</dt>
                            <dd>PKR {{ money(totals.subtotal) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Discount</dt>
                            <dd>- PKR {{ money(totals.discount) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Tax ({{ options.tax_rate }}%)</dt>
                            <dd>PKR {{ money(totals.tax) }}</dd>
                        </div>
                        <div class="flex justify-between border-t pt-2 text-base font-bold">
                            <dt>Total</dt>
                            <dd>PKR {{ money(totals.total) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
            <p v-if="saveError" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                {{ saveError }}
            </p>
            <div class="flex flex-col-reverse gap-2 border-t pt-4 sm:flex-row sm:justify-end">
                <router-link
                    :to="{ name: 'sales-orders' }"
                    class="min-h-11 rounded-md border px-4 py-2 text-center text-sm"
                    >Cancel</router-link
                ><button
                    :disabled="saving"
                    class="min-h-11 rounded-md bg-primary-app px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
                >
                    {{ saving ? 'Saving…' : editing ? 'Save draft' : 'Create draft' }}
                </button>
            </div>
        </form>

        <div
            v-if="quickCustomerOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            role="dialog"
            aria-modal="true"
        >
            <form
                class="w-full max-w-md space-y-4 rounded-lg bg-white p-5"
                @submit.prevent="createQuickCustomer"
            >
                <div class="flex justify-between">
                    <h2 class="text-lg font-semibold">Add customer</h2>
                    <button type="button" aria-label="Close" @click="quickCustomerOpen = false">
                        ×
                    </button>
                </div>
                <label class="block text-sm"
                    >Name *<input
                        v-model="quickCustomer.name"
                        required
                        class="mt-1 min-h-11 w-full rounded border px-3" /></label
                ><label class="block text-sm"
                    >Phone<input
                        v-model="quickCustomer.phone"
                        class="mt-1 min-h-11 w-full rounded border px-3" /></label
                ><label class="block text-sm"
                    >Email<input
                        v-model="quickCustomer.email"
                        type="email"
                        class="mt-1 min-h-11 w-full rounded border px-3"
                /></label>
                <p v-if="quickError" class="text-sm text-red-700">{{ quickError }}</p>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="min-h-11 rounded border px-4"
                        @click="quickCustomerOpen = false"
                    >
                        Cancel</button
                    ><button
                        :disabled="quickSaving"
                        class="min-h-11 rounded bg-primary-app px-4 text-white"
                    >
                        {{ quickSaving ? 'Saving…' : 'Add customer' }}
                    </button>
                </div>
            </form>
        </div>
    </section>
</template>
<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../api/axios';
import { formatMoney } from '../../api/money';
import { useAuthStore } from '../../stores/auth';
import PageHeader from '../../components/PageHeader.vue';
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const canManageCustomers = auth.hasPermission('customers.manage');
const editing = computed(() => Boolean(route.params.id));
const loading = ref(true);
const saving = ref(false);
const loadError = ref('');
const saveError = ref('');
const errors = ref({});
const quickCustomerOpen = ref(false);
const quickSaving = ref(false);
const quickError = ref('');
const availability = ref({});
const options = reactive({ customers: [], warehouses: [], products: [], tax_rate: '0.00' });
const today = new Date().toISOString().slice(0, 10);
let nextKey = 1;
const blankLine = () => ({ key: nextKey++, product_id: '', quantity: '1', unit_price: '0.00' });
const form = reactive({
    customer_id: '',
    warehouse_id: '',
    order_date: today,
    notes: '',
    discount_amount: '0.00',
    items: [blankLine()],
});
const quickCustomer = reactive({ name: '', phone: '', email: '' });
const selectedLines = computed(() =>
    form.items.filter((line) => line.product_id && Number(line.quantity) > 0),
);
function cents(value) {
    const match = String(value ?? '0')
        .trim()
        .match(/^(\d+)(?:\.(\d{1,2}))?$/);
    return match ? BigInt(match[1]) * 100n + BigInt((match[2] || '').padEnd(2, '0') || '0') : 0n;
}
function money(value) {
    return formatMoney(
        (BigInt(value || 0) / 100n).toString() +
            '.' +
            String(BigInt(value || 0) % 100n).padStart(2, '0'),
    );
}
function lineCents(line) {
    return BigInt(Math.max(0, Number.parseInt(line.quantity, 10) || 0)) * cents(line.unit_price);
}
const totals = computed(() => {
    const subtotal = selectedLines.value.reduce((sum, line) => sum + lineCents(line), 0n);
    const discount =
        cents(form.discount_amount) > subtotal ? subtotal : cents(form.discount_amount);
    const taxable = subtotal - discount;
    const tax = (taxable * cents(options.tax_rate) + 5000n) / 10000n;
    return { subtotal, discount, tax, total: taxable + tax };
});
function refreshPreview() {}
function addLine() {
    form.items.push(blankLine());
}
function removeLine(index) {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
        refreshAvailability();
    }
}
function productChanged(line) {
    const product = options.products.find((entry) => String(entry.id) === String(line.product_id));
    line.unit_price = product ? String(product.selling_price) : '0.00';
    refreshAvailability();
}
function availableFor(line) {
    return Number(availability.value[String(line.product_id)] ?? 0);
}
async function refreshAvailability() {
    if (!form.warehouse_id || !selectedLines.value.length) {
        availability.value = {};
        return;
    }
    try {
        const response = await api.get('/api/inventory/availability', {
            params: {
                warehouse_id: form.warehouse_id,
                product_ids: selectedLines.value.map((line) => line.product_id),
            },
        });
        availability.value = Object.fromEntries(
            response.data.data.map((item) => [String(item.product_id), item.available_quantity]),
        );
    } catch {
        availability.value = {};
    }
}
watch(
    () => [form.warehouse_id, selectedLines.value.map((line) => String(line.product_id)).join(',')],
    refreshAvailability,
);
async function load() {
    loading.value = true;
    loadError.value = '';
    try {
        const response = await api.get('/api/sales-orders/options');
        Object.assign(options, response.data.data);
        if (editing.value) {
            const order = (await api.get(`/api/sales-orders/${route.params.id}`)).data.data;
            form.customer_id = order.customer.id;
            form.warehouse_id = order.warehouse.id;
            form.order_date = order.order_date;
            form.notes = order.notes || '';
            form.discount_amount = order.discount_amount;
            form.items = order.items.map((item) => ({
                key: nextKey++,
                product_id: item.product_id,
                quantity: String(item.quantity),
                unit_price: String(item.unit_price),
            }));
        }
        await refreshAvailability();
    } catch (e) {
        loadError.value = e.response?.data?.message || 'Could not load sales order form.';
    } finally {
        loading.value = false;
    }
}
async function save() {
    saving.value = true;
    errors.value = {};
    saveError.value = '';
    const payload = {
        ...form,
        customer_id: Number(form.customer_id),
        warehouse_id: Number(form.warehouse_id),
        discount_amount: String(form.discount_amount || '0'),
        items: form.items.map(({ product_id, quantity, unit_price }) => ({
            product_id: Number(product_id),
            quantity: Number(quantity),
            unit_price: String(unit_price),
        })),
    };
    try {
        const response = editing.value
            ? await api.put(`/api/sales-orders/${route.params.id}`, payload)
            : await api.post('/api/sales-orders', payload);
        await router.push({ name: 'sales-order-detail', params: { id: response.data.data.id } });
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        saveError.value =
            e.response?.data?.message || 'Please correct the highlighted fields and try again.';
    } finally {
        saving.value = false;
    }
}
async function createQuickCustomer() {
    quickSaving.value = true;
    quickError.value = '';
    try {
        const response = await api.post('/api/customers', quickCustomer);
        const customer = response.data.data;
        options.customers.push(customer);
        form.customer_id = customer.id;
        Object.assign(quickCustomer, { name: '', phone: '', email: '' });
        quickCustomerOpen.value = false;
    } catch (e) {
        quickError.value =
            e.response?.data?.message ||
            Object.values(e.response?.data?.errors || {}).flat()[0] ||
            'Could not add customer.';
    } finally {
        quickSaving.value = false;
    }
}
onMounted(load);
</script>
