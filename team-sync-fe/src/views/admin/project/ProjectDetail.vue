<script setup>
import { useProjectStore } from "@/stores/project";
import {
    Briefcase,
    Crown,
    Target,
    DollarSign,
    CalendarCheck,
    Clock,
    Calendar,
    User,
    Users,
    ListChecks,
    Trash2,
    AlertTriangle,
    UserCheck,
    Search,
    SearchX,
} from "lucide-vue-next";
import { useRoute } from "vue-router";
import router from "@/router";
import { onMounted, ref, computed } from "vue";
import { formatDate, calculateDuration } from "@/utils/dateUtils";
import { DEFAULT_AVATAR } from "@/helpers/format";
import { formatRupiah } from "@/utils/formatUtils";
import {
    getPriorityColor,
    getProjectStatusColor,
    getProgressColor,
    TASK_STATUS_ORDER,
    TASK_STATUS_LABELS,
} from "@/utils/badgeUtils";
import _ from "lodash";
import TaskBoard from "@/components/admin/project/detail/TaskBoard.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import AnimatedValue from "@/components/common/AnimatedValue.vue";
import { useToast } from "@/composables/useToast";
import ConfirmationModal from "@/components/common/ConfirmationModal.vue";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import { can } from "@/helpers/permissionHelper";

const route = useRoute();
const id = route.params.id;

const projectStore = useProjectStore();
const { fetchProject, fetchProjectSquadSummary, deleteProject, fetchEligibleLeaders, updateProjectLeader } =
    projectStore;
const toast = useToast();

const showDeleteModal = ref(false);
const showLeaderModal = ref(false);
const eligibleLeaders = ref([]);
const eligibleLeadersLoading = ref(false);
const leaderSearch = ref("");
const leaderSubmitting = ref(false);

const project = ref({});
const squadSummary = ref(null);
const squadSummaryLoading = ref(false);

const canViewProjectStats = computed(() => can("project-statistic") || !!project.value?.is_project_leader);
const canDeleteProject = computed(() => can("project-delete"));
const canEditProject = computed(() => can("project-edit"));
const canViewStaffMemberDetail = computed(() => can("staff-member-list"));

const streamOrder = ["frontend", "backend", "uiux", "qa", "pm", "other"];
const streamLabelMap = {
    frontend: "Frontend",
    backend: "Backend",
    uiux: "UI/UX",
    qa: "QA",
    pm: "PM",
    other: "Other",
};
const statusOrder = TASK_STATUS_ORDER;
const statusLabelMap = TASK_STATUS_LABELS;

const summaryByStream = computed(() => {
    const byStream = squadSummary.value?.headcount?.by_stream ?? {};

    return streamOrder
        .map((key) => ({
            key,
            label: streamLabelMap[key] ?? key,
            value: Number(byStream[key] ?? 0),
        }))
        .filter((item) => item.value > 0);
});

const summaryTaskByStatus = computed(() => {
    const byStatus = squadSummary.value?.tasks?.by_status ?? {};

    return statusOrder
        .map((key) => ({
            key,
            label: statusLabelMap[key] ?? key,
            value: Number(byStatus[key] ?? 0),
        }))
        .filter((item) => item.value > 0);
});

const totalHeadcount = computed(() => Number(squadSummary.value?.headcount?.total ?? 0));
const totalTaskCount = computed(() => Number(squadSummary.value?.tasks?.total ?? 0));

const handleFetchProject = async () => {
    try {
        const response = await fetchProject(id);

        if (response) {
            project.value = response;
        } else {
            router.push({ name: "admin.projects" });
        }
    } catch (error) {
        toast.error(
            "Failed to load project",
            projectStore.error || error?.response?.data?.message || "Failed to load project.",
        );
        project.value = {};
    }
};

const handleFetchSquadSummary = async () => {
    if (!canViewProjectStats.value) {
        return;
    }

    squadSummaryLoading.value = true;

    try {
        squadSummary.value = await fetchProjectSquadSummary(id);
    } catch {
        squadSummary.value = null;
    } finally {
        squadSummaryLoading.value = false;
    }
};

const handleDeleteProject = async () => {
    await deleteProject(id);

    if (projectStore.success) {
        showDeleteModal.value = false;
        router.push({ name: "admin.projects" });
    } else if (projectStore.error) {
        toast.error("Delete failed", projectStore.error);
    }
};

const openLeaderEditModal = async () => {
    showLeaderModal.value = true;
    leaderSearch.value = "";
    eligibleLeadersLoading.value = true;

    try {
        eligibleLeaders.value = await fetchEligibleLeaders(id);
    } catch {
        eligibleLeaders.value = [];
        toast.error("Failed to load leaders", projectStore.error || "Please try again.");
    } finally {
        eligibleLeadersLoading.value = false;
    }
};

const closeLeaderModal = () => {
    showLeaderModal.value = false;
    eligibleLeaders.value = [];
    leaderSearch.value = "";
};

const filteredEligibleLeaders = computed(() => {
    const query = leaderSearch.value.trim().toLowerCase();
    if (!query) return eligibleLeaders.value;

    return eligibleLeaders.value.filter((leader) => {
        const name = leader?.user?.name?.toLowerCase() || "";
        const title = leader?.job_information?.job_title?.toLowerCase() || "";

        return name.includes(query) || title.includes(query);
    });
});

const handleSelectLeader = async (leader) => {
    if (leaderSubmitting.value) return;

    leaderSubmitting.value = true;

    try {
        await updateProjectLeader(id, leader.id);

        toast.success("Project leader updated");
        closeLeaderModal();
        await handleFetchProject();
    } catch (error) {
        toast.error(
            "Failed to update project leader",
            projectStore.error || error?.response?.data?.message || "Failed to update project leader.",
        );
    } finally {
        leaderSubmitting.value = false;
    }
};

const aboutParagraphs = computed(() => {
    if (!project.value.description) return [];
    return project.value.description.split("\n\n").map((p) => p.trim());
});

const projectProgress = computed(() => {
    if (!project.value.tasks || project.value.tasks.length === 0) {
        return 0;
    }
    const totalTasks = project.value.tasks.length;
    const completedTasks = project.value.tasks.filter((task) => task.status === "done").length;

    const progress = Math.round((completedTasks / totalTasks) * 100);

    return progress;
});

onMounted(async () => {
    await handleFetchProject();
    await handleFetchSquadSummary();
});
</script>

<template>
    <h1 class="text-2xl font-semibold text-brand-dark mb-6">{{ project.name || 'Project Detail' }}</h1>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2 bg-white border border-brand-border rounded-2xl p-6">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                        <Briefcase class="w-6 h-6 text-blue-600" />
                    </div>
                    <h2 class="text-lg font-semibold text-brand-dark">Project Information</h2>
                </div>
                <div class="flex items-center gap-2">
                    <div
                        class="px-3 py-1 rounded-full text-base font-semibold"
                        :class="getProjectStatusColor(project.status)"
                    >
                        {{ _.capitalize(project.status) }}
                    </div>
                    <div
                        class="px-3 py-1 rounded-full text-base font-semibold"
                        :class="getPriorityColor(project.priority)"
                    >
                        {{ _.capitalize(project.priority) }}
                    </div>
                </div>
            </div>

            <div
                v-if="project.photo"
                class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 p-5"
            >
                <img
                    loading="lazy"
                    :src="project.photo"
                    :alt="project.name ? `${project.name} cover` : 'Project cover'"
                    class="w-full h-full object-cover rounded-xl"
                />
            </div>

            <!-- Project Basic Info -->
            <div class="space-y-4">
                <div>
                    <h3 class="text-base font-semibold text-brand-dark mb-3">About Project</h3>
                    <div class="text-brand-light text-base leading-relaxed space-y-3">
                        <p v-for="(paragraph, index) in aboutParagraphs" :key="index">
                            {{ paragraph }}
                        </p>
                    </div>
                </div>

                <div>
                    <h3 class="text-base font-semibold text-brand-dark mb-3">Assigned Teams</h3>
                    <EmptyState
                        v-if="!project.teams || project.teams.length === 0"
                        icon="Briefcase"
                        title="Belum ada tim"
                    />
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div
                            v-for="team in project.teams"
                            :key="team.id"
                            class="border border-brand-border rounded-2xl hover:border-brand-primary hover:shadow-lg transition-all duration-300 p-4"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-12 h-12 relative flex items-center justify-center rounded-xl overflow-hidden flex-shrink-0"
                                >
                                    <div
                                        class="w-full h-full absolute bg-brand-primary rounded-xl"
                                    ></div>
                                    <component :is="Briefcase" class="w-5 h-5 text-white relative z-10" />
                                </div>
                                <div class="flex-1">
                                    <p class="text-base font-semibold text-brand-dark mb-1">
                                        {{ team.name }}
                                    </p>
                                    <div class="flex items-center gap-2">
                                        <User class="w-3.5 h-3.5 text-brand-light" />
                                        <p class="text-brand-light text-sm">{{ team.members_count }} Members</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 pt-4 border-t border-brand-border">
                                <div v-if="team.leader" class="flex items-center gap-3">
                                    <img loading="lazy"
                                        :src="team.leader.profile_photo || DEFAULT_AVATAR"
                                        :alt="team.leader.name"
                                        class="w-9 h-9 rounded-full object-cover"
                                    />
                                    <div class="flex-1">
                                        <p class="text-brand-dark text-sm font-semibold">
                                            {{ team.leader.name }}
                                        </p>
                                        <p class="text-brand-light text-xs">Team Leader</p>
                                    </div>
                                </div>
                                <div
                                    v-else
                                    class="w-full h-10 rounded-md bg-gray-200 flex items-center justify-center text-gray-400 text-xs"
                                >
                                    No Leader
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white border border-brand-border rounded-2xl p-6 h-fit">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                        <Crown class="w-6 h-6 text-green-600" />
                    </div>
                    <h2 class="text-lg font-semibold text-brand-dark">Project Leader</h2>
                </div>

                <div v-if="project.leader" class="flex items-center gap-4">
                    <img loading="lazy"
                        :src="project.leader?.user?.profile_photo || DEFAULT_AVATAR"
                        :alt="project.leader?.user?.name"
                        class="w-16 h-16 rounded-full object-cover"
                    />
                    <div class="flex-1">
                        <p class="text-base font-semibold text-brand-dark mb-1">
                            {{ project.leader?.user?.name }}
                        </p>
                        <p class="text-brand-light text-sm">
                            {{ project.leader?.job_information?.job_title }}
                        </p>
                    </div>
                    <RouterLink
                        v-if="canViewStaffMemberDetail"
                        :to="{
                            name: 'admin.staffMembers.detail',
                            params: { id: project.leader.id },
                        }"
                        class="border border-brand-border text-brand-dark py-2 px-4 rounded-lg font-medium hover:bg-gray-50 hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 flex items-center gap-2"
                    >
                        <User class="w-4 h-4" />
                        <span class="text-sm font-semibold">Profile</span>
                    </RouterLink>
                </div>

                <button
                    v-if="canEditProject"
                    type="button"
                    @click="openLeaderEditModal"
                    class="mt-4 w-full border border-brand-border rounded-lg hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 px-4 py-2 flex items-center justify-center gap-2"
                >
                    <UserCheck class="w-4 h-4 text-gray-600" />
                    <span class="text-brand-dark text-sm font-semibold">Change Project Leader</span>
                </button>
            </div>

            <div
                class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 p-5"
            >
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-brand-dark text-sm font-medium">Progress</p>
                        <p class="text-brand-dark text-xl font-extrabold leading-none my-2">
                            <AnimatedValue :value="projectProgress" suffix="%" />
                        </p>
                    </div>
                    <div class="w-14 h-14 bg-purple-50 rounded-2xl flex items-center justify-center">
                        <Target class="w-6 h-6 text-purple-600" />
                    </div>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div
                        class="h-3 rounded-full transition-all duration-300"
                        :class="getProgressColor(projectProgress)"
                        :style="{ width: `${projectProgress}%` }"
                    ></div>
                </div>
            </div>

            <div
                v-if="canViewProjectStats"
                class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 p-5"
            >
                <div class="flex items-center justify-between mb-4">
                    <p class="text-brand-dark text-sm font-medium">Squad Snapshot</p>
                    <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                        <Users class="w-5 h-5 text-blue-600" />
                    </div>
                </div>

                <div v-if="squadSummaryLoading" class="space-y-2 animate-pulse">
                    <div class="h-3 rounded bg-primary-50"></div>
                    <div class="h-3 rounded bg-primary-50"></div>
                    <div class="h-3 rounded bg-primary-50"></div>
                </div>

                <template v-else>
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div class="rounded-xl border border-primary-100 bg-primary-50 px-3 py-2">
                            <p class="text-[11px] uppercase tracking-wide text-brand-primary font-semibold">Members</p>
                            <p class="text-brand-dark text-lg font-bold">{{ totalHeadcount }}</p>
                        </div>
                        <div class="rounded-xl border border-primary-100 bg-primary-50 px-3 py-2">
                            <p class="text-[11px] uppercase tracking-wide text-brand-primary font-semibold">Tasks</p>
                            <p class="text-brand-dark text-lg font-bold">{{ totalTaskCount }}</p>
                        </div>
                    </div>

                    <div class="mb-4">
                        <p class="text-brand-dark text-xs font-semibold mb-2">By Stream</p>
                        <div v-if="summaryByStream.length === 0" class="text-xs text-gray-400">Data stream kosong</div>
                        <div v-else class="space-y-1.5">
                            <div
                                v-for="item in summaryByStream"
                                :key="`stream-${item.key}`"
                                class="flex items-center justify-between text-xs"
                            >
                                <span class="text-brand-light">{{ item.label }}</span>
                                <span class="font-semibold text-brand-dark">{{ item.value }}</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center gap-1.5 mb-2">
                            <ListChecks class="w-3.5 h-3.5 text-purple-600" />
                            <p class="text-brand-dark text-xs font-semibold">Task Status</p>
                        </div>
                        <div v-if="summaryTaskByStatus.length === 0" class="text-xs text-gray-400">
                            Data status tugas kosong
                        </div>
                        <div v-else class="space-y-1.5">
                            <div
                                v-for="item in summaryTaskByStatus"
                                :key="`status-${item.key}`"
                                class="flex items-center justify-between text-xs"
                            >
                                <span class="text-brand-light">{{ item.label }}</span>
                                <span class="font-semibold text-brand-dark">{{ item.value }}</span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Budget Card -->
            <div
                v-if="canViewProjectStats"
                class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 p-5"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-brand-dark text-sm font-medium">Budget</p>
                        <p class="text-brand-dark text-xl font-extrabold leading-none my-2">
                            {{ formatRupiah(project.budget) }}
                        </p>
                    </div>
                    <div class="w-14 h-14 bg-green-50 rounded-2xl flex items-center justify-center">
                        <DollarSign class="w-6 h-6 text-green-600" />
                    </div>
                </div>
            </div>

            <div
                class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 p-5"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-brand-dark text-sm font-medium">Start Date</p>
                        <p class="text-brand-dark text-xl font-extrabold leading-none my-2">
                            {{ formatDate(project.start_date) || "N/A" }}
                        </p>
                    </div>
                    <div class="w-14 h-14 bg-indigo-50 rounded-2xl flex items-center justify-center">
                        <Calendar class="w-6 h-6 text-indigo-600" />
                    </div>
                </div>
            </div>

            <div
                class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 p-5"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-brand-dark text-sm font-medium">End Date</p>
                        <p class="text-brand-dark text-xl font-extrabold leading-none my-2">
                            {{ formatDate(project.end_date) || "N/A" }}
                        </p>
                    </div>
                    <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center">
                        <CalendarCheck class="w-6 h-6 text-orange-600" />
                    </div>
                </div>
            </div>

            <div
                class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 transition-all duration-300 p-5"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-brand-dark text-sm font-medium">Duration</p>
                        <p class="text-brand-dark text-xl font-extrabold leading-none my-2">
                            {{ calculateDuration(project.start_date, project.end_date) }}
                        </p>
                    </div>
                    <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center">
                        <Clock class="w-6 h-6 text-blue-600" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Danger Zone -->
    <div v-if="canDeleteProject" class="bg-white border border-danger-100 rounded-2xl p-6">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center">
                <AlertTriangle class="w-6 h-6 text-red-600" />
            </div>
            <h2 class="text-lg font-semibold text-brand-dark">Danger Zone</h2>
        </div>
        <div
            class="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center p-4 bg-red-50 rounded-2xl"
        >
            <div class="flex-1">
                <h3 class="text-base font-semibold text-brand-dark mb-1">Delete Project</h3>
                <p class="text-brand-light text-sm">
                    Permanently delete this project and all associated data. This action cannot be undone.
                </p>
            </div>
            <button
                @click="showDeleteModal = true"
                class="btn-primary rounded-lg hover:brightness-110 focus:ring-2 focus:ring-danger-600 transition-all duration-300 bg-gradient-to-r from-red-500 to-red-600 shadow-lg px-6 py-3 flex items-center gap-2"
            >
                <Trash2 class="w-4 h-4 text-white" />
                <span class="text-brand-white text-sm font-semibold">Delete Project</span>
            </button>
        </div>
    </div>

    <!-- Tasks Section -->
    <TaskBoard :can-create-task="!!project.can_create_task" />

    <ConfirmationModal
        :show="showDeleteModal"
        title="Delete Project"
        :message="`Are you sure you want to delete '${project.name}'? This will permanently remove the project and all associated data. This action cannot be undone.`"
        confirmText="Delete Project"
        cancelText="Cancel"
        type="danger"
        :loading="projectStore.loading"
        @confirm="handleDeleteProject"
        @cancel="showDeleteModal = false"
    />

    <!-- Change Project Leader Modal -->
    <ModalWrapper :show="showLeaderModal" title="Change Project Leader" maxWidth="3xl" @close="closeLeaderModal">
        <template #header>
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                    <Crown class="w-6 h-6 text-green-600" />
                </div>
                <div>
                    <h3 class="text-brand-dark text-xl font-bold">Change Project Leader</h3>
                    <p class="text-brand-light text-sm font-normal">
                        Select a new project leader from eligible staff members
                    </p>
                </div>
            </div>
        </template>

        <div class="py-2">
            <div class="relative mb-4">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <Search class="h-5 w-5 text-gray-400" />
                </div>
                <input
                    type="text"
                    v-model="leaderSearch"
                    aria-label="Search eligible project leaders"
                    class="w-full pl-12 pr-4 py-3 border border-brand-border rounded-2xl focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 transition-all duration-300 font-medium"
                    placeholder="Search by name or job title..."
                />
            </div>

            <div v-if="eligibleLeadersLoading" class="space-y-2 animate-pulse">
                <div class="h-16 rounded-xl bg-gray-100"></div>
                <div class="h-16 rounded-xl bg-gray-100"></div>
                <div class="h-16 rounded-xl bg-gray-100"></div>
            </div>

            <div
                v-else-if="filteredEligibleLeaders.length === 0"
                class="py-8 text-center"
            >
                <SearchX class="w-10 h-10 text-gray-400 mx-auto mb-2" />
                <p class="text-sm text-brand-light">No eligible staff members found</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-96 overflow-y-auto pr-1">
                <button
                    v-for="leader in filteredEligibleLeaders"
                    :key="leader.id"
                    type="button"
                    :disabled="leaderSubmitting"
                    @click="handleSelectLeader(leader)"
                    class="border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 p-3 text-left flex items-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <img
                        loading="lazy"
                        :src="leader?.user?.profile_photo || DEFAULT_AVATAR"
                        :alt="leader?.user?.name"
                        class="w-12 h-12 rounded-xl object-cover"
                    />
                    <div class="flex-1 min-w-0">
                        <p class="text-brand-dark text-sm font-semibold truncate">
                            {{ leader?.user?.name }}
                        </p>
                        <p class="text-brand-light text-xs truncate">
                            {{ leader?.job_information?.job_title || "—" }}
                        </p>
                    </div>
                </button>
            </div>
        </div>

        <template #footer>
            <button
                type="button"
                @click="closeLeaderModal"
                class="border border-brand-border rounded-lg hover:bg-gray-50 transition-all duration-300 px-4 py-2"
            >
                <span class="text-brand-dark text-sm font-semibold">Cancel</span>
            </button>
        </template>
    </ModalWrapper>
</template>
