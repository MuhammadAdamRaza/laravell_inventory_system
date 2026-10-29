<template>
    <section class="space-y-5">
        <PageHeader title="Customers" subtitle="Manage customer contact details and order history">
            <template #actions
                ><button
                    v-if="canManage"
                    class="min-h-11 rounded-md bg-primary-app px-4 py-2 text-sm font-semibold text-white bg-primary-hover"
                    @click="openCreate"
                >
                    Add customer
                </button></template
            >
        </PageHeader>
        <div class="filter-bar grid gap-3 sm:grid-cols-[minmax(0,1fr)_200px]">
            <input
                v-model="search"
                class="min-h-11 rounded-md border border-app bg-white px-3 text-sm"
                placeholder="Search name, phone, or email"
                @input="fetchCustomers(1)"
            /><select
                v-model="status"
                class="min-h-11 rounded-md border border-app bg-white px-3 text-sm"
                @change="fetchCustomers(1)"
            >
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <p v-if="error" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            {{ error }}
            <button class="underline" @click="fetchCustomers(page.current_page)">Retry</button>
        </p>
        <div v-else-if="busy" class="rounded-lg bg-white p-8 text-center text-sm text-slate-500">
            Loading customers...
        </div>
        <div
            v-else-if="!rows.length"
            class="surface-card rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500"
        >
            No customers found.
        </div>
        <div v-else class="surface-card overflow-hidden rounded-lg border border-app bg-white">
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Orders</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="customer in rows" :key="customer.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <router-link
                                    class="font-semibold text-primary-app hover:underline"
                                    :to="{ name: 'customer-detail', params: { id: customer.id } }"
                                    >{{ customer.name }}</router-link
                                >
                            </td>
                            <td class="px-4 py-3">{{ customer.phone || '—' }}</td>
                            <td class="px-4 py-3">{{ customer.email || '—' }}</td>
                            <td class="px-4 py-3">{{ customer.sales_orders_count || 0 }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="customer.status" /></td>
                            <td class="px-4 py-3">
                                <button
                                    v-if="canManage"
                                    class="text-primary-app hover:underline"
                                    @click="openEdit(customer)"
                                >
                                    Edit
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="divide-y divide-slate-100 md:hidden">
                <article v-for="customer in rows" :key="customer.id" class="space-y-2 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <router-link
                            class="font-semibold text-primary-app"
                            :to="{ name: 'customer-detail', params: { id: customer.id } }"
                            >{{ customer.name }}</router-link
                        ><StatusBadge :status="customer.status" />
                    </div>
                    <p class="break-all text-sm text-slate-600">
                        {{ customer.phone || 'No phone' }} · {{ customer.email || 'No email' }}
                    </p>
                    <div class="flex items-center justify-between text-sm text-slate-500">
                        <span>{{ customer.sales_orders_count || 0 }} orders</span
                        ><button
                            v-if="canManage"
                            class="text-primary-app"
                            @click="openEdit(customer)"
                        >
                            Edit
                        </button>
                    </div>
                </article>
            </div>
        </div>
        <Pagination :meta="page" @page-change="fetchCustomers" />
        <div
            v-if="dialogOpen"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/40 p-4"
            role="dialog"
            aria-modal="true"
        >
            <form
                class="my-auto w-full max-w-lg space-y-4 rounded-lg bg-white p-5 shadow-xl"
                @submit.prevent="saveCustomer"
            >
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">
                        {{ editing ? 'Edit customer' : 'Add customer' }}
                    </h2>
                    <button
                        type="button"
                        class="min-h-10 px-3"
                        aria-label="Close"
                        @click="dialogOpen = false"
                    >
                        ×
                    </button>
                </div>
                <label class="block text-sm font-medium"
                    >Name *<input
                        v-model="form.name"
                        required
                        maxlength="255"
                        class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3"
                /></label>
                <p v-if="errors.name" class="text-xs text-red-700">{{ errors.name[0] }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm font-medium"
                        >Phone<input
                            v-model="form.phone"
                            maxlength="40"
                            class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3" /></label
                    ><label class="block text-sm font-medium"
                        >Email<input
                            v-model="form.email"
                            type="email"
                            maxlength="255"
                            class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3"
                    /></label>
                </div>
                <p v-if="errors.email" class="text-xs text-red-700">{{ errors.email[0] }}</p>
                <label class="block text-sm font-medium"
                    >Address<textarea
                        v-model="form.address"
                        rows="3"
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                    ></textarea></label
                ><label v-if="editing" class="block text-sm font-medium"
                    >Status<select
                        v-model="form.status"
                        class="mt-1 min-h-11 w-full rounded-md border border-slate-300 px-3"
                    >
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select></label
                >
                <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="min-h-11 rounded-md border px-4"
                        @click="dialogOpen = false"
                    >
                        Close</button
                    ><button
                        :disabled="saving"
                        class="min-h-11 rounded-md bg-primary-app px-4 font-semibold text-white disabled:opacity-50"
                    >
                        {{ saving ? 'Saving…' : 'Save customer' }}
                    </button>
                </div>
            </form>
        </div>
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
const canManage = auth.hasPermission('customers.manage');
const rows = ref([]);
const search = ref('');
const status = ref('');
const busy = ref(true);
const saving = ref(false);
const error = ref('');
const dialogOpen = ref(false);
const editing = ref(null);
const errors = ref({});
const form = reactive({ name: '', phone: '', email: '', address: '', status: 'active' });
const page = reactive({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
async function fetchCustomers(number = 1) {
    busy.value = true;
    error.value = '';
    try {
        const response = await api.get('/api/customers', {
            params: {
                page: number,
                search: search.value || undefined,
                status: status.value || undefined,
            },
        });
        rows.value = response.data.data;
        Object.assign(page, response.data.meta);
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load customers.';
    } finally {
        busy.value = false;
    }
}
function resetForm(customer = null) {
    editing.value = customer;
    errors.value = {};
    Object.assign(
        form,
        customer
            ? {
                  name: customer.name,
                  phone: customer.phone || '',
                  email: customer.email || '',
                  address: customer.address || '',
                  status: customer.status,
              }
            : { name: '', phone: '', email: '', address: '', status: 'active' },
    );
    dialogOpen.value = true;
}
function openCreate() {
    resetForm();
}
function openEdit(customer) {
    resetForm(customer);
}
async function saveCustomer() {
    saving.value = true;
    errors.value = {};
    error.value = '';
    try {
        const url = editing.value ? `/api/customers/${editing.value.id}` : '/api/customers';
        await (editing.value ? api.put(url, form) : api.post(url, form));
        dialogOpen.value = false;
        await fetchCustomers(page.current_page);
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        error.value = e.response?.data?.message || 'Could not save this customer.';
    } finally {
        saving.value = false;
    }
}
onMounted(() => fetchCustomers());
</script>
