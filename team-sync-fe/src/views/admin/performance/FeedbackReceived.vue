<script setup>
import { ref, onMounted, computed } from "vue";
import { storeToRefs } from "pinia";
import { usePerformanceFeedbackStore } from "@/stores/performanceFeedback";
import { useToast } from "@/composables/useToast";
import { ThumbsUp, AlertCircle, MessageCircle, Check, EyeOff } from "lucide-vue-next";
import MainCard from "@/components/common/MainCard.vue";
import EmptyState from "@/components/common/EmptyState.vue";

const feedbackStore = usePerformanceFeedbackStore();
const toast = useToast();
const { receivedFeedback, feedbackLoading } = storeToRefs(feedbackStore);

const selectedType = ref("all");
const selectedCategory = ref("all");

const filteredFeedback = computed(() => {
    let feedback = receivedFeedback.value;
    if (selectedType.value !== "all") {
        feedback = feedback.filter((f) => f.feedback_type === selectedType.value);
    }
    if (selectedCategory.value !== "all") {
        feedback = feedback.filter((f) => f.category === selectedCategory.value);
    }
    return feedback;
});

const categories = computed(() => {
    const uniqueCategories = [...new Set(receivedFeedback.value.map((f) => f.category).filter(Boolean))];
    return uniqueCategories;
});

const feedbackTypeConfig = {
    positive: {
        label: "Positive",
        icon: ThumbsUp,
        color: "bg-green-100 text-green-700 border-green-200",
        iconColor: "text-green-600",
    },
    constructive: {
        label: "Constructive",
        icon: AlertCircle,
        color: "bg-yellow-100 text-yellow-700 border-yellow-200",
        iconColor: "text-yellow-600",
    },
    general: {
        label: "General",
        icon: MessageCircle,
        color: "bg-blue-100 text-blue-700 border-blue-200",
        iconColor: "text-blue-600",
    },
};

const acknowledgeFeedback = async (feedbackId) => {
    try {
        await feedbackStore.acknowledgeFeedback(feedbackId);
    } catch (error) {
        toast.error(
            "Failed to acknowledge feedback",
            feedbackStore.error || error?.response?.data?.message || "Please try again.",
        );
    }
};

onMounted(async () => {
    await feedbackStore.fetchReceivedFeedback();
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-brand-dark">Feedback Received</h1>
                <p class="text-brand-light mt-1">View and acknowledge feedback from your colleagues</p>
            </div>
        </div>

        <div class="bg-[var(--color-surface)] border border-brand-border rounded-2xl p-4 sm:p-6 space-y-6">
            <div class="flex flex-wrap items-center gap-4 pb-4 border-b border-brand-border">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-semibold text-brand-light uppercase tracking-wide mb-1.5">Feedback Type</label>
                    <select
                        v-model="selectedType"
                        aria-label="Filter by feedback type"
                        class="w-full px-4 py-2.5 border border-brand-border bg-[var(--color-surface)] text-brand-dark rounded-xl text-sm focus:ring-2 focus:ring-brand-primary"
                    >
                        <option value="all">All Types</option>
                        <option value="positive">Positive</option>
                        <option value="constructive">Constructive</option>
                        <option value="general">General</option>
                    </select>
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-semibold text-brand-light uppercase tracking-wide mb-1.5">Category</label>
                    <select
                        v-model="selectedCategory"
                        aria-label="Filter by category"
                        class="w-full px-4 py-2.5 border border-brand-border bg-[var(--color-surface)] text-brand-dark rounded-xl text-sm focus:ring-2 focus:ring-brand-primary"
                    >
                        <option value="all">All Categories</option>
                        <option v-for="category in categories" :key="category" :value="category">
                            {{ category }}
                        </option>
                    </select>
                </div>
            </div>

            <div v-if="feedbackLoading" class="flex justify-center items-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-primary"></div>
            </div>

        <div v-else-if="filteredFeedback.length > 0" class="space-y-4">
            <div v-for="(feedback, index) in filteredFeedback" :key="feedback.id" class="relative">
                <div
                    v-if="index < filteredFeedback.length - 1"
                    class="absolute left-6 top-16 bottom-0 w-0.5 bg-gray-200"
                ></div>

                <MainCard class="relative hover:ring-2 hover:ring-brand-primary/20 transition-shadow duration-200">
                    <div
                        class="absolute left-0 top-6 w-12 h-12 rounded-full flex items-center justify-center border-4 border-white"
                        :class="feedbackTypeConfig[feedback.feedback_type]?.color"
                    >
                        <component
                            :is="feedbackTypeConfig[feedback.feedback_type]?.icon"
                            class="w-6 h-6"
                            :class="feedbackTypeConfig[feedback.feedback_type]?.iconColor"
                        />
                    </div>

                    <div class="ml-16">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-1">
                                    <h3 class="text-lg font-semibold text-brand-dark">
                                        {{ feedback.giver?.full_name || "Anonymous" }}
                                    </h3>
                                    <span
                                        class="px-3 py-1 rounded-full text-xs font-semibold border"
                                        :class="feedbackTypeConfig[feedback.feedback_type]?.color"
                                    >
                                        {{ feedbackTypeConfig[feedback.feedback_type]?.label }}
                                    </span>
                                    <span
                                        v-if="feedback.is_private"
                                        class="flex items-center gap-1 px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs"
                                    >
                                        <EyeOff class="w-3 h-3" />
                                        Private
                                    </span>
                                </div>
                                <div class="flex items-center gap-4 text-sm text-brand-light">
                                    <span>{{ new Date(feedback.created_at).toLocaleDateString() }}</span>
                                    <span v-if="feedback.category" class="flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-brand-light"></span>
                                        {{ feedback.category }}
                                    </span>
                                    <span v-if="feedback.linked_goal_id" class="flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-brand-light"></span>
                                        Linked to Goal
                                    </span>
                                </div>
                            </div>

                            <button
                                v-if="!feedback.acknowledged_at"
                                class="flex items-center gap-2 px-4 py-2 bg-primary-700 text-white rounded-lg hover:bg-primary-800 transition-colors font-semibold text-sm"
                                @click="acknowledgeFeedback(feedback.id)"
                            >
                                <Check class="w-4 h-4" />
                                Acknowledge
                            </button>
                            <div v-else class="flex items-center gap-2 px-4 py-2 bg-green-50 text-green-700 rounded-lg">
                                <Check class="w-4 h-4" />
                                Acknowledged
                            </div>
                        </div>

                        <div class="bg-[var(--color-surface-raised)] rounded-lg p-4 border border-gray-100">
                            <p class="text-brand-dark leading-relaxed whitespace-pre-wrap">
                                {{ feedback.content }}
                            </p>
                        </div>

                        <div v-if="feedback.acknowledged_at" class="mt-3 text-xs text-brand-light">
                            Acknowledged on
                            {{ new Date(feedback.acknowledged_at).toLocaleDateString() }}
                        </div>
                    </div>
                </MainCard>
            </div>
        </div>

            <div v-else class="py-12">
                <EmptyState
                    icon="MessageSquare"
                    title="Belum ada feedback"
                    subtitle="You haven't received any feedback yet. Feedback from your colleagues will appear here."
                    size="lg"
                />
            </div>
        </div>
    </div>
</template>
