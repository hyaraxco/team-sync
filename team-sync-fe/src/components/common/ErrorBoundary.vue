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
        <div class="w-16 h-16 rounded-full bg-red-50 flex items-center justify-center mb-4">
            <AlertTriangle class="w-8 h-8 text-red-500" />
        </div>
        <h3 class="text-lg font-semibold text-brand-dark mb-1">{{ fallbackTitle }}</h3>
        <p class="text-sm text-brand-light text-center max-w-md">{{ fallbackSubtitle }}</p>
        <button
            v-if="showRetry"
            @click="retry"
            class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 active:scale-[0.97] transition-all duration-150"
        >
            <RefreshCw class="w-4 h-4" />
            Try Again
        </button>
        <slot name="error-details" :error="errorInfo" />
    </div>
</template>