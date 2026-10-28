<template>
    <div
        class="fixed top-4 right-4 z-50 flex flex-col space-y-2 pointer-events-none max-w-sm w-full px-4 sm:px-0"
    >
        <transition-group
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-for="toast in toastStore.toasts"
                :key="toast.id"
                :class="[
                    'pointer-events-auto flex items-center justify-between p-4 rounded-lg shadow-md border text-sm font-medium',
                    toast.type === 'success'
                        ? 'bg-[#E6F4EA] border-[#1E7B3A]/30 text-[#1E7B3A]'
                        : toast.type === 'error'
                          ? 'bg-[#FDE8E8] border-[#B42318]/30 text-[#B42318]'
                          : 'bg-[#E8F1FD] border-[#1E5AA8]/30 text-[#1E5AA8]',
                ]"
            >
                <div class="flex items-center space-x-2">
                    <svg
                        v-if="toast.type === 'success'"
                        class="w-5 h-5 flex-shrink-0"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M5 13l4 4L19 7"
                        />
                    </svg>
                    <svg
                        v-else-if="toast.type === 'error'"
                        class="w-5 h-5 flex-shrink-0"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <svg
                        v-else
                        class="w-5 h-5 flex-shrink-0"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <span>{{ toast.message }}</span>
                </div>
                <button
                    type="button"
                    class="ml-3 inline-flex text-current opacity-70 hover:opacity-100"
                    @click="toastStore.remove(toast.id)"
                >
                    <svg
                        class="w-4 h-4"
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
        </transition-group>
    </div>
</template>

<script setup>
import { useToastStore } from "../stores/toast";

const toastStore = useToastStore();
</script>
