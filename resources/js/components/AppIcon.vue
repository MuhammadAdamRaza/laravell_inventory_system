<template>
    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.7"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        class="shrink-0"
    >
        <path v-for="(path, index) in paths[name] || paths.box" :key="index" :d="path" />
    </svg>
</template>

<script setup>
defineProps({ name: { type: String, default: 'box' } });
const paths = {
    box: ['M12 3 3 7.5v9L12 21l9-4.5v-9L12 3Z', 'm3 7.5 9 4.5 9-4.5M12 12v9M7.5 5.25l9 4.5'],
    dashboard: ['M3 3h7v7H3zM14 3h7v11h-7zM3 14h7v7H3zM14 18h7v3h-7z'],
    chart: ['M3 3v18h18M7 16v-5M12 16V7M17 16v-8'],
    download: ['M12 3v12m-4-4 4 4 4-4M4 16v4h16v-4'],
    stock: ['M3 20V10h4v10M10 20V4h4v16M17 20v-7h4v7'],
    history: ['M3 11a9 9 0 1 1 2.6 7.4M3 4v7h7M12 7v5l3 2'],
    folder: ['M3 7V5h6l2 3h10v12H3V7Z'],
    warehouse: ['m3 10 9-7 9 7v11H3V10Z', 'M8 21V11h8v10M8 15h8M8 18h8'],
    truck: [
        'M3 5h11v12H3V5ZM14 9h4l3 4v4h-7',
        'M7 17a2 2 0 1 0 0 4 2 2 0 0 0 0-4ZM17 17a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z',
    ],
    purchase: ['M5 3h14v18l-3-2-4 2-4-2-3 2V3ZM9 7h6M9 11h6M9 15h3'],
    sale: ['M4 7h16l-1 14H5L4 7ZM8 7V6a4 4 0 0 1 8 0v1'],
    returns: ['M9 7H4l4-4M4 7a8 8 0 1 1-1 8', 'M11 10h5v5h-5z'],
    users: [
        'M8 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM1 21v-3a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v3M16 3a4 4 0 0 1 0 8M18 14a5 5 0 0 1 5 5v2',
    ],
    transfer: ['M3 7h17m-4-4 4 4-4 4M21 17H4m4-4-4 4 4 4'],
    sliders: ['M4 3v7m0 4v7M12 3v11m0 4v3M20 3v3m0 4v11M1 10h6M9 14h6M17 6h6'],
    settings: [
        'M9 3h6l1 3 3 1 2 5-2 5-3 1-1 3H9l-1-3-3-1-2-5 2-5 3-1 1-3Z',
        'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z',
    ],
    bell: ['M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M9 21h6'],
    alert: ['m12 3 10 18H2L12 3ZM12 9v5M12 17h.01'],
    search: ['M10.5 3a7.5 7.5 0 1 0 0 15 7.5 7.5 0 0 0 0-15ZM16 16l5 5'],
    close: ['m6 6 12 12M6 18 18 6'],
    menu: ['M4 6h16M4 12h16M4 18h16'],
    arrow: ['M4 12h16m-6-6 6 6-6 6'],
    chevron: ['m9 5 7 7-7 7'],
    left: ['m15 5-7 7 7 7'],
    plus: ['M12 5v14M5 12h14'],
    refresh: ['M20 7A9 9 0 0 0 4 7M20 3v4h-4M4 17a9 9 0 0 0 16 0M4 21v-4h4'],
    logout: ['M9 4H3v16h6M9 12h12m-4-4 4 4-4 4'],
    check: ['m5 12 4 4L19 6'],
    shield: ['m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z', 'm8 12 3 3 5-6'],
    calendar: ['M4 5h16v16H4V5ZM4 10h16M8 3v4M16 3v4'],
    money: ['M3 6h18v12H3V6ZM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6ZM6 12h.01M18 12h.01'],
    clock: ['M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18ZM12 7v5l3 2'],
    sort: ['M8 4v16m-3-3 3 3 3-3M16 20V4m-3 3 3-3 3 3'],
};
</script>
