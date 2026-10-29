import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import AdminLayout from '../layouts/AdminLayout.vue';

const routes = [
    {
        path: '/register',
        name: 'register',
        component: () => import('../views/auth/RegisterView.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/auth/LoginView.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: () => import('../views/auth/ForgotPasswordView.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/reset-password',
        name: 'reset-password',
        component: () => import('../views/auth/ResetPasswordView.vue'),
    },
    {
        path: '/',
        component: AdminLayout,
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                redirect: { name: 'dashboard' },
            },
            {
                path: 'forbidden',
                name: 'access-denied',
                component: () => import('../views/system/AccessDeniedView.vue'),
            },
            {
                path: 'dashboard',
                name: 'dashboard',
                component: () => import('../views/reports/DashboardView.vue'),
                meta: { permission: 'reports.view' },
            },
            {
                path: 'reports',
                name: 'reports',
                component: () => import('../views/reports/ReportsView.vue'),
                meta: { permission: 'reports.view' },
            },
            {
                path: 'reports/exports',
                name: 'report-exports',
                component: () => import('../views/reports/ReportExportsView.vue'),
                meta: { permission: 'reports.export' },
            },
            {
                path: 'reports/:reportKind',
                name: 'report-detail',
                component: () => import('../views/reports/ReportDetailView.vue'),
                meta: { permission: 'reports.view' },
            },
            {
                path: 'inventory/balances',
                name: 'inventory-balances',
                component: () => import('../views/inventory/BalancesView.vue'),
                meta: { permission: 'inventory.view' },
            },
            {
                path: 'inventory/movements',
                name: 'inventory-movements',
                component: () => import('../views/inventory/MovementsView.vue'),
                meta: { permission: 'inventory.view' },
            },
            {
                path: 'products',
                name: 'products',
                component: () => import('../views/products/ProductsView.vue'),
                meta: { permission: 'products.view' },
            },
            {
                path: 'categories',
                name: 'categories',
                component: () => import('../views/categories/CategoriesView.vue'),
                meta: { permission: 'categories.view' },
            },
            {
                path: 'warehouses',
                name: 'warehouses',
                component: () => import('../views/warehouses/WarehousesView.vue'),
                meta: { permission: 'warehouses.view' },
            },
            {
                path: 'suppliers/:id',
                name: 'supplier-detail',
                component: () => import('../views/suppliers/SupplierDetailView.vue'),
                meta: { permission: 'suppliers.view' },
            },
            {
                path: 'purchases',
                name: 'purchases',
                component: () => import('../views/purchases/PurchasesView.vue'),
                meta: { permission: 'purchases.view' },
            },
            {
                path: 'purchases/create',
                name: 'purchase-create',
                component: () => import('../views/purchases/PurchaseFormView.vue'),
                meta: { permission: 'purchases.create' },
            },
            {
                path: 'purchases/:id/edit',
                name: 'purchase-edit',
                component: () => import('../views/purchases/PurchaseFormView.vue'),
                meta: { permission: 'purchases.update' },
            },
            {
                path: 'purchases/:id',
                name: 'purchase-detail',
                component: () => import('../views/purchases/PurchaseDetailView.vue'),
                meta: { permission: 'purchases.view' },
            },
            {
                path: 'customers',
                name: 'customers',
                component: () => import('../views/customers/CustomersView.vue'),
                meta: { permission: 'customers.view' },
            },
            {
                path: 'customers/:id',
                name: 'customer-detail',
                component: () => import('../views/customers/CustomerDetailView.vue'),
                meta: { permission: 'customers.view' },
            },
            {
                path: 'sales-orders',
                name: 'sales-orders',
                component: () => import('../views/sales/SalesOrdersView.vue'),
                meta: { permission: 'sales.view' },
            },
            {
                path: 'sales-orders/create',
                name: 'sales-order-create',
                component: () => import('../views/sales/SalesOrderFormView.vue'),
                meta: { permission: 'sales.create' },
            },
            {
                path: 'sales-orders/:id/edit',
                name: 'sales-order-edit',
                component: () => import('../views/sales/SalesOrderFormView.vue'),
                meta: { permission: 'sales.update' },
            },
            {
                path: 'sales-orders/:id',
                name: 'sales-order-detail',
                component: () => import('../views/sales/SalesOrderDetailView.vue'),
                meta: { permission: 'sales.view' },
            },
            {
                path: 'sales-returns',
                name: 'sales-returns',
                component: () => import('../views/returns/SalesReturnsView.vue'),
                meta: { permission: 'returns.view' },
            },
            {
                path: 'sales-returns/create',
                name: 'sales-return-create',
                component: () => import('../views/returns/SalesReturnFormView.vue'),
                meta: { permission: 'returns.create' },
            },
            {
                path: 'sales-returns/:id/edit',
                name: 'sales-return-edit',
                component: () => import('../views/returns/SalesReturnFormView.vue'),
                meta: { permission: 'returns.update' },
            },
            {
                path: 'sales-returns/:id',
                name: 'sales-return-detail',
                component: () => import('../views/returns/SalesReturnDetailView.vue'),
                meta: { permission: 'returns.view' },
            },
            {
                path: 'transfers',
                name: 'transfers',
                component: () => import('../views/transfers/TransfersView.vue'),
                meta: { permission: 'transfers.view' },
            },
            {
                path: 'transfers/create',
                name: 'transfer-create',
                component: () => import('../views/transfers/TransferFormView.vue'),
                meta: { permission: 'transfers.create' },
            },
            {
                path: 'transfers/:id/edit',
                name: 'transfer-edit',
                component: () => import('../views/transfers/TransferFormView.vue'),
                meta: { permission: 'transfers.create' },
            },
            {
                path: 'transfers/:id',
                name: 'transfer-detail',
                component: () => import('../views/transfers/TransferDetailView.vue'),
                meta: { permission: 'transfers.view' },
            },
            {
                path: 'stock-adjustments',
                name: 'stock-adjustments',
                component: () => import('../views/adjustments/AdjustmentsView.vue'),
                meta: { permission: 'adjustments.view' },
            },
            {
                path: 'stock-adjustments/create',
                name: 'stock-adjustment-create',
                component: () => import('../views/adjustments/AdjustmentFormView.vue'),
                meta: { permission: 'inventory.adjust' },
            },
            {
                path: 'stock-adjustments/:id',
                name: 'stock-adjustment-detail',
                component: () => import('../views/adjustments/AdjustmentDetailView.vue'),
                meta: { permission: 'adjustments.view' },
            },
            {
                path: 'suppliers',
                name: 'suppliers',
                component: () => import('../views/suppliers/SuppliersView.vue'),
                meta: { permission: 'suppliers.view' },
            },
            {
                path: 'system/settings',
                name: 'system-settings',
                component: () => import('../views/system/SystemSettingsView.vue'),
                meta: { permission: 'users.manage' },
            },
            {
                path: 'users',
                name: 'users',
                component: () => import('../views/users/UsersView.vue'),
                meta: { permission: 'users.manage' },
            },
            {
                path: 'notifications',
                name: 'notifications',
                component: () => import('../views/notifications/NotificationsView.vue'),
            },
            {
                path: 'system/failed-jobs',
                name: 'failed-jobs',
                component: () => import('../views/system/FailedJobsView.vue'),
                meta: { permission: 'users.manage' },
            },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();

    if (!authStore.isInitialized) {
        try {
            await authStore.fetchMe();
        } catch (err) {
            // Unauthenticated
        }
    }

    if (to.meta.guestOnly && authStore.isAuthenticated) {
        return next({ name: 'dashboard' });
    }

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return next({ name: 'login', query: { redirect: to.fullPath } });
    }

    if (to.meta.permission && !authStore.hasPermission(to.meta.permission)) {
        return next({ name: 'access-denied', query: { from: to.fullPath } });
    }

    next();
});

// Handle stale Vite chunk files from previous builds (reloads the page to load fresh assets)
if (typeof window !== 'undefined') {
    window.addEventListener('vite:preloadError', (event) => {
        event.preventDefault();
        window.location.reload();
    });
}

router.onError((error, to) => {
    const isChunkLoadFailed =
        error?.message?.includes('Failed to fetch dynamically imported module') ||
        error?.message?.includes('Importing a module script failed') ||
        error?.message?.includes('error loading dynamically imported module') ||
        error?.name === 'ChunkLoadError';

    if (isChunkLoadFailed) {
        const targetUrl = to?.fullPath || window.location.href;
        const reloadKey = 'vite_chunk_reload_' + (to?.path || window.location.pathname);
        if (!sessionStorage.getItem(reloadKey)) {
            sessionStorage.setItem(reloadKey, 'true');
            window.location.href = targetUrl;
        }
    }
});

router.afterEach(() => {
    try {
        for (let i = sessionStorage.length - 1; i >= 0; i--) {
            const key = sessionStorage.key(i);
            if (key && key.startsWith('vite_chunk_reload_')) {
                sessionStorage.removeItem(key);
            }
        }
    } catch {
        // Ignore storage access errors
    }
});

export default router;
