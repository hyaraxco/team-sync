<script setup lang="ts">
import { ref, inject, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useEmployeeStore } from "@/stores/employee";
import { storeToRefs } from "pinia";
import { ArrowRight, ArrowLeft, Save } from "lucide-vue-next";

import Step1PersonalInfo from "@/components/admin/employee/create/steps/Step1PersonalInfo.vue";
import Step2JobInfo from "@/components/admin/employee/create/steps/Step2JobInfo.vue";
import Step3EmergencyContact from "@/components/admin/employee/create/steps/Step3EmergencyContact.vue";
import Step4Preview from "@/components/admin/employee/create/steps/Step4Preview.vue";
import ErrorModal from "@/components/admin/employee/create/ErrorModal.vue";

const router = useRouter();
const route = useRoute();
const employeeStore = useEmployeeStore();
const { loading, error } = storeToRefs(employeeStore);

// Modal state
const showErrorModal = ref(false);

// Inject step management from layout
const currentStep = inject<any>("currentStep");
const totalSteps = inject<any>("totalSteps");
const nextStep = inject<any>("nextStep");
const previousStep = inject<any>("previousStep");

// Form data for Step 1
const step1Data = ref({
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
  identity_number: "",
  phone: "",
  date_of_birth: "",
  hobby: "",
  place_of_birth: "",
  gender: "",
  address: "",
  city: "",
  postal_code: "",
  profile_photo: null as File | null,
  profile_photo_url: "",
});

// Form data for Step 2
const step2Data = ref({
  job_title: "",
  team_id: "",
  years_experience: "",
  status: "",
  employment_type: "",
  work_location: "",
  start_date: "",
  monthly_salary: "",
  skill_level: "",
  bank_name: "",
  account_number: "",
  account_holder_name: "",
  bank_branch: "",
  account_type: "",
  role: "",
});

// Form data for Step 3
const step3Data = ref({
  emergency_contact_name: "",
  emergency_contact_relationship: "",
  emergency_contact_phone: "",
  emergency_contact_email: "",
  additional_notes: "",
  preferred_language: "",
});

const parseSalaryNumber = (value: any): number | null => {
  if (value === null || value === undefined) return null;

  const raw = String(value).trim();
  if (!raw) return null;

  if (/^\d+\.\d{1,2}$/.test(raw)) {
    const parsed = Number(raw);
    return Number.isFinite(parsed) ? Math.trunc(parsed) : null;
  }

  if (/^\d{1,3}(\.\d{3})+(,\d+)?$/.test(raw)) {
    const parsed = Number(raw.replace(/\./g, "").replace(",", "."));
    return Number.isFinite(parsed) ? Math.trunc(parsed) : null;
  }

  const digits = raw.replace(/[^0-9]/g, "");
  if (!digits) return null;

  const parsed = parseInt(digits, 10);
  return Number.isFinite(parsed) ? parsed : null;
};

const normalizeRupiah = (value: any) => {
  const parsed = parseSalaryNumber(value);
  return parsed === null ? "" : String(parsed);
};

const formatDateForInput = (value: any) => {
  if (!value) return "";
  return String(value).slice(0, 10);
};

const extractRoleValue = (roles: any) => {
  if (!Array.isArray(roles) || roles.length === 0) return "";

  const firstRole = roles[0];
  if (typeof firstRole === "string") return firstRole;

  return firstRole?.name || "";
};

// Load employee data
const loadEmployeeData = async () => {
  try {
    const employeeId = route.params.id as string;
    const employee = await employeeStore.fetchEmployee(employeeId);

    if (employee) {
      const jobInformation = employee.job_information || {};
      const bankInformation = employee.bank_information || {};

      // Step 1 - Personal Information
      step1Data.value.name = employee.user?.name || "";
      step1Data.value.email = employee.user?.email || "";
      step1Data.value.identity_number = employee.identity_number || "";
      step1Data.value.phone = employee.phone || "";
      step1Data.value.date_of_birth = formatDateForInput(
        employee.date_of_birth,
      );
      step1Data.value.hobby = employee.hobby || "";
      step1Data.value.place_of_birth = employee.place_of_birth || "";
      step1Data.value.gender = employee.gender || "";
      step1Data.value.address = employee.address || "";
      step1Data.value.city = employee.city || "";
      step1Data.value.postal_code = employee.postal_code || "";
      step1Data.value.profile_photo_url = employee.user?.profile_photo || "";

      // Step 2 - Job Information & Bank Information
      step2Data.value.job_title = jobInformation.job_title || "";
      step2Data.value.team_id = String(
        jobInformation.team?.id ?? employee.team?.id ?? "",
      );
      step2Data.value.years_experience = String(
        jobInformation.years_experience ?? "",
      );
      step2Data.value.status = jobInformation.status || "";
      step2Data.value.employment_type = jobInformation.employment_type || "";
      step2Data.value.work_location = jobInformation.work_location || "";
      step2Data.value.start_date = formatDateForInput(
        jobInformation.start_date,
      );
      step2Data.value.monthly_salary = String(
        jobInformation.monthly_salary ?? "",
      );
      step2Data.value.skill_level = jobInformation.skill_level || "";
      step2Data.value.bank_name = bankInformation.bank_name || "";
      step2Data.value.account_number = bankInformation.account_number || "";
      step2Data.value.account_holder_name =
        bankInformation.account_holder_name || "";
      step2Data.value.bank_branch = bankInformation.bank_branch || "";
      step2Data.value.account_type = bankInformation.account_type || "";
      step2Data.value.role = extractRoleValue(employee.user?.roles);

      // Reset emergency contact values before applying loaded data.
      step3Data.value.emergency_contact_name = "";
      step3Data.value.emergency_contact_relationship = "";
      step3Data.value.emergency_contact_phone = "";
      step3Data.value.emergency_contact_email = "";

      // Step 3 - Emergency Contact
      if (
        employee.emergency_contacts &&
        employee.emergency_contacts.length > 0
      ) {
        const contact = employee.emergency_contacts[0];
        step3Data.value.emergency_contact_name = contact.full_name || "";
        step3Data.value.emergency_contact_relationship =
          contact.relationship || "";
        step3Data.value.emergency_contact_phone = contact.phone || "";
        step3Data.value.emergency_contact_email = contact.email || "";
      }
      step3Data.value.preferred_language = employee.preferred_language || "";
      step3Data.value.additional_notes = employee.additional_notes || "";
    }
  } catch (err) {
    console.error("Error loading employee data:", err);
    router.push({ name: "admin.employees" });
  }
};

// Form submission
const handleSubmit = async () => {
  try {
    const formData = new FormData();

    // Step 1 data (User & Employee Profile)
    formData.append("name", step1Data.value.name);
    formData.append("email", step1Data.value.email);

    // Only append password if it's changed
    if (step1Data.value.password) {
      formData.append("password", step1Data.value.password);
    }

    formData.append("identity_number", step1Data.value.identity_number);
    formData.append("phone", step1Data.value.phone);
    formData.append("date_of_birth", step1Data.value.date_of_birth);
    formData.append("hobby", step1Data.value.hobby);
    formData.append("place_of_birth", step1Data.value.place_of_birth);
    formData.append("gender", step1Data.value.gender);
    formData.append("address", step1Data.value.address);
    formData.append("city", step1Data.value.city);
    formData.append("postal_code", step1Data.value.postal_code);

    if (step1Data.value.profile_photo) {
      formData.append("profile_photo", step1Data.value.profile_photo);
    }

    // Step 2 data (Job Information & Bank Information)
    formData.append("job_title", step2Data.value.job_title);
    formData.append("team_id", step2Data.value.team_id);
    formData.append("years_experience", step2Data.value.years_experience);
    formData.append("status", step2Data.value.status);
    formData.append("employment_type", step2Data.value.employment_type);
    formData.append("work_location", step2Data.value.work_location);
    formData.append("start_date", step2Data.value.start_date);
    formData.append(
      "monthly_salary",
      normalizeRupiah(step2Data.value.monthly_salary),
    );
    formData.append("skill_level", step2Data.value.skill_level);
    formData.append("bank_name", step2Data.value.bank_name);
    formData.append("account_number", step2Data.value.account_number);
    formData.append("account_holder_name", step2Data.value.account_holder_name);
    formData.append("bank_branch", step2Data.value.bank_branch);
    formData.append("account_type", step2Data.value.account_type);
    formData.append("roles[]", step2Data.value.role);

    // Step 3 data (Emergency Contacts & Additional Info)
    formData.append(
      "emergency_contacts[0][full_name]",
      step3Data.value.emergency_contact_name,
    );
    formData.append(
      "emergency_contacts[0][relationship]",
      step3Data.value.emergency_contact_relationship,
    );
    formData.append(
      "emergency_contacts[0][phone]",
      step3Data.value.emergency_contact_phone,
    );
    if (step3Data.value.emergency_contact_email) {
      formData.append(
        "emergency_contacts[0][email]",
        step3Data.value.emergency_contact_email,
      );
    }

    formData.append("preferred_language", step3Data.value.preferred_language);
    formData.append("additional_notes", step3Data.value.additional_notes);

    const employeeId = route.params.id as string;
    await employeeStore.updateEmployee(employeeId, formData);

    // Redirect to employee list on success
    router.push({ name: "admin.employees" });
  } catch (err) {
    console.error("Error updating employee:", err);
    // Show error modal when validation fails
    if (error.value) {
      showErrorModal.value = true;
    }
  }
};

const closeErrorModal = () => {
  showErrorModal.value = false;
};

watch(
  () => route.params.id,
  () => {
    loadEmployeeData();
  },
  { immediate: true },
);
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-6">
    <!-- Step 1: Personal Information -->
    <Step1PersonalInfo
      v-if="currentStep === 1"
      v-model="step1Data"
      :errors="error"
      :is-edit-mode="true"
    />

    <!-- Step 2: Job Information -->
    <Step2JobInfo
      v-if="currentStep === 2"
      v-model="step2Data"
      :errors="error"
    />

    <!-- Step 3: Emergency Contact -->
    <Step3EmergencyContact
      v-if="currentStep === 3"
      v-model="step3Data"
      :errors="error"
    />

    <!-- Step 4: Preview -->
    <Step4Preview
      v-if="currentStep === 4"
      :step1Data="step1Data"
      :step2Data="step2Data"
      :step3Data="step3Data"
    />

    <!-- Form Navigation -->
    <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
          <p class="text-brand-dark text-sm font-medium">
            {{
              currentStep === 4
                ? "Ready to update this employee?"
                : `Step ${currentStep} of ${totalSteps}`
            }}
          </p>
          <p class="text-brand-light text-xs font-normal mt-1">
            {{
              currentStep === 4
                ? "Review and confirm all information before updating"
                : currentStep === 1
                  ? "Update the personal information"
                  : currentStep === 2
                    ? "Update the job information"
                    : "Update the emergency contact information"
            }}
          </p>
        </div>
        <div class="flex w-full flex-col gap-3 md:flex-row md:items-center lg:w-auto">
          <button
            v-if="currentStep > 1"
            type="button"
            @click="previousStep"
            class="w-full sm:w-auto justify-center border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-4 sm:px-6 py-3 flex items-center gap-2"
          >
            <ArrowLeft class="w-4 h-4 text-gray-600" />
            <span class="text-brand-dark text-sm sm:text-base font-semibold"
              >Previous</span
            >
          </button>
          <button
            v-else
            type="button"
            @click="router.push({ name: 'admin.employees' })"
            class="w-full sm:w-auto justify-center border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-4 sm:px-6 py-3 flex items-center gap-2"
          >
            <span class="text-brand-dark text-sm sm:text-base font-semibold">Cancel</span>
          </button>

          <button
            v-if="currentStep < totalSteps"
            type="button"
            @click="nextStep"
            class="w-full sm:w-auto justify-center btn-primary rounded-[8px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-4 sm:px-6 py-3 flex items-center gap-2"
          >
            <span class="text-brand-white text-sm sm:text-base font-semibold whitespace-nowrap">
              <span class="lg:hidden">Next</span>
              <span class="hidden lg:inline">
                Next:
                {{
                  currentStep === 1
                    ? "Job Info"
                    : currentStep === 2
                      ? "Emergency Contact"
                      : "Review"
                }}
              </span>
            </span>
            <ArrowRight class="w-4 h-4 text-white" />
          </button>
          <button
            v-else
            type="submit"
            :disabled="loading"
            class="w-full sm:w-auto justify-center btn-primary rounded-[8px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-4 sm:px-6 py-3 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <Save class="w-4 h-4 text-white" />
            <span class="text-brand-white text-sm sm:text-base font-semibold whitespace-nowrap">
              {{ loading ? "Updating..." : "Update Employee" }}
            </span>
          </button>
        </div>
      </div>
    </div>
  </form>

  <!-- Error Modal -->
  <ErrorModal :show="showErrorModal" @close="closeErrorModal" />
</template>
