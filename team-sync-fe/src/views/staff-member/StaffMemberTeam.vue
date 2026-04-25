<script setup>
import { ref, computed, onMounted } from "vue";
import { DEFAULT_AVATAR } from '@/helpers/format';
import {
  Users,
  Building,
  Calendar,
  Folder,
  TrendingUp,
  User,
  FileText,
  Clock,
  CheckCircle,
} from "lucide-vue-next";
import { useStaffMemberStore } from "@/stores/staffMember";
import AnimatedValue from "@/components/common/AnimatedValue.vue";
import CardList from "@/components/admin/project/list/CardList.vue";
import { formatDate as formatDateUtil } from "@/utils/dateUtils.js";

const staffMemberStore = useStaffMemberStore();
const team = ref(null);
const members = ref([]);
const projects = ref([]);
const activeTab = ref("members");
const loadingMembers = ref(false);
const loadingProjects = ref(false);
const loadingTeam = ref(false);
const teamUnavailableMessage = ref("");

const isTeamUnavailable = computed(
  () => !loadingTeam.value && !team.value && Boolean(teamUnavailableMessage.value)
);

const activeMembers = computed(() => {
  if (!members.value) return 0;
  return members.value.filter((m) => !m.left_at).length;
});

const activeMembersStatus = computed(() => {
  const expected = team.value?.expected_size || 0;
  const active = activeMembers.value;
  if (active >= expected) return "Full capacity";
  if (active >= expected * 0.8) return "Nearly full";
  return "Growing";
});

const extractErrorMessage = (error) =>
  error?.response?.data?.message || error?.message || "Unable to load your team.";

const loadTeam = async () => {
  loadingTeam.value = true;
  teamUnavailableMessage.value = "";

  try {
    team.value = await staffMemberStore.fetchMyTeam();
    return true;
  } catch (error) {
    console.error("Error loading team:", error);
    team.value = null;
    teamUnavailableMessage.value = extractErrorMessage(error);
    return false;
  } finally {
    loadingTeam.value = false;
  }
};

const loadMembers = async () => {
  if (members.value.length > 0) return; // Already loaded
  loadingMembers.value = true;
  try {
    members.value = await staffMemberStore.fetchMyTeamMembers();
  } catch (error) {
    console.error("Error loading team members:", error);
  } finally {
    loadingMembers.value = false;
  }
};

const loadProjects = async () => {
  if (projects.value.length > 0) return; // Already loaded
  loadingProjects.value = true;
  try {
    projects.value = await staffMemberStore.fetchMyTeamProjects();
  } catch (error) {
    console.error("Error loading team projects:", error);
  } finally {
    loadingProjects.value = false;
  }
};

const getStatusClass = (status) => {
  const statusMap = {
    active: "bg-[#F0FDF4] text-[#166534]",
    inactive: "bg-[#FEE2E2] text-[#991B1B]",
    archived: "bg-[#F3F4F6] text-[#374151]",
  };
  return statusMap[status?.toLowerCase()] || "bg-[#EBF8FF] text-[#1E40AF]";
};

const getMemberStatus = (member) => {
  if (member.left_at) return "Left";
  const sm = member.staff_member || member.employee;
  const isLeader = sm?.user?.id === team.value?.leader?.id;
  return isLeader ? "Lead" : "Active";
};

const getMemberStatusClass = (member) => {
  if (member.left_at) return "bg-[#F3F4F6] text-[#374151]";
  const sm = member.staff_member || member.employee;
  const isLeader = sm?.user?.id === team.value?.leader?.id;
  return isLeader
    ? "bg-[#FEF3C7] text-[#92400E]"
    : "bg-[#F0FDF4] text-[#166534]";
};

onMounted(async () => {
  const hasTeam = await loadTeam();

  if (!hasTeam) {
    members.value = [];
    projects.value = [];
    return;
  }

  await Promise.all([loadMembers(), loadProjects()]);
});
</script>

<template>
  <div class="flex-1 flex flex-col overflow-hidden">
    <!-- Team Detail Content -->
    <main class="main-content flex-1 overflow-auto p-5">
      <div
        v-if="loadingTeam"
        class="bg-white border border-[#DCDEDD] rounded-[20px] p-8 text-center"
        data-testid="my-team-loading"
      >
        <Users class="w-10 h-10 text-gray-400 mx-auto mb-3" />
        <p class="text-brand-dark text-lg font-semibold">Loading your team workspace...</p>
      </div>

      <div
        v-else-if="isTeamUnavailable"
        class="bg-white border border-[#DCDEDD] rounded-[20px] p-8 text-center"
        data-testid="my-team-empty"
      >
        <Users class="w-12 h-12 text-gray-400 mx-auto mb-4" />
        <h2 class="text-brand-dark text-2xl font-bold mb-2">You are not assigned to a team yet</h2>
        <p class="text-gray-600 max-w-2xl mx-auto">
          {{ teamUnavailableMessage }} Please contact HR or your manager to assign your team first.
        </p>
      </div>

      <template v-else>
      <!-- Team Header -->
      <div class="bg-white border border-[#DCDEDD] rounded-[20px] mb-6 p-6">
        <div class="flex items-center gap-6">
          <div class="relative">
            <div
              class="w-32 h-32 relative flex items-center justify-center rounded-full overflow-hidden"
            >
              <div
                class="w-full h-full absolute bg-gradient-to-br from-primary-500 to-primary-600 rounded-full"
              ></div>
              <Users class="w-16 h-16 text-white relative z-10" />
            </div>
          </div>
          <div class="flex-1">
            <div class="flex items-center gap-4 mb-3">
              <h1 class="text-brand-dark text-3xl font-extrabold">
                {{ team?.name || "Loading..." }}
              </h1>
              <span
                v-if="team?.status"
                class="px-3 py-1 rounded-md text-sm font-semibold"
                :class="getStatusClass(team.status)"
              >
                {{ team.status }}
              </span>
            </div>
            <div class="flex items-center gap-6 text-base text-gray-600">
              <div class="flex items-center gap-2">
                <Users class="w-4 h-4" />
                <span>{{ members?.length || 0 }} members</span>
              </div>
              <div class="flex items-center gap-2">
                <Building class="w-4 h-4" />
                <span>{{ team?.department || "N/A" }}</span>
              </div>
              <div class="flex items-center gap-2">
                <Calendar class="w-4 h-4" />
                <span>{{ team?.created_at ? formatDateUtil(team.created_at) : "N/A" }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Team Statistics -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div
          class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
        >
          <div class="flex items-center justify-between">
            <div>
              <p class="text-brand-dark text-base font-medium">
                Active Members
              </p>
              <p
                class="text-brand-dark text-3xl font-extrabold leading-tight my-2"
              >
                <AnimatedValue :value="activeMembers" />
              </p>
              <p class="text-success text-base font-medium">
                {{ activeMembersStatus }}
              </p>
            </div>
            <div
              class="w-12 h-12 bg-blue-50 rounded-[16px] flex items-center justify-center"
            >
              <Users class="w-6 h-6 text-blue-600" />
            </div>
          </div>
        </div>
        <div
          class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
        >
          <div class="flex items-center justify-between">
            <div>
              <p class="text-brand-dark text-base font-medium">
                Projects Assigned
              </p>
              <p
                class="text-brand-dark text-3xl font-extrabold leading-tight my-2"
              >
                <AnimatedValue :value="projects?.length || 0" />
              </p>
              <p class="text-success text-base font-medium">Active projects</p>
            </div>
            <div
              class="w-12 h-12 bg-green-50 rounded-[16px] flex items-center justify-center"
            >
              <Folder class="w-6 h-6 text-green-600" />
            </div>
          </div>
        </div>
        <div
          class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
        >
          <div class="flex items-center justify-between">
            <div>
              <p class="text-brand-dark text-base font-medium">Expected Size</p>
              <p
                class="text-brand-dark text-3xl font-extrabold leading-tight my-2"
              >
                <AnimatedValue :value="team?.expected_size || 0" />
              </p>
              <p class="text-success text-base font-medium">Team capacity</p>
            </div>
            <div
              class="w-12 h-12 bg-purple-50 rounded-[16px] flex items-center justify-center"
            >
              <TrendingUp class="w-6 h-6 text-purple-600" />
            </div>
          </div>
        </div>
        <div
          class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
        >
          <div class="flex items-center justify-between">
            <div>
              <p class="text-brand-dark text-base font-medium">Team Lead</p>
              <p
                class="text-brand-dark text-xl font-extrabold leading-tight my-2"
              >
                {{ team?.leader?.name || "N/A" }}
              </p>
              <p class="text-success text-base font-medium">Leader</p>
            </div>
            <div
              class="w-12 h-12 bg-orange-50 rounded-[16px] flex items-center justify-center"
            >
              <User class="w-6 h-6 text-orange-600" />
            </div>
          </div>
        </div>
      </div>

      <!-- Tab Navigation -->
      <div class="bg-white border border-[#DCDEDD] rounded-[16px] p-6 mb-6">
        <div class="flex items-center gap-2">
          <button
            @click="activeTab = 'members'"
            :class="
              activeTab === 'members'
                ? 'px-6 py-3 rounded-[12px] bg-gradient-to-r from-primary-500 to-primary-600 text-white font-semibold text-base transition-all duration-300'
                : 'px-6 py-3 rounded-[12px] border border-[#DCDEDD] bg-white text-brand-dark font-medium text-base hover:border-[#0C51D9] hover:border-2 transition-all duration-300'
            "
          >
            <Users class="w-5 h-5 inline mr-2" />
            Members
          </button>
          <button
            @click="activeTab = 'projects'"
            :class="
              activeTab === 'projects'
                ? 'px-6 py-3 rounded-[12px] bg-gradient-to-r from-primary-500 to-primary-600 text-white font-semibold text-base transition-all duration-300'
                : 'px-6 py-3 rounded-[12px] border border-[#DCDEDD] bg-white text-brand-dark font-medium text-base hover:border-[#0C51D9] hover:border-2 transition-all duration-300'
            "
          >
            <Folder class="w-5 h-5 inline mr-2" />
            Projects
          </button>
          <button
            @click="activeTab = 'description'"
            :class="
              activeTab === 'description'
                ? 'px-6 py-3 rounded-[12px] bg-gradient-to-r from-primary-500 to-primary-600 text-white font-semibold text-base transition-all duration-300'
                : 'px-6 py-3 rounded-[12px] border border-[#DCDEDD] bg-white text-brand-dark font-medium text-base hover:border-[#0C51D9] hover:border-2 transition-all duration-300'
            "
          >
            <FileText class="w-5 h-5 inline mr-2" />
            Description
          </button>
        </div>
      </div>

      <!-- Team Members Section -->
      <div
        v-if="activeTab === 'members'"
        class="bg-white border border-[#DCDEDD] rounded-[16px] p-6 mb-6"
      >
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center gap-3">
            <div
              class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center"
            >
              <Users class="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-lg font-bold">Team Members</h3>
              <p class="text-brand-light text-sm">Current team composition</p>
            </div>
          </div>
        </div>

        <!-- Members Grid -->
        <div v-if="loadingMembers" class="text-center py-12">
          <p class="text-gray-500 text-lg font-medium">Loading members...</p>
        </div>
        <div
          v-else
          class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4"
        >
          <div
            v-for="member in members"
            :key="member.id"
            class="border border-[#DCDEDD] rounded-[16px] hover:border-[#0C51D9] hover:border-2 hover:shadow-lg transition-all duration-300 p-4"
          >
            <div class="flex flex-col items-center mb-3">
              <div class="relative">
                <img
                  :src="
                    (member.staff_member || member.employee)?.user?.profile_photo ||
                    DEFAULT_AVATAR
                  "
                  :alt="(member.staff_member || member.employee)?.user?.name"
                  class="w-[100px] h-[100px] rounded-full object-cover mb-3"
                />
                <span
                  class="absolute bottom-2 left-1/2 transform -translate-x-1/2 px-2 py-1 rounded-md text-xs font-semibold whitespace-nowrap"
                  :class="getMemberStatusClass(member)"
                >
                  {{ getMemberStatus(member) }}
                </span>
              </div>
            </div>
            <div class="text-center mb-3">
              <h4 class="text-brand-dark text-lg font-bold">
                {{ (member.staff_member || member.employee)?.user?.name || "N/A" }}
              </h4>
              <p class="text-brand-light text-base">
                {{ (member.staff_member || member.employee)?.job_information?.job_title || (member.staff_member || member.employee)?.jobInformation?.job_title || "N/A" }}
              </p>
            </div>
            <div class="space-y-1 mb-3">
              <div class="flex items-center gap-2 text-sm text-gray-500">
                <Calendar class="w-4 h-4" />
                <span>{{ member.joined_at ? `Joined ${formatDateUtil(member.joined_at)}` : "N/A" }}</span>
              </div>
              <div class="flex items-center gap-2 text-sm text-gray-500">
                <Clock class="w-4 h-4" />
                <span>{{
                    member.joined_at ? formatDateUtil(member.joined_at) : "N/A"
                  }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div
          v-if="!loadingMembers && (!members || members.length === 0)"
          class="text-center py-12"
        >
          <Users class="w-12 h-12 text-gray-400 mx-auto mb-4" />
          <p class="text-gray-500 text-lg font-medium">No team members</p>
          <p class="text-gray-400 text-sm">Team members will appear here</p>
        </div>
      </div>

      <!-- Projects Section -->
      <div
        v-if="activeTab === 'projects'"
        class="bg-white border border-[#DCDEDD] rounded-[16px] p-6 mb-6"
      >
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center gap-3">
            <div
              class="w-12 h-12 bg-purple-50 rounded-[12px] flex items-center justify-center"
            >
              <Folder class="w-6 h-6 text-purple-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-lg font-bold">Team Projects</h3>
              <p class="text-brand-light text-sm">
                Projects assigned to {{ team?.name }}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span
              class="px-3 py-1 rounded-md text-sm font-semibold bg-[#EBF8FF] text-[#1E40AF]"
            >
              {{ projects?.length || 0 }} Projects
            </span>
          </div>
        </div>

        <!-- Projects Grid -->
        <div v-if="loadingProjects" class="text-center py-12">
          <p class="text-gray-500 text-lg font-medium">Loading projects...</p>
        </div>
        <div
          v-else
          class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4"
        >
          <CardList
            v-for="project in projects"
            :key="project.id"
            :data="project"
          />
        </div>

        <!-- Empty State -->
        <div
          v-if="!loadingProjects && (!projects || projects.length === 0)"
          class="text-center py-12"
        >
          <Folder class="w-12 h-12 text-gray-400 mx-auto mb-4" />
          <p class="text-gray-500 text-lg font-medium">No projects assigned</p>
          <p class="text-gray-400 text-sm">
            Projects assigned to your team will appear here
          </p>
        </div>
      </div>

      <!-- Description Section -->
      <div
        v-if="activeTab === 'description'"
        class="bg-white border border-[#DCDEDD] rounded-[16px] p-6 mb-6"
      >
        <div class="flex items-center gap-3 mb-6">
          <div
            class="w-12 h-12 bg-orange-50 rounded-[12px] flex items-center justify-center"
          >
            <FileText class="w-6 h-6 text-orange-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-lg font-bold">Team Description</h3>
            <p class="text-brand-light text-sm">Team overview and details</p>
          </div>
        </div>

        <div class="space-y-4">
          <div>
            <h4 class="text-brand-dark text-base font-semibold mb-2">
              Description
            </h4>
            <p class="text-gray-600 text-base leading-relaxed">
              {{ team?.description || "No description available" }}
            </p>
          </div>

          <div v-if="team?.responsibilities?.length">
            <h4 class="text-brand-dark text-base font-semibold mb-2">
              Responsibilities
            </h4>
            <ul class="space-y-2">
              <li
                v-for="(responsibility, index) in team.responsibilities"
                :key="index"
                class="flex items-start gap-2 text-gray-600"
              >
                <CheckCircle
                  class="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0"
                />
                <span>{{ responsibility }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
      </template>
    </main>
  </div>
</template>
