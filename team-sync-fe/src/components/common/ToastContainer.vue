<script setup>
import { useToast } from "@/composables/useToast";
import { CheckCircle, XCircle, AlertTriangle, Info, X } from "lucide-vue-next";

const { toasts, removeToast } = useToast();

const iconMap = {
    success: CheckCircle,
    error: XCircle,
    warning: AlertTriangle,
    info: Info,
};

const styleMap = {
    success: {
        bg: "bg-white",
        border: "border-green-200",
        icon: "text-green-500",
        accent: "bg-green-500",
        title: "text-green-800",
        message: "text-green-600",
    },
    error: {
        bg: "bg-white",
        border: "border-red-200",
        icon: "text-red-500",
        accent: "bg-red-500",
        title: "text-red-800",
        message: "text-red-600",
    },
    warning: {
        bg: "bg-white",
        border: "border-yellow-200",
        icon: "text-yellow-500",
        accent: "bg-yellow-500",
        title: "text-yellow-800",
        message: "text-yellow-600",
    },
    info: {
        bg: "bg-white",
        border: "border-blue-200",
        icon: "text-blue-500",
        accent: "bg-blue-500",
        title: "text-blue-800",
        message: "text-blue-600",
    },
};
</script>

<template>
    <Teleport to="body">
        <div
            role="status"
            aria-live="polite"
            aria-relevant="additions"
            class="fixed top-6 right-6 z-[9999] flex flex-col gap-3 pointer-events-none"
            style="min-width: 360px; max-width: 420px"
        >
            <TransitionGroup
                enter-active-class="transform transition-all duration-300 ease-out"
                enter-from-class="translate-x-full opacity-0 scale-95"
                enter-to-class="translate-x-0 opacity-100 scale-100"
                leave-active-class="transform transition-all duration-300 ease-in"
                leave-from-class="translate-x-0 opacity-100 scale-100"
                leave-to-class="translate-x-full opacity-0 scale-95"
            >
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    :class="[styleMap[toast.type]?.bg, styleMap[toast.type]?.border]"
                    class="pointer-events-auto relative overflow-hidden rounded-2xl border shadow-lg shadow-black/5 backdrop-blur-sm"
                >
                    <!-- Accent bar -->
                    <div
                        :class="styleMap[toast.type]?.accent"
                        class="absolute left-0 top-0 bottom-0 w-1 rounded-l-[16px]"
                    ></div>

                    <div class="flex items-start gap-3 p-4 pl-5">
                        <!-- Icon -->
                        <div class="flex-shrink-0 mt-0.5">
                            <component :is="iconMap[toast.type]" :class="styleMap[toast.type]?.icon" class="w-5 h-5" />
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <p :class="styleMap[toast.type]?.title" class="text-sm font-semibold">
                                {{ toast.title }}
                            </p>
                            <p
                                v-if="toast.message"
                                :class="styleMap[toast.type]?.message"
                                class="text-sm mt-0.5 opacity-80"
                            >
                                {{ toast.message }}
                            </p>
                        </div>

                        <!-- Close button -->
                        <button
                            @click="removeToast(toast.id)"
                            aria-label="Dismiss notification"
                            class="flex-shrink-0 p-1 rounded-lg hover:bg-black/5 transition-colors duration-200 min-w-6 min-h-6 flex items-center justify-center"
                        >
                            <X class="w-4 h-4 text-gray-400" />
                        </button>
                    </div>

                    <!-- Progress bar -->
                    <div class="h-0.5 bg-gray-100">
                        <div
                            :class="styleMap[toast.type]?.accent"
                            class="h-full rounded-full toast-progress"
                            :style="{ animationDuration: toast.duration + 'ms' }"
                        ></div>
                    </div>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
@keyframes toast-shrink {
    from {
        width: 100%;
    }
    to {
        width: 0%;
    }
}

.toast-progress {
    animation: toast-shrink linear forwards;
}
</style>
