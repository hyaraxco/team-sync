<script setup>
import { formatToClientTimezone, DEFAULT_AVATAR } from "@/helpers/format";
import { can } from "@/helpers/permissionHelper";
import _ from "lodash";
import { Briefcase, Calendar, Crown, Edit, FileText, Trash2 } from "lucide-vue-next";
import StatusBadge from "@/components/common/StatusBadge.vue";
import AnimatedValue from "@/components/common/AnimatedValue.vue";
import { useRouter } from "vue-router";
import { useToast } from "@/composables/useToast";
import ConfirmationModal from "@/components/common/ConfirmationModal.vue";
import { ref } from "vue";
import { useProjectStore } from "@/stores/project";

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const router = useRouter();
const toast = useToast();
const showDeleteModal = ref(false);

const projectStore = useProjectStore();
const { deleteProject } = projectStore;

const navigateToDetail = () => {
    router.push({ name: "admin.projects.detail", params: { id: props.data.id } });
};

const handleDeleteProject = async () => {
    await deleteProject(props.data.id);

    if (projectStore.success) {
        showDeleteModal.value = false;
        toast.success("Project deleted", projectStore.success);
    } else if (projectStore.error) {
        toast.error("Delete failed", projectStore.error);
    }
};
</script>

<template>
    <div
        @click="navigateToDetail"
        class="border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 hover:shadow-lg transition-all duration-300 p-4 cursor-pointer"
    >
        <!-- Project Image -->
        <div
            class="w-full h-32 bg-gradient-to-br from-primary-50 to-primary-100 relative overflow-hidden rounded-xl mb-4"
        >
            <img
                v-if="data.photo"
                loading="lazy"
                :src="data.photo"
                :alt="data.name ? `${data.name} cover` : 'Project cover'"
                class="w-full h-full object-cover rounded-xl"
            />
            <div v-else class="w-full h-full flex items-center justify-center" aria-hidden="true">
                <Briefcase class="w-10 h-10 text-primary-400" />
            </div>
            <!-- Priority Badge Overlay -->
            <StatusBadge
                v-if="data.priority"
                type="priority"
                :value="data.priority"
                class="absolute bottom-2 left-2 shadow-sm"
            />
            <!-- Status Badge Overlay -->
            <StatusBadge
                v-if="data.status"
                type="project"
                :value="data.status"
                class="absolute bottom-2 right-2 shadow-sm"
            />
        </div>
        <div class="flex items-start justify-between mb-4">
            <div class="flex-1">
                <h4 class="text-brand-dark text-lg font-bold mb-2">
                    {{ data.name }}
                </h4>
                <p class="text-brand-light text-sm line-clamp-2 mb-1">
                    {{ data.description }}
                </p>
            </div>
        </div>

        <div class="border-t border-brand-border pt-4 mb-4" v-if="data.leader">
            <div class="flex items-center gap-3">
                <img loading="lazy"
                    :src="data.leader?.user?.profile_photo || DEFAULT_AVATAR"
                    class="w-10 h-10 rounded-full object-cover"
                />

                <div class="flex-1">
                    <h5 class="text-brand-dark text-sm font-semibold">
                        {{ data.leader?.user?.name }}
                    </h5>
                    <p class="text-brand-light text-xs">
                        {{ data.leader?.job_information?.job_title }}
                    </p>
                </div>
                <div class="px-2 py-1 bg-green-50 border border-green-200 rounded-md flex items-center gap-1">
                    <Crown class="w-3 h-3 text-green-600" />
                    <span class="text-green-700 text-xs font-medium">Leader</span>
                </div>
            </div>
            <div class="border-b border-brand-border pb-4"></div>
        </div>

        <div class="mb-4">
            <div class="flex items-center justify-between text-sm mb-2">
                <span class="text-brand-light">Progress</span>
                <span class="text-brand-dark font-semibold"><AnimatedValue :value="data.progress" suffix="%" /></span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="h-2 rounded-full ${getProgressColor(data.progress)}"></div>
            </div>
        </div>

        <div class="space-y-2 mb-4">
            <div class="flex items-center gap-2 text-sm text-gray-600">
                <FileText class="w-4 h-4" />
                <span v-if="data.teams.length > 0">{{ data.teams.map((team) => team.name).join(", ") }}</span>
                <span v-else>Belum ada tim</span>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-600">
                <Calendar class="w-4 h-4" />
                <span>
                    {{ formatToClientTimezone(data.start_date) }} -
                    {{ data.end_date ? formatToClientTimezone(data.end_date) : "N/A" }}
                </span>
            </div>
        </div>

        <div class="flex gap-2" v-if="can('project-edit')">
            <RouterLink
                :to="{ name: 'admin.projects.edit', params: { id: data.id } }"
                class="flex-1 border border-brand-border rounded-lg hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 px-3 py-2 flex items-center justify-center gap-2"
                @click.stop
            >
                <Edit class="w-4 h-4 text-gray-600" />
                <span class="text-brand-dark text-sm font-semibold">Edit</span>
            </RouterLink>
            <button
                v-if="can('project-delete')"
                @click.stop="showDeleteModal = true"
                class="flex-1 border border-brand-border rounded-lg hover:ring-2 hover:ring-red-500/20 hover:bg-red-50 transition-all duration-300 px-3 py-2 flex items-center justify-center gap-2"
            >
                <Trash2 class="w-4 h-4 text-gray-600 hover:text-red-600" />
                <span class="text-brand-dark text-sm font-semibold">Delete</span>
            </button>
        </div>
    </div>

    <ConfirmationModal
        :show="showDeleteModal"
        title="Delete Project"
        :message="`Are you sure you want to delete '${data.name}'? This will permanently remove the project and all associated data. This action cannot be undone.`"
        confirmText="Delete Project"
        cancelText="Cancel"
        type="danger"
        :loading="projectStore.loading"
        @confirm="handleDeleteProject"
        @cancel="showDeleteModal = false"
    />
</template>
