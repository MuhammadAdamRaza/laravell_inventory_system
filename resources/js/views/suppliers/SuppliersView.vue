<template>
    <div>
        <PageHeader title="Suppliers" subtitle="Manage vendor and supplier profiles">
            <template #actions>
                <button
                    v-if="canManage"
                    type="button"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-primary-app bg-primary-hover min-h-[44px] transition-colors"
                    @click="openCreateModal"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    Add Supplier
                </button>
            </template>
        </PageHeader>

        <!-- Filters Bar -->
        <div
            class="filter-bar mb-4 flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between"
        >
            <SearchInput
                v-model="search"
                placeholder="Search by name, company, email..."
                @search="fetchSuppliers(1)"
            />

            <div class="flex items-center space-x-3">
                <select
                    v-model="statusFilter"
                    class="px-3 py-2 bg-white border border-app rounded-md text-sm text-main min-h-[44px] focus:outline-none focus:ring-1 focus:ring-[#1E5AA8]"
                    @change="fetchSuppliers(1)"
                >
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>

        <!-- Data Table -->
        <DataTable
            :columns="columns"
            :rows="suppliers"
            :loading="loading"
            :error="error"
            :sort-key="sortBy"
            :sort-order="sortDirection"
            @sort="handleSort"
            @retry="fetchSuppliers(pagination.current_page)"
        >
            <template #company="{ row }">
                <div>
                    <router-link
                        class="font-medium text-primary-app hover:underline"
                        :to="{ name: 'supplier-detail', params: { id: row.id } }"
                        >{{ row.name }}</router-link
                    >
                    <p class="text-xs text-muted">{{ row.company_name || '-' }}</p>
                </div>
            </template>

            <template #contact="{ row }">
                <div class="text-xs">
                    <p class="font-medium text-main">{{ row.contact_person || '-' }}</p>
                    <p class="text-muted">{{ row.email || row.phone || '-' }}</p>
                </div>
            </template>

            <template #status="{ row }">
                <StatusBadge :status="row.status" />
            </template>

            <template #actions="{ row }">
                <div v-if="canManage" class="flex items-center space-x-2">
                    <button
                        type="button"
                        class="text-primary-app hover:text-[#174A8A] font-medium text-xs py-1 px-2 rounded hover:bg-[#EAF2FB] min-h-[32px]"
                        @click="openEditModal(row)"
                    >
                        Edit
                    </button>
                    <button
                        v-if="row.status === 'active'"
                        type="button"
                        class="text-[#B42318] hover:text-[#912018] font-medium text-xs py-1 px-2 rounded hover:bg-[#FDE8E8] min-h-[32px]"
                        @click="confirmDeactivate(row)"
                    >
                        Deactivate
                    </button>
                </div>
                <span v-else class="text-xs text-muted">-</span>
            </template>
        </DataTable>

        <Pagination :meta="pagination" @page-change="fetchSuppliers" />

        <!-- Create / Edit Modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div class="flex items-center justify-center min-h-screen px-4 text-center sm:p-0">
                <div class="fixed inset-0 bg-black/40 transition-opacity" @click="closeModal"></div>

                <div
                    class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full p-6 space-y-4"
                >
                    <div class="flex justify-between items-center border-b border-app pb-3">
                        <h3 class="text-lg font-semibold text-main">
                            {{ isEditing ? 'Edit Supplier' : 'Create Supplier' }}
                        </h3>
                        <button
                            type="button"
                            class="text-muted hover:text-main"
                            @click="closeModal"
                        >
                            <svg
                                class="w-5 h-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>

                    <form class="space-y-4" @submit.prevent="saveSupplier">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <FormField
                                label="Supplier Name"
                                id="name"
                                required
                                :error="formErrors.name"
                            >
                                <template #default="{ inputClass }">
                                    <input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        required
                                        :class="inputClass"
                                    />
                                </template>
                            </FormField>

                            <FormField
                                label="Company Name"
                                id="company_name"
                                :error="formErrors.company_name"
                            >
                                <template #default="{ inputClass }">
                                    <input
                                        id="company_name"
                                        v-model="form.company_name"
                                        type="text"
                                        :class="inputClass"
                                    />
                                </template>
                            </FormField>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <FormField
                                label="Contact Person"
                                id="contact_person"
                                :error="formErrors.contact_person"
                            >
                                <template #default="{ inputClass }">
                                    <input
                                        id="contact_person"
                                        v-model="form.contact_person"
                                        type="text"
                                        :class="inputClass"
                                    />
                                </template>
                            </FormField>

                            <FormField label="Phone" id="phone" :error="formErrors.phone">
                                <template #default="{ inputClass }">
                                    <input
                                        id="phone"
                                        v-model="form.phone"
                                        type="text"
                                        :class="inputClass"
                                    />
                                </template>
                            </FormField>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <FormField label="Email" id="email" :error="formErrors.email">
                                <template #default="{ inputClass }">
                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        :class="inputClass"
                                    />
                                </template>
                            </FormField>

                            <FormField
                                label="Status"
                                id="status"
                                required
                                :error="formErrors.status"
                            >
                                <template #default="{ inputClass }">
                                    <select
                                        id="status"
                                        v-model="form.status"
                                        required
                                        :class="inputClass"
                                    >
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </template>
                            </FormField>
                        </div>

                        <FormField label="Address" id="address" :error="formErrors.address">
                            <template #default="{ inputClass }">
                                <textarea
                                    id="address"
                                    v-model="form.address"
                                    rows="2"
                                    :class="inputClass"
                                ></textarea>
                            </template>
                        </FormField>

                        <div class="flex justify-end space-x-3 pt-3 border-t border-app">
                            <button
                                type="button"
                                class="px-4 py-2 border border-app rounded-md text-sm font-medium text-main bg-white bg-hover-surface min-h-[44px]"
                                @click="closeModal"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="saving"
                                class="px-4 py-2 rounded-md text-sm font-medium text-white bg-primary-app bg-primary-hover disabled:opacity-50 min-h-[44px] transition-colors"
                            >
                                {{
                                    saving
                                        ? 'Saving...'
                                        : isEditing
                                          ? 'Update Supplier'
                                          : 'Create Supplier'
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Confirm Deactivation Dialog -->
        <ConfirmDialog
            :is-open="showDeactivateDialog"
            title="Deactivate Supplier"
            :message="`Are you sure you want to deactivate '${targetSupplier?.name}'?`"
            confirm-text="Deactivate"
            @confirm="executeDeactivate"
            @cancel="showDeactivateDialog = false"
        />
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import api from '../../api/axios';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import PageHeader from '../../components/PageHeader.vue';
import DataTable from '../../components/DataTable.vue';
import Pagination from '../../components/Pagination.vue';
import SearchInput from '../../components/SearchInput.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import FormField from '../../components/FormField.vue';
import ConfirmDialog from '../../components/ConfirmDialog.vue';

const authStore = useAuthStore();
const toastStore = useToastStore();

const canManage = computed(() => authStore.hasPermission('suppliers.manage'));

const suppliers = ref([]);
const loading = ref(false);
const error = ref(null);
const search = ref('');
const statusFilter = ref('');
const sortBy = ref('created_at');
const sortDirection = ref('desc');

const pagination = reactive({
    current_page: 1,
    last_page: 1,
    total: 0,
    from: 0,
    to: 0,
});

const columns = [
    { key: 'company', label: 'Supplier & Company', sortable: true },
    { key: 'contact', label: 'Contact Details' },
    { key: 'address', label: 'Address' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: 'Actions', cellClass: 'text-right' },
];

const showModal = ref(false);
const isEditing = ref(false);
const saving = ref(false);
const formErrors = ref({});
const targetSupplier = ref(null);
const showDeactivateDialog = ref(false);

const form = reactive({
    id: null,
    name: '',
    company_name: '',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    status: 'active',
});

const fetchSuppliers = async (page = 1) => {
    loading.value = true;
    error.value = null;
    try {
        const res = await api.get('/api/suppliers', {
            params: {
                page,
                search: search.value,
                status: statusFilter.value,
                sort_by: sortBy.value,
                sort_direction: sortDirection.value,
            },
        });
        suppliers.value = res.data.data;
        Object.assign(pagination, res.data.meta);
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load suppliers.';
    } finally {
        loading.value = false;
    }
};

const handleSort = (key) => {
    if (sortBy.value === key) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = key;
        sortDirection.value = 'asc';
    }
    fetchSuppliers(1);
};

const openCreateModal = () => {
    isEditing.value = false;
    form.id = null;
    form.name = '';
    form.company_name = '';
    form.contact_person = '';
    form.phone = '';
    form.email = '';
    form.address = '';
    form.status = 'active';
    formErrors.value = {};
    showModal.value = true;
};

const openEditModal = (sup) => {
    isEditing.value = true;
    form.id = sup.id;
    form.name = sup.name;
    form.company_name = sup.company_name || '';
    form.contact_person = sup.contact_person || '';
    form.phone = sup.phone || '';
    form.email = sup.email || '';
    form.address = sup.address || '';
    form.status = sup.status;
    formErrors.value = {};
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    formErrors.value = {};
};

const saveSupplier = async () => {
    saving.value = true;
    formErrors.value = {};
    try {
        if (isEditing.value) {
            await api.put(`/api/suppliers/${form.id}`, form);
            toastStore.success('Supplier updated successfully.');
        } else {
            await api.post('/api/suppliers', form);
            toastStore.success('Supplier created successfully.');
        }
        closeModal();
        fetchSuppliers(pagination.current_page);
    } catch (err) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            formErrors.value = err.response.data.errors;
        } else {
            toastStore.error(err.response?.data?.message || 'Failed to save supplier.');
        }
    } finally {
        saving.value = false;
    }
};

const confirmDeactivate = (sup) => {
    targetSupplier.value = sup;
    showDeactivateDialog.value = true;
};

const executeDeactivate = async () => {
    showDeactivateDialog.value = false;
    try {
        await api.delete(`/api/suppliers/${targetSupplier.value.id}`);
        toastStore.success('Supplier deactivated successfully.');
        fetchSuppliers(pagination.current_page);
    } catch (err) {
        toastStore.error(err.response?.data?.message || 'Failed to deactivate supplier.');
    }
};

onMounted(() => {
    fetchSuppliers(1);
});
</script>
