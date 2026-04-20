<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { ArrowLeft } from "lucide-vue-next";
import MainCard from "@/components/common/MainCard.vue";

const route = useRoute();
const router = useRouter();
const goalId = route.params.id;

const goBack = () => {
  router.push({ name: "admin.performance.my-goals" });
};

onMounted(() => {
  // TODO: Fetch goal details
  console.log("Loading goal:", goalId);
});
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <button class="p-2 hover:bg-gray-100 rounded-lg" @click="goBack">
        <ArrowLeft class="w-5 h-5" />
      </button>
      <h1 class="text-3xl font-bold text-brand-dark">Goal Details</h1>
    </div>
    <MainCard>
      <p class="text-brand-light">Goal ID: {{ goalId }}</p>
      <p class="text-sm text-gray-500 mt-4">
        TODO: Implement goal detail view with progress timeline
      </p>
    </MainCard>
  </div>
</template>
