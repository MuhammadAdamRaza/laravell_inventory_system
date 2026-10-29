<template>
    <div>
        <PageHeader
            title="User Management"
            subtitle="Manage administrative users, access roles, and warehouse assignments"
        >
            <template #actions>
                <button
                    type="button"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-primary-app bg-primary-hover min-h-[44px] transition-colors"
                    @click="openCreateModal"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"
                        />
                    </svg>
                    Add New User
                </button>
            </template>
        </PageHeader>

        <!-- Filters Bar -->
        <div
            class="filter-bar mb-4 flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between"
        >
            <SearchInput
                v-model="search"
                placeholder="Search by name or email..."
                @search="fetchUsers(1)"
            />

            <div class="flex items-center space-x-3">
                <select
                    v-model="roleFilter"
                    class="px-3 py-2 bg-white border border-app rounded-md text-sm text-main min-h-[44px] focus:outline-none focus:ring-1 focus:ring-[#1E5AA8]"
                    @change="fetchUsers(1)"
                >
                    <option value="">All Roles</option>
                    <option v-for="r in rolesList" :key="r" :value="r">{{ r }}</option>
                </select>
            </div>
        </div>

        <!-- Data Table -->
        <DataTable
            :columns="columns"
            :rows="users"
            :loading="loading"
            :error="error"
            @retry="fetchUsers(pagination.current_page)"
        >
            <template #user="{ row }">
                <div>
                    <p class="font-medium text-main">{{ row.name }}</p>
                    <p class="text-xs text-muted">{{ row.email }}</p>
                </div>
            </template>

            <template #role="{ row }">
                <span
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-[#EAF2FB] text-primary-app"
                >
                    {{ row.role || 'No Role' }}
                </span>
            </template>

            <template #status="{ row }">
                <StatusBadge :status="row.is_active ? 'active' : 'inactive'" />
            </template>

            <template #actions="{ row }">
                <div class="flex items-center space-x-2">
                    <button
                        type="button"
                        class="text-primary-app hover:text-[#174A8A] font-medium text-xs py-1 px-2 rounded hover:bg-[#EAF2FB] min-h-[32px]"
                        @click="openEditModal(row)"
                    >
                        Edit
                    </button>
                    <button
                        v-if="row.is_active"
                        type="button"
                        class="text-[#B42318] hover:text-[#912018] font-medium text-xs py-1 px-2 rounded hover:bg-[#FDE8E8] min-h-[32px]"
                        @click="confirmDeactivate(row)"
                    >
                        Deactivate
                    </button>
                </div>
            </template>
        </DataTable>

        <Pagination :meta="pagination" @page-change="fetchUsers" />

        <!-- Create / Edit User Modal -->
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
                            {{ isEditing ? 'Edit User' : 'Create User & Assign Role' }}
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

                    <form class="space-y-4" @submit.prevent="saveUser">
                        <FormField label="Full Name" id="name" required :error="formErrors.name">
                            <template #default="{ inputClass }">
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    required
                                    :class="inputClass"
                                    placeholder="e.g. Asad Khan"
                                />
                            </template>
                        </FormField>

                        <FormField
                            label="Email Address"
                            id="email"
                            required
                            :error="formErrors.email"
                        >
                            <template #default="{ inputClass }">
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    :class="inputClass"
                                    placeholder="user@example.com"
                                />
                            </template>
                        </FormField>

                        <FormField
                            :label="
                                isEditing
                                    ? 'New Password (leave blank to keep current)'
                                    : 'Password'
                            "
                            id="password"
                            :required="!isEditing"
                            :error="formErrors.password"
                        >
                            <template #default="{ inputClass }">
                                <div class="relative">
                                    <input
                                        id="password"
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        :required="!isEditing"
                                        :class="[inputClass, 'pr-11']"
                                        placeholder="Minimum 8 characters"
                                    />
                                    <button
                                        type="button"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-muted hover:text-main focus:outline-none min-h-[44px]"
                                        @click="showPassword = !showPassword"
                                    >
                                        <svg
                                            v-if="!showPassword"
                                            class="w-5 h-5"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                            />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                            />
                                        </svg>
                                        <svg
                                            v-else
                                            class="w-5 h-5 text-primary-app"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 013.14-4.887m3.435-2.02A10.01 10.01 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21m-2.122-2.122L3 3"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </FormField>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <FormField
                                label="Role Assignment"
                                id="role"
                                required
                                :error="formErrors.role"
                            >
                                <template #default="{ inputClass }">
                                    <select
                                        id="role"
                                        v-model="form.role"
                                        required
                                        :class="inputClass"
                                    >
                                        <option value="" disabled>Select Role</option>
                                        <option v-for="r in rolesList" :key="r" :value="r">
                                            {{ r }}
                                        </option>
                                    </select>
                                </template>
                            </FormField>

                            <FormField label="Status" id="is_active">
                                <template #default="{ inputClass }">
                                    <select
                                        id="is_active"
                                        v-model="form.is_active"
                                        :class="inputClass"
                                    >
                                        <option :value="true">Active (Can log in)</option>
                                        <option :value="false">Inactive (Suspended)</option>
                                    </select>
                                </template>
                            </FormField>
                        </div>

                        <!-- Assigned Warehouses (Especially for Warehouse Staff) -->
                        <div>
                            <label class="block text-[13px] font-medium text-muted mb-1.5">
                                Assigned Warehouses (Required for Warehouse Staff isolation)
                            </label>
                            <div
                                class="space-y-1 max-h-32 overflow-y-auto p-2 border border-app rounded-md bg-app-bg"
                            >
                                <label
                                    v-for="wh in warehousesList"
                                    :key="wh.id"
                                    class="flex items-center space-x-2 text-sm text-main"
                                >
                                    <input
                                        type="checkbox"
                                        :value="wh.id"
                                        v-model="form.warehouse_ids"
                                        class="rounded border-app text-primary-app focus:ring-[#1E5AA8]"
                                    />
                                    <span>{{ wh.name }}</span>
                                </label>
                            </div>
                        </div>

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
                                    saving ? 'Saving...' : isEditing ? 'Update User' : 'Create User'
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
            title="Deactivate User"
            :message="`Are you sure you want to deactivate ${targetUser?.name}? Inactive users are rejected upon login.`"
            confirm-text="Deactivate"
            @confirm="executeDeactivate"
            @cancel="showDeactivateDialog = false"
        />
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../../api/axios';
import { useToastStore } from '../../stores/toast';
import PageHeader from '../../components/PageHeader.vue';
import DataTable from '../../components/DataTable.vue';
import Pagination from '../../components/Pagination.vue';
import SearchInput from '../../components/SearchInput.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import FormField from '../../components/FormField.vue';
import ConfirmDialog from '../../components/ConfirmDialog.vue';

const toastStore = useToastStore();

const users = ref([]);
const rolesList = ref([]);
const warehousesList = ref([]);
const loading = ref(false);
const error = ref(null);
const search = ref('');
const roleFilter = ref('');

const pagination = reactive({
    current_page: 1,
    last_page: 1,
    total: 0,
    from: 0,
    to: 0,
});

const columns = [
    { key: 'user', label: 'User Name & Email' },
    { key: 'role', label: 'Assigned Role' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: 'Actions', cellClass: 'text-right' },
];

const showModal = ref(false);
const isEditing = ref(false);
const showPassword = ref(false);
const saving = ref(false);
const formErrors = ref({});
const targetUser = ref(null);
const showDeactivateDialog = ref(false);

const form = reactive({
    id: null,
    name: '',
    email: '',
    password: '',
    role: '',
    is_active: true,
    warehouse_ids: [],
});

const fetchRoles = async () => {
    try {
        const res = await api.get('/api/roles');
        rolesList.value = res.data;
    } catch (err) {
        rolesList.value = ['Administrator', 'Manager', 'Warehouse Staff', 'Viewer'];
    }
};

const fetchWarehousesList = async () => {
    try {
        const res = await api.get('/api/warehouses', { params: { per_page: 100 } });
        warehousesList.value = res.data.data;
    } catch (err) {
        // silent
    }
};

const fetchUsers = async (page = 1) => {
    loading.value = true;
    error.value = null;
    try {
        const res = await api.get('/api/users', {
            params: {
                page,
                search: search.value,
                role: roleFilter.value,
            },
        });
        users.value = res.data.data;
        Object.assign(pagination, res.data.meta);
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load users.';
    } finally {
        loading.value = false;
    }
};

const openCreateModal = () => {
    isEditing.value = false;
    form.id = null;
    form.name = '';
    form.email = '';
    form.password = '';
    form.role = rolesList.value[0] || 'Viewer';
    form.is_active = true;
    form.warehouse_ids = [];
    formErrors.value = {};
    showModal.value = true;
};

const openEditModal = (u) => {
    isEditing.value = true;
    form.id = u.id;
    form.name = u.name;
    form.email = u.email;
    form.password = '';
    form.role = u.role || '';
    form.is_active = u.is_active;
    form.warehouse_ids = u.assigned_warehouse_ids || [];
    formErrors.value = {};
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    formErrors.value = {};
};

const saveUser = async () => {
    saving.value = true;
    formErrors.value = {};
    try {
        if (isEditing.value) {
            await api.put(`/api/users/${form.id}`, form);
            toastStore.success('User updated successfully.');
        } else {
            await api.post('/api/users', form);
            toastStore.success('User created and role assigned successfully.');
        }
        closeModal();
        fetchUsers(pagination.current_page);
    } catch (err) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            formErrors.value = err.response.data.errors;
        } else {
            toastStore.error(err.response?.data?.message || 'Failed to save user.');
        }
    } finally {
        saving.value = false;
    }
};

const confirmDeactivate = (u) => {
    targetUser.value = u;
    showDeactivateDialog.value = true;
};

const executeDeactivate = async () => {
    showDeactivateDialog.value = false;
    try {
        await api.delete(`/api/users/${targetUser.value.id}`);
        toastStore.success('User deactivated successfully.');
        fetchUsers(pagination.current_page);
    } catch (err) {
        toastStore.error(err.response?.data?.message || 'Failed to deactivate user.');
    }
};

onMounted(() => {
    fetchRoles();
    fetchWarehousesList();
    fetchUsers(1);
});
</script>
