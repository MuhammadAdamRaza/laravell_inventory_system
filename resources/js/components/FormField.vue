<template>
    <div class="space-y-2">
        <label v-if="label" :for="id" class="block text-xs font-semibold text-main"
            >{{ label }}
            <span v-if="required" class="text-red-600" aria-hidden="true">*</span></label
        >
        <slot
            :has-error="!!errorText"
            :input-class="inputClasses"
            :input-attrs="{
                'aria-invalid': !!errorText,
                'aria-describedby': errorText || hint ? `${id}-help` : undefined,
            }"
        />
        <p v-if="errorText" :id="`${id}-help`" class="text-xs text-red-700" role="alert">
            {{ errorText }}
        </p>
        <p v-else-if="hint" :id="`${id}-help`" class="text-xs leading-relaxed text-muted">
            {{ hint }}
        </p>
    </div>
</template>
<script setup>
import { computed } from 'vue';
const props = defineProps({
    label: { type: String, default: '' },
    id: { type: String, default: '' },
    required: { type: Boolean, default: false },
    error: { type: [String, Array], default: '' },
    hint: { type: String, default: '' },
});
const errorText = computed(() => (Array.isArray(props.error) ? props.error[0] : props.error));
const inputClasses = computed(
    () =>
        `w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-main min-h-[44px] transition-colors focus:outline-none ${errorText.value ? 'border-red-500 focus:ring-2 focus:ring-red-100' : 'border-app focus:border-blue-500 focus:ring-2 focus:ring-blue-100'}`,
);
</script>
