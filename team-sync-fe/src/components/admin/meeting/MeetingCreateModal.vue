<script setup>
import { ref, watch, computed } from "vue";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import { useMeetingStore } from "@/stores/meeting";
import { useTeamStore } from "@/stores/team";
import { useToast } from "@/composables/useToast";

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["close", "created"]);

const meetingStore = useMeetingStore();
const teamStore = useTeamStore();
const toast = useToast();

const isSubmitting = ref(false);

const formData = ref({
    title: "",
    description: "",
    scheduled_at: "",
    duration_minutes: 60,
    location: "",
    departments: [],
    team_ids: [],
});

const durationOptions = [
    { value: 15, label: "15 min" },
    { value: 30, label: "30 min" },
    { value: 45, label: "45 min" },
    { value: 60, label: "1 hour" },
    { value: 90, label: "1.5 hours" },
    { value: 120, label: "2 hours" },
];

const departmentOptions = [
    { value: "development", label: "Development" },
    { value: "design", label: "Design" },
    { value: "marketing", label: "Marketing" },
    { value: "sales", label: "Sales" },
    { value: "support", label: "Support" },
    { value: "management", label: "Management" },
];

const now = new Date();
const minDateTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);

watch(
    () => props.show,
    async (show) => {
        if (show) {
            formData.value = {
                title: "",
                description: "",
                scheduled_at: "",
                duration_minutes: 60,
                location: "",
                departments: [],
                team_ids: [],
            };
            await teamStore.fetchTeams();
        }
    },
    { immediate: true },
);

const closeModal = () => {
    emit("close");
};

const isValid = computed(() => {
    return formData.value.title && formData.value.scheduled_at;
});

const handleSubmit = async () => {
    if (!isValid.value) return;

    isSubmitting.value = true;

    try {
        await meetingStore.createMeeting({
            ...formData.value,
            scheduled_at: formData.value.scheduled_at.replace("T", " ") + ":00", // Format for Laravel
        });

        if (meetingStore.error) {
            throw new Error(meetingStore.error);
        }

        toast.success("Success", "Meeting scheduled successfully.");
        emit("created");
    } catch (error) {
        toast.error("Error", "Failed to schedule meeting.");
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <ModalWrapper :show="show" title="Schedule Meeting" maxWidth="2xl" @close="closeModal">
        <form @submit.prevent="handleSubmit" class="space-y-6 pt-2">
            <!-- Title -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Title
                    <span class="text-red-500">*</span>
                </label>
                <input
                    v-model="formData.title"
                    type="text"
                    class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:border-brand-primary focus:ring-2 focus:ring-brand-primary focus:ring-opacity-20 transition-all"
                    placeholder="Meeting title"
                    required
                />
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                <textarea
                    v-model="formData.description"
                    rows="3"
                    class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:border-brand-primary focus:ring-2 focus:ring-brand-primary focus:ring-opacity-20 transition-all resize-none"
                    placeholder="Agenda or notes"
                ></textarea>
            </div>

            <!-- Date/Time and Duration -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Date & Time
                        <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="formData.scheduled_at"
                        type="datetime-local"
                        :min="minDateTime"
                        class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:border-brand-primary focus:ring-2 focus:ring-brand-primary focus:ring-opacity-20 transition-all"
                        required
                    />
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Duration</label>
                    <select
                        v-model="formData.duration_minutes"
                        class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:border-brand-primary focus:ring-2 focus:ring-brand-primary focus:ring-opacity-20 transition-all"
                    >
                        <option v-for="option in durationOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Location/Link -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Location or Link</label>
                <input
                    v-model="formData.location"
                    type="text"
                    class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:border-brand-primary focus:ring-2 focus:ring-brand-primary focus:ring-opacity-20 transition-all"
                    placeholder="Paste GMeet/Zoom link or enter location"
                />
            </div>

            <!-- Departments and Teams Checkboxes -->
            <div class="grid grid-cols-2 gap-6 pt-2">
                <!-- Departments -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Departments</label>
                    <div class="space-y-2">
                        <label
                            v-for="dept in departmentOptions"
                            :key="dept.value"
                            class="flex items-center gap-2 cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                :value="dept.value"
                                v-model="formData.departments"
                                class="w-4 h-4 text-brand-primary border-gray-300 rounded focus:ring-brand-primary"
                            />
                            <span class="text-sm text-gray-600">{{ dept.label }}</span>
                        </label>
                    </div>
                </div>

                <!-- Teams -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Teams</label>
                    <div class="space-y-2 max-h-48 overflow-y-auto pr-2 custom-scrollbar">
                        <label
                            v-for="team in teamStore.teams"
                            :key="team.id"
                            class="flex items-center gap-2 cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                :value="team.id"
                                v-model="formData.team_ids"
                                class="w-4 h-4 text-brand-primary border-gray-300 rounded focus:ring-brand-primary"
                            />
                            <span class="text-sm text-gray-600">{{ team.name }}</span>
                        </label>
                        <div
                            v-if="teamStore.teams.length === 0 && !teamStore.loading"
                            class="text-sm text-gray-500 italic"
                        >
                            No teams available
                        </div>
                        <div v-if="teamStore.loading" class="text-sm text-gray-500">Loading teams...</div>
                    </div>
                </div>
            </div>
        </form>

        <template #footer>
            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                <button
                    type="button"
                    @click="closeModal"
                    class="px-6 py-3 border border-gray-200 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    @click="handleSubmit"
                    :disabled="isSubmitting || !isValid"
                    class="px-6 py-3 bg-brand-primary hover:bg-[#0a42b3] text-white rounded-lg transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    :class="{ 'blue-gradient': isValid && !isSubmitting }"
                >
                    {{ isSubmitting ? "Scheduling..." : "Schedule Meeting" }}
                </button>
            </div>
        </template>
    </ModalWrapper>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #d1d5db;
    border-radius: 10px;
}
.blue-gradient {
    background: linear-gradient(135deg, #0c51d9 0%, #2151a0 100%);
}
</style>
