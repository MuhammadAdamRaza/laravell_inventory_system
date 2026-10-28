<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex items-center justify-center min-h-screen px-4 text-center sm:p-0">
            <div
                class="fixed inset-0 bg-black/40 transition-opacity"
                @click="$emit('cancel')"
            ></div>

            <div
                class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full p-6 space-y-4"
            >
                <div class="flex items-start space-x-3">
                    <div
                        :class="[
                            'w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0',
                            isDanger
                                ? 'bg-[#FDE8E8] text-[#B42318]'
                                : 'bg-[#E8F1FD] text-[#1E5AA8]',
                        ]"
                    >
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                            />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-[#1F2933]" id="modal-title">
                            {{ title }}
                        </h3>
                        <p class="text-sm text-[#637083] mt-1">{{ message }}</p>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-3">
                    <button
                        type="button"
                        class="px-4 py-2 border border-[#DDE3EA] rounded-md text-sm font-medium text-[#1F2933] bg-white hover:bg-[#F7F9FC] min-h-[44px]"
                        @click="$emit('cancel')"
                    >
                        {{ cancelText }}
                    </button>
                    <button
                        type="button"
                        :class="[
                            'px-4 py-2 rounded-md text-sm font-medium text-white min-h-[44px] transition-colors',
                            isDanger
                                ? 'bg-[#B42318] hover:bg-[#912018]'
                                : 'bg-[#1E5AA8] hover:bg-[#174A8A]',
                        ]"
                        @click="$emit('confirm')"
                    >
                        {{ confirmText }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Confirm Action',
    },
    message: {
        type: String,
        default: 'Are you sure you want to proceed?',
    },
    confirmText: {
        type: String,
        default: 'Confirm',
    },
    cancelText: {
        type: String,
        default: 'Cancel',
    },
    isDanger: {
        type: Boolean,
        default: true,
    },
});

defineEmits(['confirm', 'cancel']);
</script>
