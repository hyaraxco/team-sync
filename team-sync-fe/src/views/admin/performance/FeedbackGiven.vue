<script setup>
import { ref, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { usePerformanceFeedbackStore } from "@/stores/performanceFeedback";
import { MessageSquare } from "lucide-vue-next";
import MainCard from "@/components/common/MainCard.vue";
import EmptyState from "@/components/common/EmptyState.vue";

const feedbackStore = usePerformanceFeedbackStore();
const { givenFeedback, feedbackLoading } = storeToRefs(feedbackStore);

onMounted(async () => {
  await feedbackStore.fetchGivenFeedback();
});
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-3xl font-bold text-brand-dark">Feedback Given</h1>
      <p class="text-brand-light mt-1">
        View feedback you've provided to colleagues
      </p>
    </div>

    <div v-if="feedbackLoading" class="flex justify-center items-center py-12">
      <div
        class="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-primary"
      ></div>
    </div>

    <div v-else-if="givenFeedback.length > 0">
      <MainCard>
        <p class="text-brand-light">
          {{ givenFeedback.length }} feedback items
        </p>
        <p class="text-sm text-gray-500 mt-4">
          TODO: Implement given feedback view
        </p>
      </MainCard>
    </div>

    <EmptyState
      v-else
      icon="MessageSquare"
      title="No Feedback Given"
      description="You haven't given any feedback yet."
    />
  </div>
</template>
