<template>
    <main class="grid min-h-dvh bg-white lg:grid-cols-[.9fr_1.1fr]">
        <aside
            class="auth-artwork relative hidden min-h-dvh flex-col overflow-hidden px-10 py-10 text-white lg:flex xl:px-16"
        >
            <div class="auth-grid pointer-events-none absolute inset-0" aria-hidden="true"></div>
            <div class="relative flex items-center gap-3">
                <span class="brand-mark"><AppIcon name="box" class="h-6 w-6" /></span>
                <p class="text-lg font-semibold tracking-tight">
                    Inventory Hub<span class="text-blue-400">.</span>
                </p>
            </div>
            <div class="relative my-auto max-w-md py-16">
                <p class="mb-5 text-[10px] font-semibold uppercase tracking-[.2em] text-blue-300">
                    YOUR OPERATIONS, CONNECTED
                </p>
                <h2 class="text-4xl font-semibold leading-[1.15] tracking-[-.04em] xl:text-5xl">
                    Clarity for every<br />move you make.
                </h2>
                <p class="mt-6 max-w-sm text-sm leading-7 text-slate-300">
                    Bring your inventory, purchasing and warehouse operations together in one
                    organized workspace.
                </p>
                <div
                    class="my-10 rounded-2xl border border-white/10 bg-white/[.04] p-5"
                    aria-hidden="true"
                >
                    <div class="mb-6 flex items-center justify-between">
                        <span class="text-[11px] font-medium text-slate-300"
                            >A connected workflow</span
                        ><AppIcon name="transfer" class="h-4 w-4 text-blue-300" />
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <div
                            v-for="(step, index) in steps"
                            :key="step.label"
                            class="flex min-w-0 flex-1 flex-col items-center gap-3"
                        >
                            <span
                                class="flex h-12 w-12 items-center justify-center rounded-xl border border-blue-300/15"
                                :class="
                                    index === 2
                                        ? 'bg-blue-500/25 text-blue-200'
                                        : 'bg-white/5 text-slate-300'
                                "
                                ><AppIcon :name="step.icon" class="h-5 w-5" /></span
                            ><span class="text-[10px] text-slate-300">{{ step.label }}</span>
                        </div>
                    </div>
                    <div
                        class="mx-8 mt-5 h-px bg-gradient-to-r from-transparent via-blue-300/40 to-transparent"
                    ></div>
                </div>
                <div class="space-y-4">
                    <div
                        v-for="feature in features"
                        :key="feature.title"
                        class="flex items-start gap-3"
                    >
                        <span
                            class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-400/10 text-blue-300"
                            ><AppIcon name="check" class="h-3.5 w-3.5"
                        /></span>
                        <div>
                            <p class="text-xs font-medium text-white">{{ feature.title }}</p>
                            <p class="mt-1 text-[11px] leading-relaxed text-slate-400">
                                {{ feature.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <p class="relative text-[10px] text-slate-400">
                Inventory, purchasing &amp; warehouse management
            </p>
        </aside>
        <div class="flex min-w-0 flex-col px-6 py-8 sm:px-12 lg:px-16">
            <div class="mb-10 flex items-center gap-3 lg:hidden">
                <span class="brand-mark"><AppIcon name="box" class="h-6 w-6" /></span
                ><span class="text-base font-semibold tracking-tight text-main"
                    >Inventory Hub<span class="text-primary-app">.</span></span
                >
            </div>
            <div class="auth-form mx-auto my-auto w-full max-w-[400px] py-6 sm:py-12">
                <p
                    class="mb-3 text-[10px] font-semibold uppercase tracking-[.16em] text-primary-app"
                >
                    WELCOME TO YOUR WORKSPACE
                </p>
                <h1 class="text-3xl font-semibold tracking-[-.035em] text-main">{{ title }}</h1>
                <p class="mb-8 mt-3 text-[13px] leading-relaxed text-muted">{{ description }}</p>
                <slot />
                <div
                    class="mt-8 flex items-center justify-center gap-2 border-t border-app pt-6 text-[10px] text-muted"
                >
                    <AppIcon name="shield" class="h-3.5 w-3.5" />Access managed by your organization
                </div>
            </div>
            <p class="mt-8 text-center text-[10px] text-muted">
                Inventory Hub · Your operations workspace
            </p>
        </div>
        <ToastContainer />
    </main>
</template>

<script setup>
import AppIcon from '../components/AppIcon.vue';
import ToastContainer from '../components/ToastContainer.vue';
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
const steps = [
    { label: 'Purchase', icon: 'purchase' },
    { label: 'Receive', icon: 'truck' },
    { label: 'Stock', icon: 'box' },
    { label: 'Fulfil', icon: 'sale' },
];
const features = [
    {
        title: 'Know what is in stock',
        description: 'View balances and movements across your warehouses.',
    },
    {
        title: 'Keep every order on track',
        description: 'Follow purchases, sales, transfers and returns.',
    },
    {
        title: 'The right access for every role',
        description: 'A workspace shaped around your responsibilities.',
    },
];
</script>
