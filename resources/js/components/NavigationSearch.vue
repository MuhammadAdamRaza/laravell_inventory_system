<template>
    <Teleport to="body"
        ><Transition name="fade">
            <div
                v-if="isOpen"
                class="fixed inset-0 z-[70] flex items-start justify-center bg-slate-950/40 px-4 pt-[12vh] backdrop-blur-sm"
                @click.self="emit('close')"
            >
                <section
                    ref="dialog"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="navigation-search-title"
                    class="w-full max-w-xl overflow-hidden rounded-2xl border border-white/40 bg-white shadow-2xl"
                    @keydown="handleKeydown"
                >
                    <h2 id="navigation-search-title" class="sr-only">Search workspace pages</h2>
                    <div class="flex items-center gap-3 border-b border-app p-4">
                        <AppIcon name="search" class="h-5 w-5 text-primary-app" /><input
                            ref="searchInput"
                            v-model="query"
                            class="min-w-0 flex-1 border-0 bg-transparent text-base outline-none"
                            placeholder="Where would you like to go?"
                            aria-label="Search pages"
                            role="combobox"
                            aria-controls="navigation-results"
                            aria-autocomplete="list"
                            :aria-expanded="true"
                            :aria-activedescendant="
                                results.length ? `navigation-result-${selected}` : undefined
                            "
                        /><button
                            type="button"
                            class="icon-button"
                            aria-label="Close search"
                            @click="emit('close')"
                        >
                            <AppIcon name="close" class="h-4 w-4" />
                        </button>
                    </div>
                    <p
                        class="px-5 pb-2 pt-4 text-[10px] font-semibold uppercase tracking-[.14em] text-muted"
                    >
                        {{ query ? 'Matching pages' : 'Your workspace' }}
                    </p>
                    <ul
                        id="navigation-results"
                        role="listbox"
                        aria-label="Available pages"
                        class="max-h-[50dvh] overflow-y-auto px-2 pb-2"
                    >
                        <li
                            v-for="(item, index) in results"
                            :id="`navigation-result-${index}`"
                            :key="item.name"
                            role="option"
                            :aria-selected="selected === index"
                        >
                            <button
                                type="button"
                                tabindex="-1"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-3 text-left"
                                :class="
                                    selected === index
                                        ? 'bg-blue-50 text-primary-app'
                                        : 'text-main hover:bg-slate-50'
                                "
                                @click="navigate(item)"
                                @mousemove="selected = index"
                            >
                                <AppIcon :name="item.icon" class="h-5 w-5" /><span
                                    class="flex-1 text-sm font-medium"
                                    >{{ item.label }}</span
                                ><span class="hidden text-xs text-muted sm:inline">{{
                                    item.group
                                }}</span
                                ><AppIcon name="arrow" class="h-4 w-4 opacity-50" />
                            </button>
                        </li>
                    </ul>
                    <p v-if="!results.length" class="px-5 py-10 text-center text-sm text-muted">
                        No pages match “{{ query }}”. Try a different name.
                    </p>
                    <div
                        class="flex items-center justify-between border-t border-app bg-slate-50 px-5 py-3 text-[11px] text-muted"
                    >
                        <span>↑ ↓ to navigate · Enter to open</span><span>Esc to close</span>
                    </div>
                </section>
            </div>
        </Transition></Teleport
    >
</template>

<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { getNavigationGroups } from './navigation';
import AppIcon from './AppIcon.vue';
const props = defineProps({ isOpen: { type: Boolean, default: false } });
const emit = defineEmits(['close']);
const router = useRouter();
const auth = useAuthStore();
const searchInput = ref(null);
const dialog = ref(null);
const query = ref('');
const selected = ref(0);
let previousFocus;
let previousOverflow;
const results = computed(() =>
    getNavigationGroups(auth)
        .flatMap((group) => group.items)
        .filter((item) =>
            `${item.label} ${item.group}`.toLowerCase().includes(query.value.trim().toLowerCase()),
        ),
);
watch(query, () => {
    selected.value = 0;
});
watch(
    () => props.isOpen,
    async (open) => {
        if (open) {
            previousFocus = document.activeElement;
            previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            query.value = '';
            selected.value = 0;
            await nextTick();
            searchInput.value?.focus();
        } else {
            document.body.style.overflow = previousOverflow || '';
            previousFocus?.focus();
        }
    },
);
onUnmounted(() => {
    if (props.isOpen) document.body.style.overflow = previousOverflow || '';
});
function navigate(item) {
    emit('close');
    router.push(item.to);
}
async function handleKeydown(event) {
    if (event.key === 'Escape') emit('close');
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        selected.value = Math.max(
            0,
            Math.min(
                results.value.length - 1,
                selected.value + (event.key === 'ArrowDown' ? 1 : -1),
            ),
        );
        await nextTick();
        document
            .getElementById(`navigation-result-${selected.value}`)
            ?.scrollIntoView({ block: 'nearest' });
    }
    if (
        event.key === 'Enter' &&
        event.target === searchInput.value &&
        results.value[selected.value]
    ) {
        event.preventDefault();
        navigate(results.value[selected.value]);
    }
    if (event.key === 'Tab') {
        const elements = [...dialog.value.querySelectorAll('input, button:not([tabindex="-1"])')];
        if (event.shiftKey && document.activeElement === elements[0]) {
            event.preventDefault();
            elements.at(-1)?.focus();
        } else if (!event.shiftKey && document.activeElement === elements.at(-1)) {
            event.preventDefault();
            elements[0]?.focus();
        }
    }
}
</script>
