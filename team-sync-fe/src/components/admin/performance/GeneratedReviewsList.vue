<script setup>
import { ref, computed, onMounted, Teleport } from "vue";
import { usePerformanceReviewStore } from "@/stores/performanceReview";
import { useStaffMemberStore } from "@/stores/staffMember";
import { Play, UserCheck, Edit3, X, User } from "lucide-vue-next";
import { storeToRefs } from "pinia";
import MainCard from "@/components/common/MainCard.vue";
import { useToast } from "@/composables/useToast";
import { DEFAULT_AVATAR } from "@/helpers/format";

const props = defineProps({
    cycle: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(["refresh"]);
const toast = useToast();

const reviewStore = usePerformanceReviewStore();
const staffStore = useStaffMemberStore();

const { loading } = storeToRefs(reviewStore);
const { staffMembers } = storeToRefs(staffStore);

const generating = ref(false);
const showOverrideModal = ref(false);
const selectedReview = ref(null);
const selectedReviewerId = ref("");
const assigning = ref(false);

const canGenerate = computed(() => ["draft", "active"].includes(props.cycle.status));

onMounted(async () => {
    if (!staffMembers.value?.length) {
        await staffStore.fetchStaffMembers({ per_page: 1000 }); // Load all for dropdown
    }
});

const generateReviews = async () => {
    if (
        !confirm(
            "Are you sure you want to generate reviews based on the assignment rules? This will only generate reviews for staff members who do not have one yet in this cycle.",
        )
    ) {
        return;
    }

    generating.value = true;
    try {
        const res = await reviewStore.generateReviews(props.cycle.id);
        toast.success(`Successfully generated reviews for ${res.generated_count} employees`);
        emit("refresh");
    } catch (error) {
        toast.error("Failed to generate reviews: " + (error.response?.data?.message || error.message));
    } finally {
        generating.value = false;
    }
};

const openOverrideModal = (review) => {
    selectedReview.value = review;
    selectedReviewerId.value = review.reviewer_id || "";
    showOverrideModal.value = true;
};

const closeOverrideModal = () => {
    showOverrideModal.value = false;
    selectedReview.value = null;
    selectedReviewerId.value = "";
    assigning.value = false;
};

const assignReviewer = async () => {
    if (!selectedReviewerId.value) {
        toast.warning("Please select a reviewer");
        return;
    }

    assigning.value = true;
    try {
        await reviewStore.assignReviewer(selectedReview.value.id, selectedReviewerId.value);
        toast.success("Reviewer assigned successfully");
        emit("refresh");
        closeOverrideModal();
    } catch (error) {
        toast.error("Failed to assign reviewer: " + (error.response?.data?.message || error.message));
    } finally {
        assigning.value = false;
    }
};

const getAvatarUrl = (user) => {
    return user?.avatar || DEFAULT_AVATAR;
};
</script>

<template>
    <MainCard class="mt-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-lg font-bold text-brand-dark">Generated Reviews</h3>
                <p class="text-sm text-brand-light">Manage reviews and reviewer assignments for this cycle</p>
            </div>

            <button
                v-if="canGenerate"
                @click="generateReviews"
                :disabled="generating"
                class="flex items-center gap-2 px-4 py-2 bg-brand-primary text-white rounded-lg hover:bg-brand-primary-dark transition-colors disabled:opacity-50"
            >
                <Play class="w-4 h-4" :class="{ 'animate-pulse': generating }" />
                {{ generating ? "Generating..." : "Generate Reviews" }}
            </button>
        </div>

        <!-- Empty State -->
        <div
            v-if="!props.cycle.reviews?.length"
            class="text-center py-10 border-2 border-dashed border-gray-200 rounded-xl"
        >
            <UserCheck class="w-12 h-12 text-gray-300 mx-auto mb-3" />
            <h3 class="text-lg font-medium text-brand-dark">No reviews generated yet</h3>
            <p class="text-sm text-brand-light mt-1 max-w-sm mx-auto">
                Click the "Generate Reviews" button to automatically create review records and assign reviewers based on
                the system rules.
            </p>
        </div>

        <!-- Reviews Table -->
        <div v-else class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-brand-light uppercase tracking-wider">
                            Staff Member
                        </th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-brand-light uppercase tracking-wider">
                            Assigned Reviewer
                        </th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-brand-light uppercase tracking-wider">
                            Status
                        </th>
                        <th
                            class="text-right py-3 px-4 text-xs font-semibold text-brand-light uppercase tracking-wider"
                        >
                            Action
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="review in props.cycle.reviews"
                        :key="review.id"
                        class="border-b border-gray-100 hover:bg-gray-50"
                    >
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-3">
                                <img
                                    :src="getAvatarUrl(review.staff_member?.user)"
                                    class="w-8 h-8 rounded-full bg-gray-100"
                                />
                                <div>
                                    <p class="text-sm font-medium text-brand-dark">
                                        {{ review.staff_member?.user?.name || `Staff #${review.staff_member?.id}` }}
                                    </p>
                                    <p class="text-xs text-brand-light">
                                        {{ review.staff_member?.job_information?.job_title || "No Title" }}
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <div v-if="review.reviewer" class="flex items-center gap-3">
                                <img
                                    :src="getAvatarUrl(review.reviewer?.user)"
                                    class="w-8 h-8 rounded-full bg-gray-100"
                                />
                                <div>
                                    <p class="text-sm font-medium text-brand-dark">
                                        {{ review.reviewer?.user?.name || `Staff #${review.reviewer?.id}` }}
                                    </p>
                                    <p class="text-xs text-brand-light">
                                        <!-- Badging rule here P4-13 -->
                                        <span
                                            class="inline-block px-2 py-0.5 mt-0.5 rounded text-[10px] bg-blue-100 text-blue-700 border border-blue-200"
                                        >
                                            {{ review.reviewer?.user?.roles?.[0]?.name || "Unknown Role" }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div v-else class="flex items-center gap-2 text-amber-600">
                                <User class="w-4 h-4" />
                                <span class="text-sm italic">Unassigned</span>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700">
                                {{ review.status.replace("_", " ") }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <button
                                @click="openOverrideModal(review)"
                                class="inline-flex items-center gap-1.5 text-sm text-brand-primary hover:text-brand-primary-dark font-medium"
                                :disabled="['completed', 'pending_calibration'].includes(review.status)"
                                :class="{
                                    'opacity-50 cursor-not-allowed': ['completed', 'pending_calibration'].includes(
                                        review.status,
                                    ),
                                }"
                                title="Override Reviewer"
                            >
                                <Edit3 class="w-4 h-4" />
                                Assign
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </MainCard>

    <Teleport to="body">
        <div
            v-if="showOverrideModal"
            class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm"
        >
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
                <div class="flex items-center justify-between p-4 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-brand-dark">Assign Reviewer</h3>
                    <button @click="closeOverrideModal" class="p-1 hover:bg-gray-100 rounded-lg text-gray-500">
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <div class="p-4 space-y-4">
                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                        <p class="text-xs text-brand-light mb-1">Reviewee:</p>
                        <p class="text-sm font-medium text-brand-dark">
                            {{
                                selectedReview?.staff_member?.user?.name || `Staff #${selectedReview?.staff_member?.id}`
                            }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-brand-dark mb-1">Select Reviewer</label>
                        <select
                            v-model="selectedReviewerId"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-brand-primary focus:border-brand-primary"
                        >
                            <option value="" disabled>-- Select a staff member --</option>
                            <option
                                v-for="staff in staffMembers.filter((s) => s.id !== selectedReview?.staff_member_id)"
                                :key="staff.id"
                                :value="staff.id"
                            >
                                {{ staff.user?.name || `Staff #${staff.id}` }} ({{
                                    staff.job_information?.job_title || "No Title"
                                }})
                            </option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 p-4 border-t border-gray-100 bg-gray-50">
                    <button
                        @click="closeOverrideModal"
                        class="px-4 py-2 text-sm font-medium text-brand-dark hover:bg-gray-200 rounded-lg transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        @click="assignReviewer"
                        :disabled="assigning || !selectedReviewerId"
                        class="px-4 py-2 text-sm font-medium text-white bg-brand-primary hover:bg-brand-primary-dark rounded-lg transition-colors disabled:opacity-50"
                    >
                        {{ assigning ? "Saving..." : "Save Assignment" }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
