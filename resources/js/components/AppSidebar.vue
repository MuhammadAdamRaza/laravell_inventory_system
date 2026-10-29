<template>
    <div>
        <Transition name="fade"
            ><div
                v-if="isOpen"
                class="fixed inset-0 z-40 bg-slate-950/45 backdrop-blur-sm lg:hidden"
                aria-hidden="true"
                @click="emit('close')"
            ></div
        ></Transition>
        <aside
            id="main-navigation"
            ref="sidebarElement"
            class="app-sidebar fixed inset-y-0 left-0 z-50 flex w-64 max-w-[calc(100vw-2rem)] flex-col lg:visible lg:translate-x-0"
            :class="isOpen ? 'visible translate-x-0' : 'invisible -translate-x-full'"
            aria-label="Primary navigation"
            :role="isOpen ? 'dialog' : undefined"
            :aria-modal="isOpen ? true : undefined"
            @keydown="handleKeydown"
        >
            <div class="flex h-20 shrink-0 items-center justify-between gap-2 px-5">
                <div class="flex items-center gap-3">
                    <span class="brand-mark"><AppIcon name="box" class="h-6 w-6" /></span>
                    <div>
                        <p class="text-[16px] font-bold tracking-tight text-white">
                            Inventory Hub<span class="text-blue-400">.</span>
                        </p>
                        <p class="mt-0.5 text-[9px] font-medium tracking-[.13em] text-slate-400">
                            INVENTORY WORKSPACE
                        </p>
                    </div>
                </div>
                <button
                    ref="closeButton"
                    type="button"
                    class="sidebar-close lg:hidden"
                    aria-label="Close navigation"
                    @click="emit('close')"
                >
                    <AppIcon name="close" class="h-5 w-5" />
                </button>
            </div>
            <nav class="sidebar-nav-scroll min-h-0 flex-1 overflow-y-auto px-3 pb-6">
                <section
                    v-for="group in groups"
                    :key="group.label"
                    class="mt-5 first:mt-2"
                    :aria-label="group.label"
                >
                    <h2
                        class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[.16em] text-slate-400"
                    >
                        {{ group.label }}
                    </h2>
                    <div class="space-y-0.5">
                        <router-link
                            v-for="item in group.items"
                            :key="item.name"
                            :to="item.to"
                            class="sidebar-link"
                            :class="{ 'is-active': active?.name === item.name }"
                            :aria-current="active?.name === item.name ? 'page' : undefined"
                            @click="emit('close')"
                            ><AppIcon :name="item.icon" class="h-[18px] w-[18px]" /><span
                                class="min-w-0 flex-1 truncate"
                                >{{ item.label }}</span
                            ><span
                                v-if="active?.name === item.name"
                                class="h-1.5 w-1.5 rounded-full bg-blue-300"
                                aria-hidden="true"
                            ></span
                        ></router-link>
                    </div>
                </section>
            </nav>
            <div
                class="mx-3 mb-3 flex shrink-0 items-center gap-3 rounded-xl border border-white/10 bg-white/[.04] p-3"
            >
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-700/70 text-xs font-semibold text-white"
                    >{{ initials }}</span
                >
                <div class="min-w-0">
                    <p class="truncate text-[13px] font-semibold text-white">
                        {{ auth.roleLabel }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-slate-400">Your workspace</p>
                </div>
                <AppIcon name="shield" class="ml-auto h-4 w-4 text-slate-400" />
            </div>
        </aside>
    </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import AppIcon from './AppIcon.vue';
import { getActiveNavigation, getNavigationGroups } from './navigation';
const props = defineProps({ isOpen: { type: Boolean, default: false } });
const emit = defineEmits(['close']);
const auth = useAuthStore();
const route = useRoute();
const sidebarElement = ref(null);
const closeButton = ref(null);
const groups = computed(() => getNavigationGroups(auth));
const active = computed(() => getActiveNavigation(route.path, groups.value));
const initials = computed(() =>
    auth.roleLabel
        .split(' ')
        .map((word) => word[0])
        .slice(0, 2)
        .join(''),
);
let previousFocus = null;
watch(
    () => props.isOpen,
    async (open) => {
        if (open) {
            previousFocus = document.activeElement;
            await nextTick();
            closeButton.value?.focus();
        } else if (previousFocus instanceof HTMLElement) {
            await nextTick();
            previousFocus.focus();
            previousFocus = null;
        }
    },
);
function handleKeydown(event) {
    if (!props.isOpen) return;
    if (event.key === 'Escape') emit('close');
    if (event.key !== 'Tab') return;
    const elements = [...sidebarElement.value.querySelectorAll('a[href], button:not([disabled])')];
    const first = elements[0];
    const last = elements.at(-1);
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
}
</script>
