export const navigationGroups = [
    {
        label: 'Overview',
        items: [
            {
                name: 'dashboard',
                label: 'Dashboard',
                to: '/dashboard',
                permission: 'reports.view',
                icon: 'dashboard',
            },
            {
                name: 'reports',
                label: 'Reports',
                to: '/reports',
                permission: 'reports.view',
                icon: 'chart',
            },
            {
                name: 'report-exports',
                label: 'Report exports',
                to: '/reports/exports',
                permission: 'reports.export',
                icon: 'download',
            },
        ],
    },
    {
        label: 'Inventory',
        items: [
            {
                name: 'balances',
                label: 'Stock balances',
                to: '/inventory/balances',
                permission: 'inventory.view',
                icon: 'stock',
            },
            {
                name: 'movements',
                label: 'Movement logs',
                to: '/inventory/movements',
                permission: 'inventory.view',
                icon: 'history',
            },
            {
                name: 'products',
                label: 'Products',
                to: '/products',
                permission: 'products.view',
                icon: 'box',
            },
            {
                name: 'categories',
                label: 'Categories',
                to: '/categories',
                permission: 'categories.view',
                icon: 'folder',
            },
            {
                name: 'warehouses',
                label: 'Warehouses',
                to: '/warehouses',
                permission: 'warehouses.view',
                icon: 'warehouse',
            },
        ],
    },
    {
        label: 'Operations',
        items: [
            {
                name: 'purchases',
                label: 'Purchases',
                to: '/purchases',
                permission: 'purchases.view',
                icon: 'purchase',
            },
            {
                name: 'suppliers',
                label: 'Suppliers',
                to: '/suppliers',
                permission: 'suppliers.view',
                icon: 'truck',
            },
            {
                name: 'sales-orders',
                label: 'Sales orders',
                to: '/sales-orders',
                permission: 'sales.view',
                icon: 'sale',
            },
            {
                name: 'customers',
                label: 'Customers',
                to: '/customers',
                permission: 'customers.view',
                icon: 'users',
            },
            {
                name: 'sales-returns',
                label: 'Customer returns',
                to: '/sales-returns',
                permission: 'returns.view',
                icon: 'returns',
            },
            {
                name: 'transfers',
                label: 'Warehouse transfers',
                to: '/transfers',
                permission: 'transfers.view',
                icon: 'transfer',
            },
            {
                name: 'stock-adjustments',
                label: 'Stock adjustments',
                to: '/stock-adjustments',
                permission: 'adjustments.view',
                icon: 'sliders',
            },
        ],
    },
    {
        label: 'Workspace',
        items: [
            {
                name: 'notifications',
                label: 'Notifications',
                to: '/notifications',
                showForAuthenticated: true,
                icon: 'bell',
            },
            {
                name: 'users',
                label: 'User management',
                to: '/users',
                permission: 'users.manage',
                icon: 'users',
            },
            {
                name: 'system-settings',
                label: 'System settings',
                to: '/system/settings',
                permission: 'users.manage',
                icon: 'settings',
            },
            {
                name: 'failed-jobs',
                label: 'Failed jobs',
                to: '/system/failed-jobs',
                permission: 'users.manage',
                icon: 'alert',
            },
        ],
    },
];

const staffLabels = {
    balances: 'Assigned stock',
    movements: 'Stock movements',
    warehouses: 'My warehouse',
    purchases: 'Purchase receiving',
    'sales-returns': 'Return receiving',
    'stock-adjustments': 'Adjustment history',
};

export function getNavigationGroups(auth) {
    return navigationGroups
        .map((group) => ({
            ...group,
            items: group.items
                .filter((item) =>
                    item.permission
                        ? auth.hasPermission(item.permission)
                        : item.showForAuthenticated && auth.isAuthenticated,
                )
                .map((item) => ({
                    ...item,
                    group: group.label,
                    label:
                        auth.role === 'Warehouse Staff'
                            ? staffLabels[item.name] || item.label
                            : item.label,
                })),
        }))
        .filter((group) => group.items.length);
}

export function getActiveNavigation(path, groups) {
    return groups
        .flatMap((group) => group.items)
        .filter((item) => path === item.to || path.startsWith(`${item.to}/`))
        .sort((a, b) => b.to.length - a.to.length)[0];
}
