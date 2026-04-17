<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import { useEmployeeStore } from "@/stores/employee";
import { useAuthStore } from "@/stores/auth";
import { storeToRefs } from "pinia";
import StatusBadge from "@/components/common/StatusBadge.vue";
import { formatDateLong as formatDate } from "@/utils/dateUtils.js";
import {
  formatRupiah as formatCurrency,
  capitalize,
} from "@/utils/formatUtils.js";
import AnimatedValue from "@/components/common/AnimatedValue.vue";
import {
  Edit,
  CheckCircle,
  CalendarCheck,
  Folder,
  TrendingUp,
  Contact,
  Briefcase,
  MapPin,
  Phone,
  Users,
  ListChecks,
  Calendar,
  Code,
  Star,
  Building,
  User,
  Clock,
} from "lucide-vue-next";

const employeeStore = useEmployeeStore();
const authStore = useAuthStore();
const { loading, performanceStatistics, error } = storeToRefs(employeeStore);

type AuthEmployeeProfile = {
  user?: {
    name?: string;
    email?: string;
    profile_photo?: string | null;
  };
  emergency_contacts?: unknown[];
  [key: string]: unknown;
};

type AuthUser = {
  name?: string;
  email?: string;
  profile_photo?: string | null;
  employee_profile?: AuthEmployeeProfile | null;
};

const profile = ref<any>(null);
const authUser = computed(() => authStore.user as AuthUser | null);

const loadProfile = async () => {
  try {
    profile.value = await employeeStore.fetchMyProfile();
    if (profile.value?.id) {
      await employeeStore.fetchPerformanceStatistics(profile.value.id);
    }
  } catch (fetchError) {
    console.error("Error loading employee profile:", fetchError);
  }
};

const fallbackProfile = computed(() => {
  const currentUser = authUser.value;
  const employeeProfile = currentUser?.employee_profile;

  if (!employeeProfile && !currentUser) {
    return null;
  }

  return {
    ...(employeeProfile ?? {}),
    user: {
      ...(employeeProfile?.user ?? {}),
      name: currentUser?.name,
      email: currentUser?.email,
      profile_photo: currentUser?.profile_photo,
    },
    emergency_contacts: employeeProfile?.emergency_contacts || [],
  };
});

const resolvedProfile = computed(() => profile.value || fallbackProfile.value);
const hasDetailedProfile = computed(() => Boolean(profile.value));

// Dummy tasks
const latestTasks = ref([
  {
    id: 1,
    title: "API Integration for User Dashboard",
    description: "Integrate REST APIs for the new user dashboard features...",
    status: "In Progress",
    statusClass: "bg-[#FEF3C7] text-[#D97706]",
    dueDate: "Jan 30, 2024",
    project: "Dashboard Project",
  },
  {
    id: 2,
    title: "Database Schema Optimization",
    description: "Review and optimize database queries for improved...",
    status: "Waiting",
    statusClass: "bg-[#EBF8FF] text-[#1E40AF]",
    dueDate: "Feb 5, 2024",
    project: "Performance Project",
  },
  {
    id: 3,
    title: "Code Review for Payment Module",
    description: "Review payment processing code and provide feedback on...",
    status: "Completed",
    statusClass: "bg-[#F0FDF4] text-[#166534]",
    dueDate: "Completed: Jan 25, 2024",
    project: "Payment Project",
  },
  {
    id: 4,
    title: "Unit Tests for Authentication",
    description: "Write comprehensive unit tests for the new authentication...",
    status: "In Progress",
    statusClass: "bg-[#FEF3C7] text-[#D97706]",
    dueDate: "Feb 1, 2024",
    project: "Security Project",
  },
  {
    id: 5,
    title: "Documentation Update",
    description: "Update technical documentation for the new feature...",
    status: "Waiting",
    statusClass: "bg-[#EBF8FF] text-[#1E40AF]",
    dueDate: "Feb 8, 2024",
    project: "Documentation Project",
  },
]);

onMounted(() => {
  loadProfile();
});
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64">
    <p class="text-gray-500">Loading...</p>
  </div>

  <div v-else-if="resolvedProfile">
    <div
      v-if="typeof error === 'string' && !hasDetailedProfile"
      class="bg-amber-50 border border-amber-200 text-amber-900 rounded-[16px] px-5 py-4 mb-6"
    >
      {{ error }}. Showing basic account information while the full employee profile is unavailable.
    </div>

    <!-- Employee Header -->
    <div class="bg-white border border-[#DCDEDD] rounded-[20px] mb-6 p-6">
      <div class="flex items-center gap-6">
        <div class="relative">
          <img
            :src="
              resolvedProfile?.user?.profile_photo ||
              'https://ui-avatars.com/api/?name=' + resolvedProfile?.user?.name
            "
            :alt="resolvedProfile?.user?.name"
            class="w-32 h-32 rounded-full object-cover"
          />
          <StatusBadge
            v-if="resolvedProfile?.job_information?.status"
            type="status"
            :value="resolvedProfile.job_information.status"
            class="absolute bottom-0 left-1/2 transform -translate-x-1/2 translate-y-1/2 rounded-full !px-3 shadow-sm"
          />
        </div>
        <div class="flex-1">
          <div class="flex items-center gap-4 mb-2">
            <h1 class="text-brand-dark text-3xl font-extrabold">
              {{ resolvedProfile?.user?.name }}
            </h1>
            <StatusBadge
              v-if="resolvedProfile?.job_information?.skill_level"
              type="skill"
              :value="resolvedProfile.job_information.skill_level"
              class="!px-3 text-sm"
            />
          </div>
          <p class="text-brand-light text-lg mb-3">
            {{ capitalize(resolvedProfile?.job_information?.job_title) }}
          </p>
          <div class="flex items-center gap-6 text-base text-gray-600">
            <div class="flex items-center gap-2">
              <Building class="w-4 h-4" />
              <span>{{
                capitalize(resolvedProfile?.job_information?.work_location)
              }}</span>
            </div>
            <div class="flex items-center gap-2">
              <User class="w-4 h-4" />
              <span>{{ resolvedProfile?.code || "-" }}</span>
            </div>
            <div class="flex items-center gap-2">
              <Calendar class="w-4 h-4" />
              <span
                >Joined
                {{ formatDate(resolvedProfile?.job_information?.start_date) }}</span
              >
            </div>
          </div>
        </div>
        <div class="flex items-center">
          <button
            class="btn-primary rounded-[8px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-6 py-3 flex items-center gap-2"
          >
            <Edit class="w-4 h-4 text-white" />
            <span class="text-brand-white text-sm font-semibold"
              >Request Edit to Manager</span
            >
          </button>
        </div>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <!-- Tasks Completed Card -->
      <div
        class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-brand-dark text-sm font-medium">Tasks Completed</p>
            <p
              class="text-brand-dark text-3xl font-extrabold leading-none my-2"
            >
              <AnimatedValue :value="performanceStatistics?.tasks_completed || 0" />
            </p>
            <p class="text-success text-sm font-medium">This month</p>
          </div>
          <div
            class="w-14 h-14 bg-blue-50 rounded-[16px] flex items-center justify-center"
          >
            <CheckCircle class="w-6 h-6 text-blue-600" />
          </div>
        </div>
      </div>

      <!-- Attendance Rate Card -->
      <div
        class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-brand-dark text-sm font-medium">Attendance Rate</p>
            <p
              class="text-brand-dark text-3xl font-extrabold leading-none my-2"
            >
              <AnimatedValue :value="performanceStatistics?.attendance_rate || 0" suffix="%" />
            </p>
            <p class="text-success text-sm font-medium">Above average</p>
          </div>
          <div
            class="w-14 h-14 bg-green-50 rounded-[16px] flex items-center justify-center"
          >
            <CalendarCheck class="w-6 h-6 text-green-600" />
          </div>
        </div>
      </div>

      <!-- Active Projects Card -->
      <div
        class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-brand-dark text-sm font-medium">Active Projects</p>
            <p
              class="text-brand-dark text-3xl font-extrabold leading-none my-2"
            >
              <AnimatedValue :value="performanceStatistics?.projects_count || 0" />
            </p>
            <p class="text-success text-sm font-medium">Current projects</p>
          </div>
          <div
            class="w-14 h-14 bg-purple-50 rounded-[16px] flex items-center justify-center"
          >
            <Folder class="w-6 h-6 text-purple-600" />
          </div>
        </div>
      </div>

      <!-- Performance Rating Card -->
      <div
        class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-brand-dark text-sm font-medium">Performance</p>
            <p
              class="text-brand-dark text-3xl font-extrabold leading-none my-2"
            >
              <AnimatedValue :value="performanceStatistics?.performance_score || 0" suffix="%" />
            </p>
            <p class="text-success text-sm font-medium">Excellent rating</p>
          </div>
          <div
            class="w-14 h-14 bg-orange-50 rounded-[16px] flex items-center justify-center"
          >
            <TrendingUp class="w-6 h-6 text-orange-600" />
          </div>
        </div>
      </div>
    </div>

    <!-- Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6 items-start">
      <!-- Left Column -->
      <div class="space-y-6">
        <!-- Personal Information -->
        <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-5">
          <div class="flex items-center gap-3 mb-4">
            <div
              class="w-12 h-12 bg-teal-50 rounded-[12px] flex items-center justify-center"
            >
              <Contact class="w-6 h-6 text-teal-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-lg font-bold">
                Personal Information
              </h3>
              <p class="text-brand-light text-base">
                Your contact and profile details
              </p>
            </div>
          </div>
          <div class="space-y-4">
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Email</span>
              <span class="text-brand-dark text-base font-medium">{{
                resolvedProfile?.user?.email
              }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Phone</span>
              <span class="text-brand-dark text-base font-medium">{{
                resolvedProfile?.phone || "-"
              }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Date of Birth</span>
              <span class="text-brand-dark text-base font-medium">{{
                formatDate(resolvedProfile?.date_of_birth)
              }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Employee ID</span>
              <span class="text-brand-dark text-base font-medium">{{
                resolvedProfile?.code || "-"
              }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Office Location</span>
              <span class="text-brand-dark text-base font-medium">{{
                capitalize(resolvedProfile?.job_information?.work_location)
              }}</span>
            </div>
            <div class="flex justify-between items-start">
              <span class="text-brand-light text-base">Hobbies</span>
              <span
                class="text-brand-dark text-base font-medium text-right max-w-[60%]"
                >{{ resolvedProfile?.hobby || "-" }}</span
              >
            </div>
          </div>
        </div>

        <!-- Employment Details -->
        <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-5">
          <div class="flex items-center gap-3 mb-4">
            <div
              class="w-12 h-12 bg-green-50 rounded-[12px] flex items-center justify-center"
            >
              <Briefcase class="w-6 h-6 text-green-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-lg font-bold">
                Employment Details
              </h3>
              <p class="text-brand-light text-base">
                Work and compensation information
              </p>
            </div>
          </div>
          <div class="space-y-4">
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Job Title</span>
              <span class="text-brand-dark text-base font-medium">{{
                capitalize(resolvedProfile?.job_information?.job_title)
              }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Start Date</span>
              <span class="text-brand-dark text-base font-medium">{{
                formatDate(resolvedProfile?.job_information?.start_date)
              }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Employment Type</span>
              <span class="text-brand-dark text-base font-medium">{{
                capitalize(resolvedProfile?.job_information?.employment_type)
              }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Monthly Salary</span>
              <span class="text-brand-dark text-base font-medium">{{
                formatCurrency(resolvedProfile?.job_information?.monthly_salary)
              }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Skill Level</span>
              <StatusBadge
                v-if="resolvedProfile?.job_information?.skill_level"
                type="skill"
                :value="resolvedProfile.job_information.skill_level"
              />
            </div>
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Work Location</span>
              <span class="text-brand-dark text-base font-medium">{{
                capitalize(resolvedProfile?.job_information?.work_location)
              }}</span>
            </div>
          </div>
        </div>

        <!-- Location Details -->
        <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-5">
          <div class="flex items-center gap-3 mb-4">
            <div
              class="w-12 h-12 bg-purple-50 rounded-[12px] flex items-center justify-center"
            >
              <MapPin class="w-6 h-6 text-purple-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-lg font-bold">
                Location Details
              </h3>
              <p class="text-brand-light text-base">
                Address and location information
              </p>
            </div>
          </div>
          <div class="space-y-4">
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Gender</span>
              <span class="text-brand-dark text-base font-medium">{{
                capitalize(resolvedProfile?.gender)
              }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Place of Birth</span>
              <span class="text-brand-dark text-base font-medium">{{
                resolvedProfile?.place_of_birth || "-"
              }}</span>
            </div>
            <div class="flex justify-between items-start">
              <span class="text-brand-light text-base">Address</span>
              <span
                class="text-brand-dark text-base font-medium text-right max-w-[60%]"
                >{{ resolvedProfile?.address || "-" }}</span
              >
            </div>
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">City</span>
              <span class="text-brand-dark text-base font-medium">{{
                resolvedProfile?.city || "-"
              }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Post Code</span>
              <span class="text-brand-dark text-base font-medium">{{
                resolvedProfile?.postal_code || "-"
              }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Country</span>
              <span class="text-brand-dark text-base font-medium"
                >Indonesia</span
              >
            </div>
          </div>
        </div>

        <!-- Emergency Contact -->
        <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-5">
          <div class="flex items-center gap-3 mb-4">
            <div
              class="w-12 h-12 bg-red-50 rounded-[12px] flex items-center justify-center"
            >
              <Phone class="w-6 h-6 text-red-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-lg font-bold">
                Emergency Contact
              </h3>
              <p class="text-brand-light text-base">
                Person to contact in case of emergency
              </p>
            </div>
          </div>
          <div
            class="space-y-4"
            v-if="
              resolvedProfile?.emergency_contacts &&
              resolvedProfile.emergency_contacts.length > 0
            "
          >
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Contact Name</span>
              <span class="text-brand-dark text-base font-medium">{{
                resolvedProfile.emergency_contacts[0]?.full_name || "-"
              }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Relationship</span>
              <span class="text-brand-dark text-base font-medium">{{
                capitalize(resolvedProfile.emergency_contacts[0]?.relationship)
              }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Phone Number</span>
              <span class="text-brand-dark text-base font-medium">{{
                resolvedProfile.emergency_contacts[0]?.phone || "-"
              }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Email</span>
              <span class="text-brand-dark text-base font-medium">{{
                resolvedProfile.emergency_contacts[0]?.email || "-"
              }}</span>
            </div>
          </div>
          <div v-else class="text-center text-gray-500 py-4">
            No emergency contact added
          </div>
        </div>
      </div>

      <!-- Right Column -->
      <div class="space-y-6">
        <!-- Team Information -->
        <div
          class="bg-white border border-[#DCDEDD] rounded-[20px] p-5 h-fit"
          v-if="resolvedProfile?.team"
        >
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
              <div
                class="w-12 h-12 bg-indigo-50 rounded-[12px] flex items-center justify-center"
              >
                <Users class="w-6 h-6 text-indigo-600" />
              </div>
              <div>
                <h3 class="text-brand-dark text-lg font-bold">My Team</h3>
                <p class="text-brand-light text-base">Team information</p>
              </div>
            </div>
            <button
              class="border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-4 py-2 flex items-center gap-2"
            >
              <Users class="w-4 h-4 text-gray-600" />
              <span class="text-brand-dark text-sm font-semibold"
                >View Team</span
              >
            </button>
          </div>

          <!-- Team Header -->
          <div
            class="flex items-center gap-4 mb-4 p-4 bg-gradient-to-br from-primary-500 to-primary-600 rounded-[16px]"
          >
            <div
              class="w-16 h-16 relative flex items-center justify-center rounded-[12px] overflow-hidden"
            >
              <div
                class="w-full h-full absolute bg-white/20 rounded-[12px]"
              ></div>
              <Code class="w-8 h-8 text-white relative z-10" />
            </div>
            <div class="flex-1">
              <h4 class="text-white text-xl font-bold">
                {{ resolvedProfile.team.name }}
              </h4>
              <p class="text-white/80 text-base font-normal">
                {{ resolvedProfile.team.members_count || 0 }} members •
                {{ capitalize(resolvedProfile.team.status) }}
              </p>
            </div>
            <div class="flex items-center gap-1">
              <Star class="w-4 h-4 text-white fill-white" />
              <Star class="w-4 h-4 text-white fill-white" />
              <Star class="w-4 h-4 text-white fill-white" />
              <Star class="w-4 h-4 text-white fill-white" />
              <Star class="w-4 h-4 text-white fill-white" />
            </div>
          </div>

          <!-- Team Details -->
          <div class="space-y-3">
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Team Lead</span>
              <div class="flex items-center gap-2">
                <span class="text-brand-dark text-base font-medium">{{
                  resolvedProfile.team.leader?.name || "-"
                }}</span>
              </div>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Department</span>
              <span class="text-brand-dark text-base font-medium">{{
                capitalize(resolvedProfile.team.department)
              }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-brand-light text-base">Team Size</span>
              <span class="text-brand-dark text-base font-medium"
                >{{ resolvedProfile.team.members_count || 0 }} members</span
              >
            </div>
          </div>
        </div>

        <!-- Latest 5 Tasks Assigned -->
        <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-5">
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
              <div
                class="w-12 h-12 bg-orange-50 rounded-[12px] flex items-center justify-center"
              >
                <ListChecks class="w-6 h-6 text-orange-600" />
              </div>
              <div>
                <h3 class="text-brand-dark text-lg font-bold">Latest Tasks</h3>
                <p class="text-brand-light text-sm">Recently assigned tasks</p>
              </div>
            </div>
            <button class="text-[#0C51D9] text-sm font-medium hover:underline">
              View All
            </button>
          </div>

          <div class="space-y-4">
            <div
              v-for="task in latestTasks"
              :key="task.id"
              class="border border-[#DCDEDD] rounded-[12px] p-4 hover:border-[#0C51D9] hover:border-2 transition-all duration-300"
            >
              <div class="flex items-start justify-between mb-2">
                <h4 class="text-brand-dark text-base font-semibold">
                  {{ task.title }}
                </h4>
                <span
                  :class="task.statusClass"
                  class="px-2 py-1 rounded-md text-xs font-semibold flex-shrink-0"
                  >{{ task.status }}</span
                >
              </div>
              <p class="text-brand-light text-sm mb-3">
                {{ task.description }}
              </p>
              <div class="flex items-center gap-4 text-xs text-gray-600">
                <div class="flex items-center gap-1">
                  <Calendar class="w-3 h-3" />
                  <span>{{ task.dueDate }}</span>
                </div>
                <div class="flex items-center gap-1">
                  <Folder class="w-3 h-3" />
                  <span>{{ task.project }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div v-else class="bg-white border border-[#DCDEDD] rounded-[20px] p-8 text-center text-gray-600">
    Your employee profile is not available yet.
  </div>
</template>
