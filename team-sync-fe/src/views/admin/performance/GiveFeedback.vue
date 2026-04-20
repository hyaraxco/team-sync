<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import { ArrowLeft, Send } from "lucide-vue-next";
import MainCard from "@/components/common/MainCard.vue";

const router = useRouter();

const formData = ref({
  employee_id: "",
  feedback_type: "positive",
  category: "",
  content: "",
  is_private: false,
  linked_goal_id: null,
});

const goBack = () => {
  router.push({ name: "admin.performance.feedback.received" });
};

const submitFeedback = async () => {
  // TODO: Implement feedback submission
  console.log("Submitting feedback:", formData.value);
};
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <button class="p-2 hover:bg-gray-100 rounded-lg" @click="goBack">
        <ArrowLeft class="w-5 h-5" />
      </button>
      <div>
        <h1 class="text-3xl font-bold text-brand-dark">Give Feedback</h1>
        <p class="text-brand-light mt-1">Provide feedback to a colleague</p>
      </div>
    </div>

    <MainCard>
      <form @submit.prevent="submitFeedback" class="space-y-6">
        <div>
          <label class="block text-sm font-medium text-brand-dark mb-2"
            >Employee *</label
          >
          <select
            v-model="formData.employee_id"
            required
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
          >
            <option value="">Select an employee</option>
            <!-- TODO: Load employees from store -->
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-brand-dark mb-2"
            >Feedback Type *</label
          >
          <select
            v-model="formData.feedback_type"
            required
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
          >
            <option value="positive">Positive</option>
            <option value="constructive">Constructive</option>
            <option value="general">General</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-brand-dark mb-2"
            >Category</label
          >
          <input
            v-model="formData.category"
            type="text"
            placeholder="e.g., Communication, Technical Skills"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-brand-dark mb-2"
            >Feedback *</label
          >
          <textarea
            v-model="formData.content"
            required
            rows="6"
            placeholder="Write your feedback here..."
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
          ></textarea>
        </div>

        <div class="flex items-center gap-2">
          <input
            v-model="formData.is_private"
            type="checkbox"
            id="is_private"
            class="w-4 h-4 text-brand-primary border-gray-300 rounded focus:ring-brand-primary"
          />
          <label for="is_private" class="text-sm text-brand-dark">
            Make this feedback private (visible only to employee and their
            manager)
          </label>
        </div>

        <div class="flex items-center gap-4 pt-6 border-t border-gray-200">
          <button
            type="submit"
            class="flex items-center gap-2 px-6 py-3 bg-brand-primary text-white rounded-lg hover:bg-brand-primary-dark transition-colors"
          >
            <Send class="w-5 h-5" />
            Send Feedback
          </button>
          <button
            type="button"
            class="px-6 py-3 bg-gray-100 text-brand-dark rounded-lg hover:bg-gray-200 transition-colors"
            @click="goBack"
          >
            Cancel
          </button>
        </div>
      </form>
    </MainCard>
  </div>
</template>
