<script setup>
import { ref, onErrorCaptured } from 'vue'
import { AlertTriangle, RefreshCw } from 'lucide-vue-next'

const props = defineProps({
    fallbackTitle: {
        type: String,
        default: 'Something went wrong'
    },
    fallbackSubtitle: {
        type: String,
        default: 'An unexpected error occurred. Please try again.'
    },
    showRetry: {
        type: Boolean,
        default: true
    }
})

const emit = defineEmits(['error'])

const hasError = ref(false)
const errorInfo = ref(null)

onErrorCaptured((err, instance, info) => {
    hasError.value = true
    errorInfo.value = { error: err, info }
    emit('error', { error: err, info })
    // Return false to prevent error from propagating further
    return false
})

const retry = () => {
    hasError.value = false
    errorInfo.value = null
}
</script>

<template>
    <slot v-if="!hasError" />
    <div v-else class="flex flex-col items-center justify-center py-12 px-4">
        <div class="w-16 h-16 rounded-full bg-[var(--color-surface)] flex items-center justify-center mb-4">
            <AlertTriangle aria-hidden="true" class="w-8 h-8 text-[var(--color-feedback-danger)]" />
        </div>
        <div role="alert" aria-atomic="true">
            <h3 class="text-lg font-semibold text-[var(--color-text-primary)] mb-1">{{ fallbackTitle }}</h3>
            <p class="text-sm text-[var(--color-text-secondary)] text-center max-w-md">{{ fallbackSubtitle }}</p>
        </div>
        <button
            v-if="showRetry"
            @click="retry"
            type="button"
            class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white bg-primary-700 hover:bg-primary-800 motion-reduce:transition-none transition-colors duration-150"
        >
            <RefreshCw aria-hidden="true" class="w-4 h-4" />
            Try Again
        </button>
        <slot name="error-details" :error="errorInfo" />
    </div>
</template>