<template>
    <Teleport to="body"
        ><Transition name="fade">
            <div
                v-if="isOpen"
                class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-sm"
                @click.self="$emit('cancel')"
            >
                <section
                    ref="dialog"
                    class="w-full max-w-md rounded-2xl border border-app bg-white p-6 shadow-2xl"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="`${dialogId}-title`"
                    :aria-describedby="`${dialogId}-description`"
                    @keydown="handleKeydown"
                >
                    <span
                        class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl"
                        :class="
                            isDanger ? 'bg-rose-50 text-rose-600' : 'bg-blue-50 text-primary-app'
                        "
                        ><AppIcon :name="isDanger ? 'alert' : 'check'" class="h-6 w-6"
                    /></span>
                    <h2
                        :id="`${dialogId}-title`"
                        class="text-lg font-semibold tracking-tight text-main"
                    >
                        {{ title }}
                    </h2>
                    <p
                        :id="`${dialogId}-description`"
                        class="mt-2 text-sm leading-relaxed text-muted"
                    >
                        {{ message }}
                    </p>
                    <div class="mt-7 flex justify-end gap-3">
                        <button
                            ref="cancelButton"
                            type="button"
                            class="ui-button"
                            @click="$emit('cancel')"
                        >
                            {{ cancelText }}</button
                        ><button
                            type="button"
                            class="ui-button"
                            :class="isDanger ? 'danger' : 'primary'"
                            @click="$emit('confirm')"
                        >
                            {{ confirmText }}
                        </button>
                    </div>
                </section>
            </div>
        </Transition></Teleport
    >
</template>
<script setup>
import { nextTick, onUnmounted, ref, useId, watch } from 'vue';
import AppIcon from './AppIcon.vue';
const props = defineProps({
    isOpen: { type: Boolean, default: false },
    title: { type: String, default: 'Confirm action' },
    message: { type: String, default: 'Are you sure you want to proceed?' },
    confirmText: { type: String, default: 'Confirm' },
    cancelText: { type: String, default: 'Cancel' },
    isDanger: { type: Boolean, default: true },
});
const emit = defineEmits(['confirm', 'cancel']);
const dialogId = useId();
const dialog = ref(null);
const cancelButton = ref(null);
let previousFocus;
let previousOverflow;
watch(
    () => props.isOpen,
    async (open) => {
        if (open) {
            previousFocus = document.activeElement;
            previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            await nextTick();
            cancelButton.value?.focus();
        } else {
            document.body.style.overflow = previousOverflow || '';
            previousFocus?.focus();
        }
    },
);
onUnmounted(() => {
    if (props.isOpen) document.body.style.overflow = previousOverflow || '';
});
function handleKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        emit('cancel');
    }
    if (event.key !== 'Tab') return;
    const buttons = [...dialog.value.querySelectorAll('button')];
    if (event.shiftKey && document.activeElement === buttons[0]) {
        event.preventDefault();
        buttons.at(-1)?.focus();
    } else if (!event.shiftKey && document.activeElement === buttons.at(-1)) {
        event.preventDefault();
        buttons[0]?.focus();
    }
}
</script>
