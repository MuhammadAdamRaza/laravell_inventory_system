import { defineStore } from "pinia";
import { ref, computed } from "vue";
import api, { clearTabToken, getCsrfCookie, setTabToken } from "../api/axios";

export const useAuthStore = defineStore("auth", () => {
    const user = ref(null);
    const role = ref(null);
    const roles = ref([]);
    const permissions = ref([]);
    const assignedWarehouseIds = ref([]);
    const isInitialized = ref(false);
    const loading = ref(false);

    const isAuthenticated = computed(() => !!user.value);
    const isAdmin = computed(() => role.value === "Administrator");
    const roleLabel = computed(() => {
        const labels = {
            Administrator: "Admin",
            Manager: "Manager",
            "Warehouse Staff": "Warehouse Staff",
            Viewer: "Viewer",
        };

        return labels[role.value] || role.value || "User";
    });

    const hasPermission = (permission) => {
        if (!permission) return true;
        if (isAdmin.value) return true;
        return permissions.value.includes(permission);
    };

    const fetchMe = async () => {
        loading.value = true;
        try {
            const res = await api.get("/api/me");
            user.value = res.data.user;
            role.value = res.data.role;
            roles.value = res.data.roles || [];
            permissions.value = res.data.permissions || [];
            assignedWarehouseIds.value = res.data.assigned_warehouse_ids || [];
            return res.data;
        } catch (err) {
            user.value = null;
            role.value = null;
            roles.value = [];
            permissions.value = [];
            assignedWarehouseIds.value = [];
            throw err;
        } finally {
            isInitialized.value = true;
            loading.value = false;
        }
    };

    const register = async (details) => {
        await getCsrfCookie();
        const res = await api.post("/api/register", details);
        setTabToken(res.data.token);
        user.value = res.data.user;
        role.value = res.data.role;
        roles.value = res.data.roles || [];
        permissions.value = res.data.permissions || [];
        assignedWarehouseIds.value = res.data.assigned_warehouse_ids || [];
        isInitialized.value = true;
        return res.data;
    };

    const login = async (credentials) => {
        clearTabToken();
        await getCsrfCookie();
        const res = await api.post("/api/login", credentials);
        setTabToken(res.data.token);
        user.value = res.data.user;
        role.value = res.data.role;
        roles.value = res.data.roles || [];
        permissions.value = res.data.permissions || [];
        assignedWarehouseIds.value = res.data.assigned_warehouse_ids || [];
        isInitialized.value = true;
        return res.data;
    };

    const clearSession = () => {
        clearTabToken();
        user.value = null;
        role.value = null;
        roles.value = [];
        permissions.value = [];
        assignedWarehouseIds.value = [];
        isInitialized.value = true;
        loading.value = false;
    };

    const logout = async () => {
        try {
            await api.post("/api/logout");
        } finally {
            clearSession();
        }
    };

    return {
        user,
        role,
        roleLabel,
        roles,
        permissions,
        assignedWarehouseIds,
        isInitialized,
        loading,
        isAuthenticated,
        hasPermission,
        fetchMe,
        register,
        login,
        logout,
        clearSession,
    };
});
