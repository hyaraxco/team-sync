<script setup>
import { ref, watch } from "vue";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import { useToast } from "@/composables/useToast";
import { useProjectStore } from "@/stores/project";

const toast = useToast();
const projectStore = useProjectStore();

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

const projectMembers = ref([]);
const loadingMembers = ref(false);

const formData = ref({
    name: "",
    description: "",
    priority: "medium",
    status: "todo",
    assignee_id: null,
    due_date: "",
    project_id: props.projectId,
});

const isSubmitting = ref(false);

const loadMembers = async () => {
    if (!props.projectId) return;
    loadingMembers.value = true;
    try {
        const members = await projectStore.fetchProjectMembers(props.projectId);
        projectMembers.value = Array.isArray(members) ? members : [];
    } finally {
        loadingMembers.value = false;
    }
};

// Fetch members when modal opens
watch(
    () => props.isOpen,
    (isOpen) => {
        if (isOpen) {
            loadMembers();
        }
    },
    { immediate: true },
);

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
                assignee_id: null,
                due_date: "",
                project_id: props.projectId,
            };
        }
    },
);

const memberDisplayName = (member) => {
    return member?.user?.name || member?.code || `Member #${member?.id}`;
};

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
        const payload = { ...formData.value };
        if (!payload.assignee_id) {
            payload.assignee_id = null;
        }
        emit("created", payload);
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
                    <label for="task-priority" class="block text-sm font-semibold text-gray-700 mb-2">
                        Priority
                    </label>
                    <select
                        id="task-priority"
                        v-model="formData.priority"
                        class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:border-brand-primary focus:ring-2 focus:ring-brand-primary focus:ring-opacity-20 transition-all"
                    >
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>

                <!-- Status (read-only) -->
                <div>
                    <span class="block text-sm font-semibold text-gray-700 mb-2">Status</span>
                    <div class="flex items-center h-[50px] px-4 py-3 border border-gray-200 rounded-lg bg-gray-50">
                        <span class="px-2 py-1 bg-gray-100 rounded text-sm text-gray-700 font-medium">To Do</span>
                        <span class="ml-2 text-xs text-gray-500">(set automatically)</span>
                    </div>
                </div>
            </div>

            <!-- Assignee (optional) -->
            <div>
                <label for="task-assignee" class="block text-sm font-semibold text-gray-700 mb-2">
                    Assign To
                    <span class="text-xs text-gray-500 font-normal">(optional)</span>
                </label>
                <select
                    id="task-assignee"
                    v-model="formData.assignee_id"
                    :disabled="loadingMembers"
                    class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:border-brand-primary focus:ring-2 focus:ring-brand-primary focus:ring-opacity-20 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <option :value="null">Unassigned</option>
                    <option v-if="loadingMembers" disabled value="">Loading members...</option>
                    <option
                        v-for="member in projectMembers"
                        :key="member.id"
                        :value="member.id"
                    >
                        {{ memberDisplayName(member) }}
                    </option>
                </select>
                <p
                    v-if="!loadingMembers && projectMembers.length === 0"
                    class="text-sm text-gray-500 mt-2"
                >
                    No team members available. Add teams to the project first.
                </p>
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
                    class="px-6 py-3 bg-brand-primary hover:bg-primary-800 text-white rounded-lg transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ isSubmitting ? "Creating..." : "Create Task" }}
                </button>
            </div>
        </template>
    </ModalWrapper>
</template>
