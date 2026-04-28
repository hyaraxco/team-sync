<script setup>
import { ref, computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { Send } from "lucide-vue-next";
import { usePerformanceFeedbackStore } from "@/stores/performanceFeedback";
import { useStaffMemberStore } from "@/stores/staffMember";
import { usePerformanceGoalStore } from "@/stores/performanceGoal";
import { useAuthStore } from "@/stores/auth";
import MainCard from "@/components/common/MainCard.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import Alert from "@/components/common/Alert.vue";
import { useToast } from "@/composables/useToast";

const performanceFeedbackStore = usePerformanceFeedbackStore();
const staffMemberStore = useStaffMemberStore();
const performanceGoalStore = usePerformanceGoalStore();
const authStore = useAuthStore();
const toast = useToast();

const { feedbackLoading } = storeToRefs(performanceFeedbackStore);
const { staffMembers, loading: staffLoading } = storeToRefs(staffMemberStore);
const { myGoals } = storeToRefs(performanceGoalStore);

const errorMessage = ref("");

const defaultFormData = () => ({
    staff_member_id: "",
    feedback_type: "positive",
    category: "",
    content: "",
    is_private: false,
    linked_goal_id: "",
});

const formData = ref(defaultFormData());

const isFormValid = computed(() => {
    return (
        !!formData.value.staff_member_id &&
        !!formData.value.feedback_type &&
        !!formData.value.content.trim()
    );
});

const resetForm = () => {
    formData.value = defaultFormData();
};

const submitFeedback = async () => {
    if (!isFormValid.value) {
        return;
    }

    errorMessage.value = "";

    try {
        await performanceFeedbackStore.createFeedback({
            ...formData.value,
            linked_goal_id: formData.value.linked_goal_id || null,
        });

        toast.success(
            "Feedback sent",
            "Your feedback has been submitted successfully.",
        );
        resetForm();
    } catch (error) {
        errorMessage.value =
            performanceFeedbackStore.error ||
            error?.response?.data?.message ||
            "Failed to submit feedback.";
        toast.error("Failed to send feedback", errorMessage.value);
    }
};

onMounted(async () => {
    await Promise.all([
        staffMemberStore.fetchStaffMembers(),
        performanceGoalStore.fetchMyGoals(),
    ]);
});
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-brand-dark">Give Feedback</h1>
            <p class="text-brand-light mt-1">
                Provide meaningful feedback to your team members
                <span v-if="authStore.user?.name">as {{ authStore.user.name }}</span>
            </p>
        </div>

        <Alert
            v-if="errorMessage"
            type="danger"
            title="Unable to send feedback"
            :message="errorMessage"
        />

        <MainCard>
            <div v-if="staffLoading" class="flex justify-center items-center py-12">
                <div
                    class="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-primary"
                ></div>
            </div>

            <EmptyState
                v-else-if="!staffMembers.length"
                icon="Users"
                title="No Employees Available"
                description="No staff members are available to receive feedback right now."
            />

            <form v-else class="space-y-6" @submit.prevent="submitFeedback">
                <div>
                    <label class="block text-sm font-medium text-brand-dark mb-2"
                        >Employee *</label
                    >
                    <select
                        v-model="formData.staff_member_id"
                        name="staff_member_id"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
                    >
                        <option value="">Select an employee</option>
                        <option
                            v-for="staffMember in staffMembers"
                            :key="staffMember.id"
                            :value="String(staffMember.id)"
                        >
                            {{ staffMember.full_name || staffMember.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-brand-dark mb-2"
                        >Feedback Type *</label
                    >
                    <select
                        v-model="formData.feedback_type"
                        name="feedback_type"
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
                        name="category"
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
                        name="content"
                        required
                        rows="6"
                        placeholder="Write your feedback here..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
                    ></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-brand-dark mb-2"
                        >Linked Goal (Optional)</label
                    >
                    <select
                        v-model="formData.linked_goal_id"
                        name="linked_goal_id"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-primary focus:border-transparent"
                    >
                        <option value="">Not linked to any goal</option>
                        <option
                            v-for="goal in myGoals"
                            :key="goal.id"
                            :value="String(goal.id)"
                        >
                            {{ goal.title }}
                        </option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <input
                        id="is_private"
                        v-model="formData.is_private"
                        name="is_private"
                        type="checkbox"
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
                        :disabled="!isFormValid || feedbackLoading"
                        class="flex items-center gap-2 px-6 py-3 bg-brand-primary text-white rounded-lg hover:bg-brand-primary-dark transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <Send class="w-5 h-5" />
                        {{ feedbackLoading ? "Sending..." : "Send Feedback" }}
                    </button>
                </div>
            </form>
        </MainCard>
    </div>
</template>
