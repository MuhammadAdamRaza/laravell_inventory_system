<template>
    <div
        class="pointer-events-none fixed inset-x-4 bottom-4 z-[90] flex max-w-sm flex-col gap-2 sm:left-auto sm:right-5 sm:w-full"
        aria-live="polite"
        aria-atomic="false"
    >
        <TransitionGroup name="popover"
            ><div
                v-for="toast in toastStore.toasts"
                :key="toast.id"
                class="pointer-events-auto flex items-start gap-3 rounded-xl border border-app bg-white p-4 shadow-lg"
                :role="toast.type === 'error' ? 'alert' : 'status'"
            >
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                    :class="
                        toast.type === 'error'
                            ? 'bg-rose-50 text-rose-600'
                            : toast.type === 'success'
                              ? 'bg-emerald-50 text-emerald-600'
                              : 'bg-blue-50 text-primary-app'
                    "
                    ><AppIcon
                        :name="
                            toast.type === 'error'
                                ? 'alert'
                                : toast.type === 'success'
                                  ? 'check'
                                  : 'bell'
                        "
                        class="h-4 w-4"
                /></span>
                <p class="min-w-0 flex-1 self-center break-words text-xs leading-relaxed text-main">
                    {{ toast.message }}
                </p>
                <button
                    type="button"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-muted hover:bg-slate-50"
                    aria-label="Dismiss notification"
                    @click="toastStore.remove(toast.id)"
                >
                    <AppIcon name="close" class="h-4 w-4" />
                </button></div
        ></TransitionGroup>
    </div>
</template>
<script setup>
import { useToastStore } from '../stores/toast';
import AppIcon from './AppIcon.vue';
const toastStore = useToastStore();
</script>
