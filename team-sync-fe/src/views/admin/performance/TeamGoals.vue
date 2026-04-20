<script setup>
import { ref, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { usePerformanceGoalStore } from "@/stores/performanceGoal";
import { useRouter } from "vue-router";
import { Target } from "lucide-vue-next";
import MainCard from "@/components/common/MainCard.vue";
import EmptyState from "@/components/common/EmptyState.vue";

const router = useRouter();
const goalStore = usePerformanceGoalStore();
const { teamGoals, goalsLoading } = storeToRefs(goalStore);

onMounted(async () => {
  await goalStore.fetchTeamGoals();
});
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-3xl font-bold text-brand-dark">Team Goals</h1>
      <p class="text-brand-light mt-1">Monitor and manage your team's goals</p>
    </div>

    <div v-if="goalsLoading" class="flex justify-center items-center py-12">
      <div
        class="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-primary"
      ></div>
    </div>

    <div v-else-if="teamGoals.length > 0">
      <MainCard>
        <p class="text-brand-light">{{ teamGoals.length }} team goals</p>
        <p class="text-sm text-gray-500 mt-4">
          TODO: Implement team goals view
        </p>
      </MainCard>
    </div>

    <EmptyState
      v-else
      icon="Target"
      title="No Team Goals"
      description="Your team members haven't created any goals yet."
    />
  </div>
</template>
