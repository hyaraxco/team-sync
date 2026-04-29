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
} from "lucide-vue-next";
import { useRoute } from "vue-router";
import router from "@/router";
import { onMounted, ref, computed } from "vue";
import { formatDate, calculateDuration } from "@/utils/dateUtils";
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

const route = useRoute();
const id = route.params.id;

const projectStore = useProjectStore();
const { fetchProject, fetchProjectSquadSummary } = projectStore;
const toast = useToast();

const project = ref({});
const squadSummary = ref(null);
const squadSummaryLoading = ref(false);

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
      projectStore.error ||
        error?.response?.data?.message ||
        "Failed to load project.",
    );
    project.value = {};
  }
};

const handleFetchSquadSummary = async () => {
  squadSummaryLoading.value = true;

  try {
    squadSummary.value = await fetchProjectSquadSummary(id);
  } catch {
    squadSummary.value = null;
  } finally {
    squadSummaryLoading.value = false;
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
  const completedTasks = project.value.tasks.filter(
    (task) => task.status === "done"
  ).length;

  const progress = Math.round((completedTasks / totalTasks) * 100);

  return progress;
});

onMounted(async () => {
  await Promise.all([handleFetchProject(), handleFetchSquadSummary()]);
});
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div
      class="lg:col-span-2 bg-white border border-[#DCDEDD] rounded-[20px] p-6"
    >
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div
            class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center"
          >
            <Briefcase class="w-6 h-6 text-blue-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-xl font-bold">
              Project Information
            </h3>
            <p class="text-brand-light text-sm font-normal">
              Complete project overview
            </p>
          </div>
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
        class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
      >
        <img
          :src="project.photo"
          alt="Project Image"
          class="w-full h-full object-cover rounded-[12px]"
        />
      </div>

      <!-- Project Basic Info -->
      <div class="space-y-4">
        <div>
          <h4 class="text-brand-dark text-2xl font-bold mb-2">
            {{ project.name }}
          </h4>
        </div>

        <div>
          <h5 class="text-brand-dark text-base font-semibold mb-3">
            About Project
          </h5>
          <div class="text-brand-light text-base leading-relaxed space-y-3">
            <p v-for="(paragraph, index) in aboutParagraphs" :key="index">
              {{ paragraph }}
            </p>
          </div>
        </div>

        <div>
          <h5 class="text-brand-dark text-base font-semibold mb-3">
            Assigned Teams
          </h5>
          <EmptyState
            v-if="!project.teams || project.teams.length === 0"
            icon="Briefcase"
            title="No teams assigned"
          />
          <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              v-for="team in project.teams"
              :key="team.id"
              class="border border-[#DCDEDD] rounded-[16px] hover:border-[#0C51D9] hover:shadow-lg transition-all duration-300 p-4"
            >
              <div class="flex items-center gap-3">
                <div
                  class="w-12 h-12 relative flex items-center justify-center rounded-[12px] overflow-hidden flex-shrink-0"
                >
                  <div
                    class="w-full h-full absolute bg-gradient-to-br from-primary-500 to-primary-600 rounded-[12px]"
                  ></div>
                  <component
                    :is="Briefcase"
                    class="w-5 h-5 text-white relative z-10"
                  />
                </div>
                <div class="flex-1">
                  <h4 class="text-brand-dark text-base font-bold mb-1">
                    {{ team.name }}
                  </h4>
                  <div class="flex items-center gap-2">
                    <User class="w-3.5 h-3.5 text-brand-light" />
                    <p class="text-brand-light text-sm">
                      {{ team.members_count }} Members
                    </p>
                  </div>
                </div>
              </div>

              <div class="mt-4 pt-4 border-t border-[#DCDEDD]">
                <div v-if="team.leader" class="flex items-center gap-3">
                  <img
                    v-if="team.leader.profile_photo"
                    :src="team.leader.profile_photo"
                    :alt="team.leader.name"
                    class="w-9 h-9 rounded-full object-cover"
                  />
                  <div
                    v-else
                    class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center"
                  >
                    <User class="w-4 h-4 text-gray-400" />
                  </div>
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
      <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6 h-fit">
        <div class="flex items-center gap-3 mb-4">
          <div
            class="w-12 h-12 bg-green-50 rounded-[12px] flex items-center justify-center"
          >
            <Crown class="w-6 h-6 text-green-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-xl font-bold">Project Leader</h3>
            <p class="text-brand-light text-sm font-normal">
              Team leader information
            </p>
          </div>
        </div>

        <div v-if="project.leader" class="flex items-center gap-4">
          <img
            :src="project.leader?.user?.profile_photo"
            :alt="project.leader?.user?.name"
            class="w-16 h-16 rounded-full object-cover"
            v-if="project.leader?.user?.profile_photo"
          />
          <div
            class="w-12 h-12 rounded-[12px] flex items-center justify-center bg-gray-100"
            v-else
          >
            <User class="w-5 h-5 text-gray-400" />
          </div>
          <div class="flex-1">
            <h4 class="text-brand-dark text-md font-bold mb-1">
              {{ project.leader?.user?.name }}
            </h4>
            <p class="text-brand-light text-sm">
              {{ project.leader?.job_information?.job_title }}
            </p>
          </div>
          <RouterLink
            :to="{
              name: 'admin.staffMembers.detail',
              params: { id: project.leader.id },
            }"
            class="border border-[#DCDEDD] text-brand-dark py-2 px-4 rounded-[8px] font-medium hover:bg-gray-50 hover:border-[#0C51D9] hover:border-2 transition-all duration-300 flex items-center gap-2"
          >
            <User class="w-4 h-4" />
            <span class="text-sm font-semibold">Profile</span>
          </RouterLink>
        </div>
      </div>

      <div
        class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
      >
        <div class="flex items-center justify-between mb-4">
          <div>
            <p class="text-brand-dark text-sm font-medium">Progress</p>
            <p class="text-brand-dark text-xl font-extrabold leading-none my-2">
              <AnimatedValue :value="projectProgress" suffix="%" />
            </p>
            <p class="text-purple-600 text-sm font-medium">
              Project completion
            </p>
          </div>
          <div
            class="w-14 h-14 bg-purple-50 rounded-[16px] flex items-center justify-center"
          >
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
        class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
      >
        <div class="flex items-center justify-between mb-4">
          <div>
            <p class="text-brand-dark text-sm font-medium">Squad Snapshot</p>
            <p class="text-brand-light text-xs font-normal">
              Team and task distribution for this project
            </p>
          </div>
          <div
            class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center"
          >
            <Users class="w-5 h-5 text-blue-600" />
          </div>
        </div>

        <div v-if="squadSummaryLoading" class="space-y-2 animate-pulse">
          <div class="h-3 rounded bg-[#E9EEF7]"></div>
          <div class="h-3 rounded bg-[#E9EEF7]"></div>
          <div class="h-3 rounded bg-[#E9EEF7]"></div>
        </div>

        <template v-else>
          <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="rounded-[12px] border border-[#E6ECF7] bg-[#F7FAFF] px-3 py-2">
              <p class="text-[11px] uppercase tracking-wide text-[#0C51D9] font-semibold">
                Members
              </p>
              <p class="text-brand-dark text-lg font-bold">{{ totalHeadcount }}</p>
            </div>
            <div class="rounded-[12px] border border-[#E6ECF7] bg-[#F7FAFF] px-3 py-2">
              <p class="text-[11px] uppercase tracking-wide text-[#0C51D9] font-semibold">
                Tasks
              </p>
              <p class="text-brand-dark text-lg font-bold">{{ totalTaskCount }}</p>
            </div>
          </div>

          <div class="mb-4">
            <p class="text-brand-dark text-xs font-semibold mb-2">By Stream</p>
            <div v-if="summaryByStream.length === 0" class="text-xs text-gray-400">No stream data</div>
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
            <div v-if="summaryTaskByStatus.length === 0" class="text-xs text-gray-400">No task status data</div>
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
        class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-brand-dark text-sm font-medium">Budget</p>
            <p class="text-brand-dark text-xl font-extrabold leading-none my-2">
              {{ formatRupiah(project.budget) }}
            </p>
            <p class="text-success text-sm font-medium">Project budget</p>
          </div>
          <div
            class="w-14 h-14 bg-green-50 rounded-[16px] flex items-center justify-center"
          >
            <DollarSign class="w-6 h-6 text-green-600" />
          </div>
        </div>
      </div>

      <div
        class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-brand-dark text-sm font-medium">Start Date</p>
            <p class="text-brand-dark text-xl font-extrabold leading-none my-2">
              {{ formatDate(project.start_date) || "N/A" }}
            </p>
            <p class="text-indigo-600 text-sm font-medium">Project kickoff</p>
          </div>
          <div
            class="w-14 h-14 bg-indigo-50 rounded-[16px] flex items-center justify-center"
          >
            <Calendar class="w-6 h-6 text-indigo-600" />
          </div>
        </div>
      </div>

      <div
        class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-brand-dark text-sm font-medium">End Date</p>
            <p class="text-brand-dark text-xl font-extrabold leading-none my-2">
              {{ formatDate(project.end_date) || "N/A" }}
            </p>
            <p class="text-orange-600 text-sm font-medium">Project deadline</p>
          </div>
          <div
            class="w-14 h-14 bg-orange-50 rounded-[16px] flex items-center justify-center"
          >
            <CalendarCheck class="w-6 h-6 text-orange-600" />
          </div>
        </div>
      </div>

      <div
        class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-brand-dark text-sm font-medium">Duration</p>
            <p class="text-brand-dark text-xl font-extrabold leading-none my-2">
              {{ calculateDuration(project.start_date, project.end_date) }}
            </p>
            <p class="text-blue-600 text-sm font-medium">Project timeline</p>
          </div>
          <div
            class="w-14 h-14 bg-blue-50 rounded-[16px] flex items-center justify-center"
          >
            <Clock class="w-6 h-6 text-blue-600" />
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tasks Section -->
  <TaskBoard />
</template>
