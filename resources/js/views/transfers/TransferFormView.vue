<template>
    <section class="mx-auto max-w-5xl space-y-5">
        <PageHeader
            :title="editing ? 'Edit transfer' : 'New warehouse transfer'"
            subtitle="Choose source, destination, and quantities to move"
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
        <div v-else-if="loading" class="rounded bg-white p-8 text-center text-slate-500">
            Loading form...
        </div>
        <form v-else class="space-y-5" @submit.prevent="save">
            <div class="surface-card grid gap-4 rounded-lg border bg-white p-4 sm:grid-cols-2">
                <label class="text-sm"
                    >Source warehouse *<select
                        v-model="form.source_warehouse_id"
                        required
                        class="mt-1 min-h-11 w-full rounded border bg-white px-3"
                    >
                        <option value="">Select source</option>
                        <option v-for="wh in options.warehouses" :key="wh.id" :value="wh.id">
                            {{ wh.name }}
                        </option></select
                    ><span v-if="errors.source_warehouse_id" class="text-xs text-red-700">{{
                        errors.source_warehouse_id[0]
                    }}</span></label
                ><label class="text-sm"
                    >Destination warehouse *<select
                        v-model="form.destination_warehouse_id"
                        required
                        class="mt-1 min-h-11 w-full rounded border bg-white px-3"
                    >
                        <option value="">Select destination</option>
                        <option v-for="wh in options.warehouses" :key="wh.id" :value="wh.id">
                            {{ wh.name }}
                        </option></select
                    ><span v-if="errors.destination_warehouse_id" class="text-xs text-red-700">{{
                        errors.destination_warehouse_id[0]
                    }}</span></label
                >
                <p v-if="sameWarehouse" class="text-sm text-red-700 sm:col-span-2">
                    Source and destination must be different warehouses.
                </p>
                <label class="text-sm sm:col-span-2"
                    >Notes<textarea
                        v-model="form.notes"
                        rows="2"
                        class="mt-1 w-full rounded border px-3 py-2"
                    ></textarea>
                </label>
            </div>
            <div class="surface-card overflow-hidden rounded-lg border bg-white">
                <div class="flex justify-between border-b p-4">
                    <div>
                        <h2 class="font-semibold">Transfer items</h2>
                        <p class="text-xs text-slate-500">
                            Stock is checked again when dispatched.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="min-h-10 rounded border px-3 text-sm"
                        @click="addLine"
                    >
                        Add item
                    </button>
                </div>
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase">
                            <tr>
                                <th class="p-3">Product</th>
                                <th class="p-3">Dispatch qty</th>
                                <th class="p-3">Available</th>
                                <th class="p-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(line, index) in form.items" :key="line.key">
                                <td class="p-3">
                                    <select
                                        v-model="line.product_id"
                                        class="min-h-10 w-full rounded border px-2"
                                        @change="refreshAvailability"
                                    >
                                        <option value="">Select product</option>
                                        <option
                                            v-for="p in options.products"
                                            :key="p.id"
                                            :value="p.id"
                                        >
                                            {{ p.name }} ({{ p.sku }})
                                        </option></select
                                    ><span
                                        v-if="errors[`items.${index}.product_id`]"
                                        class="text-xs text-red-700"
                                        >{{ errors[`items.${index}.product_id`][0] }}</span
                                    >
                                </td>
                                <td class="p-3">
                                    <input
                                        v-model="line.dispatched_quantity"
                                        type="number"
                                        min="1"
                                        step="1"
                                        class="min-h-10 w-28 rounded border px-2"
                                        @input="refreshAvailability"
                                    />
                                </td>
                                <td class="p-3">
                                    <span>{{ available(line) }}</span
                                    ><b
                                        v-if="
                                            line.product_id &&
                                            Number(line.dispatched_quantity) > available(line)
                                        "
                                        class="ml-2 text-xs text-amber-700"
                                        >Exceeds stock</b
                                    >
                                </td>
                                <td class="p-3">
                                    <button
                                        type="button"
                                        :disabled="form.items.length === 1"
                                        class="min-h-10 px-2 text-red-700"
                                        @click="remove(index)"
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
                        <div class="flex justify-between">
                            <b>Item {{ index + 1 }}</b
                            ><button
                                type="button"
                                :disabled="form.items.length === 1"
                                class="text-red-700"
                                @click="remove(index)"
                            >
                                Remove
                            </button>
                        </div>
                        <label class="block text-sm"
                            >Product<select
                                v-model="line.product_id"
                                class="mt-1 min-h-11 w-full rounded border px-3"
                                @change="refreshAvailability"
                            >
                                <option value="">Select product</option>
                                <option v-for="p in options.products" :key="p.id" :value="p.id">
                                    {{ p.name }} ({{ p.sku }})
                                </option>
                            </select></label
                        >
                        <div class="flex items-center justify-between gap-3">
                            <label class="text-sm"
                                >Quantity<input
                                    v-model="line.dispatched_quantity"
                                    type="number"
                                    min="1"
                                    class="mt-1 min-h-11 w-28 rounded border px-3"
                                    @input="refreshAvailability" /></label
                            ><span class="text-sm"
                                >Available: {{ available(line)
                                }}<b
                                    v-if="
                                        line.product_id &&
                                        Number(line.dispatched_quantity) > available(line)
                                    "
                                    class="block text-amber-700"
                                    >Over stock warning</b
                                ></span
                            >
                        </div>
                    </article>
                </div>
                <p v-if="errors.items" class="p-3 text-sm text-red-700">{{ errors.items[0] }}</p>
            </div>
            <p v-if="saveError" class="text-sm text-red-700">{{ saveError }}</p>
            <div class="flex justify-end gap-2">
                <router-link :to="{ name: 'transfers' }" class="min-h-11 rounded border px-4 py-2"
                    >Cancel</router-link
                ><button
                    :disabled="saving || sameWarehouse"
                    class="min-h-11 rounded bg-primary-app px-5 py-2 font-semibold text-white disabled:opacity-50"
                >
                    {{ saving ? 'Saving…' : 'Save draft' }}
                </button>
            </div>
        </form>
    </section>
</template>
<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../api/axios';
import PageHeader from '../../components/PageHeader.vue';
const route = useRoute(),
    router = useRouter(),
    editing = computed(() => Boolean(route.params.id));
const loading = ref(true),
    saving = ref(false),
    error = ref(''),
    saveError = ref(''),
    errors = ref({}),
    availability = ref({});
const options = reactive({ warehouses: [], products: [] });
let key = 0;
const blank = () => ({ key: key++, product_id: '', dispatched_quantity: '1' });
const form = reactive({
    source_warehouse_id: '',
    destination_warehouse_id: '',
    notes: '',
    items: [blank()],
});
const sameWarehouse = computed(
    () => form.source_warehouse_id && form.source_warehouse_id === form.destination_warehouse_id,
);
const available = (line) => Number(availability.value[String(line.product_id)] || 0);
function addLine() {
    form.items.push(blank());
}
function remove(i) {
    if (form.items.length > 1) {
        form.items.splice(i, 1);
        refreshAvailability();
    }
}
async function refreshAvailability() {
    if (!form.source_warehouse_id) {
        availability.value = {};
        return;
    }
    const ids = form.items.map((x) => x.product_id).filter(Boolean);
    if (!ids.length) {
        availability.value = {};
        return;
    }
    try {
        const r = await api.get('/api/inventory/availability', {
            params: { warehouse_id: form.source_warehouse_id, product_ids: ids },
        });
        availability.value = Object.fromEntries(
            r.data.data.map((x) => [String(x.product_id), x.available_quantity]),
        );
    } catch {
        availability.value = {};
    }
}
watch(
    () => [form.source_warehouse_id, form.items.map((x) => x.product_id).join(',')],
    refreshAvailability,
);
async function load() {
    loading.value = true;
    error.value = '';
    try {
        const r = await api.get('/api/transfers/options');
        Object.assign(options, r.data.data);
        if (editing.value) {
            const t = (await api.get(`/api/transfers/${route.params.id}`)).data.data;
            form.source_warehouse_id = t.source_warehouse.id;
            form.destination_warehouse_id = t.destination_warehouse.id;
            form.notes = t.notes || '';
            form.items = t.items.map((i) => ({
                key: key++,
                product_id: i.product_id,
                dispatched_quantity: String(i.dispatched_quantity),
            }));
        }
        await refreshAvailability();
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load transfer form.';
    } finally {
        loading.value = false;
    }
}
async function save() {
    saving.value = true;
    errors.value = {};
    saveError.value = '';
    const data = {
        ...form,
        source_warehouse_id: Number(form.source_warehouse_id),
        destination_warehouse_id: Number(form.destination_warehouse_id),
        items: form.items.map((i) => ({
            product_id: Number(i.product_id),
            dispatched_quantity: Number(i.dispatched_quantity),
        })),
    };
    try {
        const r = editing.value
            ? await api.put(`/api/transfers/${route.params.id}`, data)
            : await api.post('/api/transfers', data);
        await router.push({ name: 'transfer-detail', params: { id: r.data.data.id } });
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        saveError.value = e.response?.data?.message || 'Could not save transfer.';
    } finally {
        saving.value = false;
    }
}
onMounted(load);
</script>
