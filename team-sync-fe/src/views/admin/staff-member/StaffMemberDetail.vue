<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import StatusBadge from "@/components/common/StatusBadge.vue";
import { formatDateLong as formatDate } from "@/utils/dateUtils.js";
import {
  formatRupiah as formatCurrency,
  capitalize,
  getJobStatusText,
} from "@/utils/formatUtils.js";
import ConfirmationModal from "@/components/common/ConfirmationModal.vue";
import AnimatedValue from "@/components/common/AnimatedValue.vue";
import { useToast } from "@/composables/useToast";
import { can } from "@/helpers/permissionHelper";

const toast = useToast();
import { useRoute, useRouter } from "vue-router";
import { useStaffMemberStore } from "@/stores/staffMember";
import { storeToRefs } from "pinia";
import {
  Edit,
  Share2,
  CheckCircle,
  CalendarCheck,
  Folder,
  TrendingUp,
  Users,
  Contact,
  Calendar,
  Phone,
  MapPin,
  Briefcase,
  FileText,
  Download,
  Trash2,
  Building,
  User,
  Clock,
  AlertTriangle,
} from "lucide-vue-next";

const route = useRoute();
const router = useRouter();
const staffMemberStore = useStaffMemberStore();
const { loading, performanceStatistics, success } = storeToRefs(staffMemberStore);

const staffMember = ref<any>(null);
const showDeleteModal = ref(false);

// Load staff member data
const loadStaffMember = async () => {
  try {
    const staffMemberId = route.params.id as string;
    staffMember.value = await staffMemberStore.fetchStaffMember(staffMemberId);
    // Load performance statistics
    await staffMemberStore.fetchPerformanceStatistics(staffMemberId);
  } catch (error) {
    console.error("Error loading staff member:", error);
    router.push({ name: "admin.staffMembers" });
  }
};


const statusText = computed(() => {
  return getJobStatusText(staffMember.value?.job_information?.status);
});

// Actions
const editStaffMember = () => {
  router.push({
    name: "admin.staffMembers.edit",
    params: { id: route.params.id },
  });
};

const shareProfile = () => {
  const url = window.location.href;
  navigator.clipboard.writeText(url);
  toast.success("Link Copied", "Profile link copied to clipboard!");
};

const backupStaffMember = () => {
  if (
    confirm(
      `Create backup for ${staffMember.value?.user?.name}? This will download all staff member data.`
    )
  ) {
    toast.info("Coming Soon", "Backup feature will be implemented soon.");
  }
};

const handleDeleteStaffMember = async () => {
  try {
    await staffMemberStore.deleteStaffMember(route.params.id as string);
    if (success.value) {
      showDeleteModal.value = false;
      router.push({ name: "admin.staffMembers" });
    }
  } catch (error) {}
};

onMounted(() => {
  loadStaffMember();
});
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center min-h-screen">
    <div class="text-center">
      <div
        class="w-16 h-16 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"
      ></div>
      <p class="text-brand-dark text-lg font-medium">
        Loading staff member data...
      </p>
    </div>
  </div>

  <div v-else-if="staffMember">
    <!-- Staff Member Header -->
    <div class="bg-white border border-[#DCDEDD] rounded-[20px] mb-6 p-6">
      <div class="flex items-center gap-6">
        <div class="relative">
          <img
            :src="staffMember.user?.profile_photo"
            v-if="staffMember.user?.profile_photo"
            alt="Staff Member"
            class="w-32 h-32 rounded-full object-cover"
          />
          <div
            v-else
            class="w-32 h-32 rounded-full bg-gray-100 flex items-center justify-center"
          >
            <User class="w-16 h-16 text-gray-400" />
          </div>
          <StatusBadge
            v-if="statusText"
            type="status"
            :value="statusText"
            class="absolute bottom-0 left-1/2 transform -translate-x-1/2 translate-y-1/2 rounded-full !px-3 shadow-sm"
          />
        </div>
        <div class="flex-1">
          <div class="flex items-center gap-4 mb-2">
            <h1 class="text-brand-dark text-3xl font-extrabold">
              {{ staffMember.user?.name }}
            </h1>
          </div>
          <p class="text-brand-light text-lg mb-3">
            {{ staffMember.job_information?.job_title }}
          </p>
          <div class="flex items-center gap-6 text-base text-gray-600">
            <div class="flex items-center gap-2">
              <Building class="w-4 h-4" />
              <span>{{
                capitalize(staffMember.job_information?.work_location)
              }}</span>
            </div>
            <div class="flex items-center gap-2">
              <User class="w-4 h-4" />
              <span>{{ staffMember.code }}</span>
            </div>
            <div class="flex items-center gap-2">
              <Calendar class="w-4 h-4" />
              <span
                >Joined
                {{ formatDate(staffMember.job_information?.start_date) }}</span
              >
            </div>
          </div>
        </div>
        <div class="flex gap-3">
          <button
            v-if="can('staff-member-edit')"
            @click="editStaffMember"
            class="btn-primary rounded-[8px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-6 py-3 flex items-center gap-2"
          >
            <Edit class="w-4 h-4 text-white" />
            <span class="text-brand-white text-sm font-semibold"
              >Edit Profile</span
            >
          </button>
          <button
            @click="shareProfile"
            class="bg-white border border-[#DCDEDD] text-brand-dark py-3 px-6 rounded-[8px] font-medium hover:bg-gray-50 transition-colors flex items-center gap-2"
          >
            <Share2 class="w-4 h-4" />
            Share Profile
          </button>
        </div>
      </div>
    </div>

    <!-- Performance Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div
        class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-brand-dark text-base font-medium">Tasks Completed</p>
            <p
              class="text-brand-dark text-3xl font-extrabold leading-tight my-2"
            >
              <template v-if="loading">...</template><AnimatedValue v-else :value="performanceStatistics.tasks_completed" />
            </p>
            <p class="text-success text-base font-medium">This month</p>
          </div>
          <div
            class="w-12 h-12 bg-blue-50 rounded-[16px] flex items-center justify-center"
          >
            <CheckCircle class="w-6 h-6 text-blue-600" />
          </div>
        </div>
      </div>
      <div
        class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-brand-dark text-base font-medium">Attendance Rate</p>
            <p
              class="text-brand-dark text-3xl font-extrabold leading-tight my-2"
            >
              <template v-if="loading">...</template><AnimatedValue v-else :value="performanceStatistics.attendance_rate" suffix="%" />
            </p>
            <p class="text-success text-base font-medium">
              {{
                performanceStatistics.attendance_rate >= 80
                  ? "Above average"
                  : "Below average"
              }}
            </p>
          </div>
          <div
            class="w-12 h-12 bg-green-50 rounded-[16px] flex items-center justify-center"
          >
            <CalendarCheck class="w-6 h-6 text-green-600" />
          </div>
        </div>
      </div>
      <div
        class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-brand-dark text-base font-medium">Projects</p>
            <p
              class="text-brand-dark text-3xl font-extrabold leading-tight my-2"
            >
              <template v-if="loading">...</template><AnimatedValue v-else :value="performanceStatistics.projects_count" />
            </p>
            <p class="text-success text-base font-medium">Active projects</p>
          </div>
          <div
            class="w-12 h-12 bg-purple-50 rounded-[16px] flex items-center justify-center"
          >
            <Folder class="w-6 h-6 text-purple-600" />
          </div>
        </div>
      </div>
      <div
        class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-5"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-brand-dark text-base font-medium">Performance</p>
            <p
              class="text-brand-dark text-3xl font-extrabold leading-tight my-2"
            >
              <template v-if="loading">...</template><AnimatedValue v-else :value="performanceStatistics.performance_score" suffix="%" />
            </p>
            <p
              :class="
                performanceStatistics.performance_score >= 80
                  ? 'text-success'
                  : performanceStatistics.performance_score >= 60
                  ? 'text-warning'
                  : 'text-danger'
              "
              class="text-base font-medium"
            >
              {{
                performanceStatistics.performance_score >= 80
                  ? "Excellent rating"
                  : performanceStatistics.performance_score >= 60
                  ? "Good rating"
                  : "Needs improvement"
              }}
            </p>
          </div>
          <div
            class="w-12 h-12 bg-orange-50 rounded-[16px] flex items-center justify-center"
          >
            <TrendingUp class="w-6 h-6 text-orange-600" />
          </div>
        </div>
      </div>
    </div>

    <!-- Information Cards Row 1 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <!-- Team Information -->
      <div class="bg-white border border-[#DCDEDD] rounded-[16px] p-6">
        <div class="flex items-center gap-3 mb-4">
          <div
            class="w-12 h-12 bg-indigo-50 rounded-[12px] flex items-center justify-center"
          >
            <Users class="w-6 h-6 text-indigo-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-lg font-bold">Team Information</h3>
            <p class="text-brand-light text-sm">
              Current team and reporting structure
            </p>
          </div>
        </div>
        <div class="space-y-4">
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Team</span>
            <span class="text-brand-dark text-base font-medium">
              {{ staffMember.job_information?.team?.name || "-" }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Team Members</span>
            <span class="text-brand-dark text-base font-medium">
              {{ staffMember.job_information?.team?.members_count || 0 }} members
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Team Status</span>
            <span class="text-brand-dark text-base font-medium">
              {{ capitalize(staffMember.job_information?.team?.status) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Contact Details -->
      <div class="bg-white border border-[#DCDEDD] rounded-[16px] p-6">
        <div class="flex items-center gap-3 mb-4">
          <div
            class="w-12 h-12 bg-teal-50 rounded-[12px] flex items-center justify-center"
          >
            <Contact class="w-6 h-6 text-teal-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-lg font-bold">Contact Details</h3>
            <p class="text-brand-light text-sm">How to reach this staff member</p>
          </div>
        </div>
        <div class="space-y-4">
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Email</span>
            <span class="text-brand-dark text-base font-medium">
              {{ staffMember.user?.email }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Phone</span>
            <span class="text-brand-dark text-base font-medium">
              {{ staffMember.phone }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Identity Number</span>
            <span class="text-brand-dark text-base font-medium">
              {{ staffMember.identity_number }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Information Cards Row 2 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <!-- Personal Information -->
      <div class="bg-white border border-[#DCDEDD] rounded-[16px] p-6">
        <div class="flex items-center gap-3 mb-4">
          <div
            class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center"
          >
            <Calendar class="w-6 h-6 text-blue-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-lg font-bold">
              Personal Information
            </h3>
            <p class="text-brand-light text-sm">Birth and personal details</p>
          </div>
        </div>
        <div class="space-y-4">
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Date of Birth</span>
            <span class="text-brand-dark text-base font-medium">
              {{ formatDate(staffMember.date_of_birth) }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Place of Birth</span>
            <span class="text-brand-dark text-base font-medium">
              {{ staffMember.place_of_birth }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Gender</span>
            <span class="text-brand-dark text-base font-medium">
              {{ capitalize(staffMember.gender) }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Religion</span>
            <span class="text-brand-dark text-base font-medium">
              {{ capitalize(staffMember.religion) || "-" }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Marital Status</span>
            <span class="text-brand-dark text-base font-medium">
              {{ capitalize(staffMember.marital_status) || "-" }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Blood Type</span>
            <span class="text-brand-dark text-base font-medium">
              {{ staffMember.blood_type || "-" }}
            </span>
          </div>
        </div>
      </div>

      <!-- Emergency Contact -->
      <div class="bg-white border border-[#DCDEDD] rounded-[16px] p-6">
        <div class="flex items-center gap-3 mb-4">
          <div
            class="w-12 h-12 bg-red-50 rounded-[12px] flex items-center justify-center"
          >
            <Phone class="w-6 h-6 text-red-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-lg font-bold">Emergency Contact</h3>
            <p class="text-brand-light text-sm">
              Person to contact in emergency
            </p>
          </div>
        </div>
        <div
          class="space-y-4"
          v-if="
            staffMember.emergency_contacts &&
            staffMember.emergency_contacts.length > 0
          "
        >
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Contact Name</span>
            <span class="text-brand-dark text-base font-medium">
              {{ staffMember.emergency_contacts[0].full_name }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Relationship</span>
            <span class="text-brand-dark text-base font-medium">
              {{ capitalize(staffMember.emergency_contacts[0].relationship) }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Phone</span>
            <span class="text-brand-dark text-base font-medium">
              {{ staffMember.emergency_contacts[0].phone }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Email</span>
            <span class="text-brand-dark text-base font-medium">
              {{ staffMember.emergency_contacts[0].email || "-" }}
            </span>
          </div>
        </div>
        <div v-else class="text-center py-4">
          <p class="text-brand-light text-sm">
            No emergency contact information
          </p>
        </div>
      </div>
    </div>

    <!-- Information Cards Row 3 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <!-- Address Information -->
      <div class="bg-white border border-[#DCDEDD] rounded-[16px] p-6">
        <div class="flex items-center gap-3 mb-4">
          <div
            class="w-12 h-12 bg-purple-50 rounded-[12px] flex items-center justify-center"
          >
            <MapPin class="w-6 h-6 text-purple-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-lg font-bold">
              Address Information
            </h3>
            <p class="text-brand-light text-sm">Location and postal details</p>
          </div>
        </div>
        <div class="space-y-4">
          <div class="flex justify-between items-start">
            <span class="text-brand-light text-base">Address</span>
            <span
              class="text-brand-dark text-base font-medium text-right max-w-[60%]"
            >
              {{ staffMember.address }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">City</span>
            <span class="text-brand-dark text-base font-medium">
              {{ staffMember.city }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Post Code</span>
            <span class="text-brand-dark text-base font-medium">
              {{ staffMember.postal_code }}
            </span>
          </div>
        </div>
      </div>

      <!-- Employment Details -->
      <div class="bg-white border border-[#DCDEDD] rounded-[16px] p-6">
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
            <p class="text-brand-light text-sm">
              Work arrangement and compensation
            </p>
          </div>
        </div>
        <div class="space-y-4">
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">NPWP</span>
            <span class="text-brand-dark text-base font-medium">
              {{ staffMember.npwp || "-" }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">BPJS Ketenagakerjaan</span>
            <span class="text-brand-dark text-base font-medium">
              {{ staffMember.bpjs_ketenagakerjaan || "-" }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">BPJS Kesehatan</span>
            <span class="text-brand-dark text-base font-medium">
              {{ staffMember.bpjs_kesehatan || "-" }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">PTKP Status</span>
            <span class="text-brand-dark text-base font-medium">
              {{ staffMember.ptkp_status || "-" }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Employment Type</span>
            <span class="text-brand-dark text-base font-medium">
              {{ capitalize(staffMember.job_information?.employment_type) }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Start Date</span>
            <span class="text-brand-dark text-base font-medium">
              {{ formatDate(staffMember.job_information?.start_date) }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Monthly Salary</span>
            <span class="text-brand-dark text-base font-medium">
              {{ formatCurrency(staffMember.job_information?.monthly_salary) }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Information Cards Row 4 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <!-- Administrative Information -->
      <div class="bg-white border border-[#DCDEDD] rounded-[16px] p-6">
        <div class="flex items-center gap-3 mb-4">
          <div
            class="w-12 h-12 bg-orange-50 rounded-[12px] flex items-center justify-center"
          >
            <FileText class="w-6 h-6 text-orange-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-lg font-bold">
              Administrative Information
            </h3>
            <p class="text-brand-light text-sm">
              System details and preferences
            </p>
          </div>
        </div>
        <div class="space-y-4">
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Staff Member ID</span>
            <span class="text-brand-dark text-base font-medium">
              {{ staffMember.code }}
            </span>
          </div>
        </div>
      </div>

      <!-- Bank Information -->
      <div class="bg-white border border-[#DCDEDD] rounded-[16px] p-6">
        <div class="flex items-center gap-3 mb-4">
          <div
            class="w-12 h-12 bg-cyan-50 rounded-[12px] flex items-center justify-center"
          >
            <Briefcase class="w-6 h-6 text-cyan-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-lg font-bold">Bank Information</h3>
            <p class="text-brand-light text-sm">Banking details for payroll</p>
          </div>
        </div>
        <div class="space-y-4">
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Bank Name</span>
            <span class="text-brand-dark text-base font-medium">
              {{ capitalize(staffMember.bank_information?.bank_name) }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Account Number</span>
            <span class="text-brand-dark text-base font-medium">
              {{ staffMember.bank_information?.account_number }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-brand-light text-base">Account Holder</span>
            <span class="text-brand-dark text-base font-medium">
              {{ staffMember.bank_information?.account_holder_name }}
            </span>
          </div>

        </div>
      </div>
    </div>

    <!-- Danger Zone (only for users with delete permission) -->
    <div v-if="can('staff-member-delete')" class="bg-white border border-[#FEE2E2] rounded-[16px] p-6">
      <div class="flex items-center gap-3 mb-6">
        <div
          class="w-12 h-12 bg-red-50 rounded-[12px] flex items-center justify-center"
        >
          <AlertTriangle class="w-6 h-6 text-red-600" />
        </div>
        <div>
          <h3 class="text-brand-dark text-lg font-bold">Danger Zone</h3>
          <p class="text-brand-light text-sm">
            Irreversible and destructive actions
          </p>
        </div>
      </div>
      <div
        class="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center p-4 bg-red-50 rounded-[12px]"
      >
        <div class="flex-1">
          <h4 class="text-brand-dark text-base font-bold mb-1">
            Delete Staff Member Profile
          </h4>
          <p class="text-brand-light text-sm">
            Permanently remove this staff member and all associated data. This
            action cannot be undone.
          </p>
        </div>
        <div class="flex gap-3">
          <button
            @click="backupStaffMember"
            class="bg-white border border-[#DCDEDD] text-brand-dark py-3 px-6 rounded-[8px] font-medium hover:bg-gray-50 transition-colors flex items-center gap-2"
          >
            <Download class="w-4 h-4" />
            Backup Data
          </button>
          <button
            @click="showDeleteModal = true"
            class="bg-red-600 border border-red-700 text-white py-3 px-6 rounded-[8px] font-semibold hover:bg-red-700 transition-colors flex items-center gap-2"
          >
            <Trash2 class="w-4 h-4" />
            Delete Staff Member
          </button>
        </div>
      </div>
    </div>
    <ConfirmationModal
      :show="showDeleteModal"
      title="Delete Staff Member"
      :message="`Are you sure you want to delete '${staffMember.user?.name}'? This action cannot be undone.`"
      confirmText="Delete Staff Member"
      cancelText="Cancel"
      type="danger"
      :loading="loading"
      @confirm="handleDeleteStaffMember"
      @cancel="showDeleteModal = false"
    />
  </div>
</template>
