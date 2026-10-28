<template>
    <div class="space-y-1.5">
        <label v-if="label" :for="id" class="block text-[13px] font-medium text-[#637083]">
            {{ label }}
            <span v-if="required" class="text-[#B42318]">*</span>
        </label>

        <div>
            <slot :has-error="!!error" :input-class="inputClasses"></slot>
        </div>

        <p v-if="error" class="text-xs text-[#B42318] mt-1">
            {{ error }}
        </p>
        <p v-else-if="hint" class="text-xs text-[#637083] mt-1">
            {{ hint }}
        </p>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: {
        type: String,
        default: '',
    },
    id: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    hint: {
        type: String,
        default: '',
    },
});

const inputClasses = computed(() => {
    const base =
        'w-full px-3 py-2 text-sm bg-white rounded-md border min-h-[44px] transition-colors focus:outline-none';
    if (props.error) {
        return `${base} border-[#B42318] focus:ring-1 focus:ring-[#B42318] text-[#1F2933]`;
    }
    return `${base} border-[#DDE3EA] focus:border-[#1E5AA8] focus:ring-1 focus:ring-[#1E5AA8] text-[#1F2933]`;
});
</script>
