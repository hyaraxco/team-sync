<script setup>
import { ref, watch, computed } from "vue";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import { useToast } from "@/composables/useToast";
import { useAuthStore } from "@/stores/auth";

const toast = useToast();

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    projectId: {
        type: [String, Number],
        required: true,
    },
});

const emit = defineEmits(["close", "created"]);

const authStore = useAuthStore();

const roleNames = computed(() => (authStore.user?.roles || []).map((role) => role.name || role));

const isPureStaff = computed(
    () => roleNames.value.includes("staff") && !roleNames.value.includes("manager") && !roleNames.value.includes("hr"),
);

const availableStatuses = computed(() => {
    if (isPureStaff.value) {
        return [{ value: "todo", label: "To Do" }];
    }
    return [
        { value: "todo", label: "To Do" },
        { value: "in_progress", label: "In Progress" },
        { value: "review", label: "Review" },
        { value: "done", label: "Done" },
    ];
});

const formData = ref({
    name: "",
    description: "",
    priority: "medium",
    status: "todo",
    due_date: "",
    project_id: props.projectId,
});

const isSubmitting = ref(false);

// Reset form when modal opens
watch(
    () => props.isOpen,
    (isOpen) => {
        if (isOpen) {
            formData.value = {
                name: "",
                description: "",
                priority: "medium",
                status: "todo",
                due_date: "",
                project_id: props.projectId,
            };
        }
    },
);

const closeModal = () => {
    emit("close");
};

const handleSubmit = async () => {
    if (!formData.value.name) {
        toast.warning("Validation Error", "Please enter task name");
        return;
    }

    isSubmitting.value = true;

    try {
        emit("created", { ...formData.value });
        closeModal();
    } catch (error) {
        toast.error("Failed to create task. Please try again.");
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <ModalWrapper :show="isOpen" title="Create New Task" maxWidth="2xl" @close="closeModal">
        <form @submit.prevent="handleSubmit" class="space-y-6 pt-2">
            <!-- Task Name -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Task Name
                    <span class="text-red-500">*</span>
                </label>
                <input
                    v-model="formData.name"
                    type="text"
                    class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:border-brand-primary focus:ring-2 focus:ring-brand-primary focus:ring-opacity-20 transition-all"
                    placeholder="Enter task name"
                    required
                />
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                <textarea
                    v-model="formData.description"
                    rows="4"
                    class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:border-brand-primary focus:ring-2 focus:ring-brand-primary focus:ring-opacity-20 transition-all resize-none"
                    placeholder="Enter task description"
                ></textarea>
            </div>

            <!-- Priority and Status -->
            <div class="grid grid-cols-2 gap-4">
                <!-- Priority -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Priority</label>
                    <select
                        v-model="formData.priority"
                        class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:border-brand-primary focus:ring-2 focus:ring-brand-primary focus:ring-opacity-20 transition-all"
                    >
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                    <select
                        v-model="formData.status"
                        class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:border-brand-primary focus:ring-2 focus:ring-brand-primary focus:ring-opacity-20 transition-all"
                    >
                        <option v-for="status in availableStatuses" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Due Date -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Due Date</label>
                <input
                    v-model="formData.due_date"
                    type="date"
                    class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:border-brand-primary focus:ring-2 focus:ring-brand-primary focus:ring-opacity-20 transition-all"
                />
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
                    :disabled="isSubmitting"
                    class="px-6 py-3 bg-brand-primary hover:bg-[#0a42b3] text-white rounded-lg transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ isSubmitting ? "Creating..." : "Create Task" }}
                </button>
            </div>
        </template>
    </ModalWrapper>
</template>
