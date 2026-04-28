<script setup lang="ts">
import { ref, inject } from "vue";
import { useRouter } from "vue-router";
import { useStaffMemberStore } from "@/stores/staffMember";
import { storeToRefs } from "pinia";
import { ArrowRight, ArrowLeft, UserPlus } from "lucide-vue-next";
import { useToast } from "@/composables/useToast";

import Step1PersonalInfo from "@/components/admin/staff-member/create/steps/Step1PersonalInfo.vue";
import Step2JobInfo from "@/components/admin/staff-member/create/steps/Step2JobInfo.vue";
import Step3EmergencyContact from "@/components/admin/staff-member/create/steps/Step3EmergencyContact.vue";
import Step4Preview from "@/components/admin/staff-member/create/steps/Step4Preview.vue";
import ErrorModal from "@/components/admin/staff-member/create/ErrorModal.vue";

const router = useRouter();
const staffMemberStore = useStaffMemberStore();
const toast = useToast();
const { loading, error } = storeToRefs(staffMemberStore);

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
  gender: "",
  religion: "",
  marital_status: "",
  blood_type: "",
  place_of_birth: "",
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
  status: "",
  employment_type: "",
  work_location: "",
  start_date: "",
  monthly_salary: "",
  bank_name: "",
  account_number: "",
  account_holder_name: "",
  npwp: "",
  bpjs_ketenagakerjaan: "",
  bpjs_kesehatan: "",
  ptkp_status: "",
  role: "",
});

// Form data for Step 3
const step3Data = ref({
  emergency_contact_name: "",
  emergency_contact_relationship: "",
  emergency_contact_phone: "",
  emergency_contact_email: "",
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

const appendIfNotEmpty = (formData: FormData, key: string, value: any) => {
  if (value !== null && value !== undefined && String(value).trim() !== "") {
    formData.append(key, String(value));
  }
};

const requiredFieldError = "This field is required.";

const getErrorMap = () => {
  if (!staffMemberStore.error || typeof staffMemberStore.error !== "object") {
    return {} as Record<string, string[]>;
  }

  return { ...(staffMemberStore.error as Record<string, string[]>) };
};

const setFieldError = (field: string, message: string) => {
  const nextErrors = getErrorMap();
  nextErrors[field] = [message];
  staffMemberStore.error = nextErrors;
};

const clearFieldError = (field: string) => {
  if (!staffMemberStore.error || typeof staffMemberStore.error !== "object") return;

  const nextErrors = getErrorMap();
  delete nextErrors[field];
  staffMemberStore.error = Object.keys(nextErrors).length ? nextErrors : null;
};

const applyAvailabilityErrors = (errors: Record<string, string[]>) => {
  const nextErrors = getErrorMap();

  if (errors?.email) {
    nextErrors.email = errors.email;
  } else {
    delete nextErrors.email;
  }

  if (errors?.identity_number) {
    nextErrors.identity_number = errors.identity_number;
  } else {
    delete nextErrors.identity_number;
  }

  staffMemberStore.error = Object.keys(nextErrors).length ? nextErrors : null;
};

const checkAvailabilityRealtime = async (
  payload: Record<string, string>,
  field: "email" | "identity_number",
) => {
  const value = String(payload[field] ?? "").trim();

  if (!value) {
    setFieldError(field, requiredFieldError);
    return;
  }

  if (field === "email") {
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(value)) {
      setFieldError("email", "Please enter a valid email address.");
      return;
    }
  }

  clearFieldError(field);

  try {
    await staffMemberStore.checkAvailability(payload);
    applyAvailabilityErrors({});
  } catch (err: any) {
    const availabilityErrors = err?.response?.data?.errors || {};
    applyAvailabilityErrors(availabilityErrors);
  }
};

const handleEmailBlur = async (value: string) => {
  await checkAvailabilityRealtime({ email: value }, "email");
};

const handleIdentityNumberBlur = async (value: string) => {
  await checkAvailabilityRealtime(
    { identity_number: value },
    "identity_number",
  );
};

const validateStep1 = async () => {
  const validationErrors: Record<string, string[]> = {};

  const step1RequiredFields = [
    "name",
    "email",
    "password",
    "password_confirmation",
    "identity_number",
    "phone",
    "date_of_birth",
    "place_of_birth",
    "gender",
    "address",
    "city",
    "postal_code",
  ] as const;

  for (const field of step1RequiredFields) {
    if (!String(step1Data.value[field] ?? "").trim()) {
      validationErrors[field] = [requiredFieldError];
    }
  }

  if (
    step1Data.value.password &&
    step1Data.value.password_confirmation &&
    step1Data.value.password !== step1Data.value.password_confirmation
  ) {
    validationErrors.password_confirmation = [
      "Password confirmation does not match.",
    ];
  }

  if (Object.keys(validationErrors).length > 0) {
    staffMemberStore.error = validationErrors;
    return false;
  }

  try {
    await staffMemberStore.checkAvailability({
      email: step1Data.value.email,
      identity_number: step1Data.value.identity_number,
    });
    applyAvailabilityErrors({});
  } catch (err: any) {
    const availabilityErrors = err?.response?.data?.errors || {};
    applyAvailabilityErrors(availabilityErrors);
    return false;
  }

  staffMemberStore.error = null;
  return true;
};

const validateStep2 = () => {
  const validationErrors: Record<string, string[]> = {};
  const step2RequiredChecks: Array<[string, string]> = [
    ["job_title", step2Data.value.job_title],
    ["status", step2Data.value.status],
    ["employment_type", step2Data.value.employment_type],
    ["work_location", step2Data.value.work_location],
    ["start_date", step2Data.value.start_date],
    ["monthly_salary", step2Data.value.monthly_salary],
    ["bank_name", step2Data.value.bank_name],
    ["account_number", step2Data.value.account_number],
    ["account_holder_name", step2Data.value.account_holder_name],
  ];

  for (const [field, value] of step2RequiredChecks) {
    if (!String(value ?? "").trim()) {
      validationErrors[field] = [requiredFieldError];
    }
  }

  if (!String(step2Data.value.role ?? "").trim()) {
    validationErrors.roles = [requiredFieldError];
  }

  if (Object.keys(validationErrors).length > 0) {
    staffMemberStore.error = validationErrors;
    return false;
  }

  staffMemberStore.error = null;
  return true;
};

const validateStep3 = () => {
  const validationErrors: Record<string, string[]> = {};

  if (!String(step3Data.value.emergency_contact_name ?? "").trim()) {
    validationErrors["emergency_contacts.0.full_name"] = [requiredFieldError];
  }

  if (!String(step3Data.value.emergency_contact_relationship ?? "").trim()) {
    validationErrors["emergency_contacts.0.relationship"] = [
      requiredFieldError,
    ];
  }

  if (!String(step3Data.value.emergency_contact_phone ?? "").trim()) {
    validationErrors["emergency_contacts.0.phone"] = [requiredFieldError];
  }

  if (Object.keys(validationErrors).length > 0) {
    staffMemberStore.error = validationErrors;
    return false;
  }

  staffMemberStore.error = null;
  return true;
};

const handleNextStep = async () => {
  if (currentStep?.value === 1) {
    const isValid = await validateStep1();
    if (!isValid) return;
  }

  if (currentStep?.value === 2) {
    const isValid = validateStep2();
    if (!isValid) return;
  }

  if (currentStep?.value === 3) {
    const isValid = validateStep3();
    if (!isValid) return;
  }

  nextStep?.();
};

const goToErrorStep = (validationErrors: Record<string, any>) => {
  if (!currentStep || !validationErrors) return;

  const keys = Object.keys(validationErrors);

  const step1Fields = [
    "name",
    "email",
    "password",
    "identity_number",
    "phone",
    "date_of_birth",
    "gender",
    "place_of_birth",
    "address",
    "city",
    "postal_code",
    "profile_photo",
  ];
  const step2Fields = [
    "roles",
    "roles.0",
    "job_title",
    "employment_type",
    "work_location",
    "status",
    "bank_name",
    "account_number",
    "account_holder_name",
  ];

  if (keys.some((key) => step1Fields.includes(key))) {
    currentStep.value = 1;
    return;
  }

  if (keys.some((key) => step2Fields.includes(key))) {
    currentStep.value = 2;
    return;
  }

  if (keys.some((key) => key.startsWith("emergency_contacts"))) {
    currentStep.value = 3;
  }
};

// Form submission
const handleSubmit = async () => {
  try {
    const formData = new FormData();

    // Step 1 data (User & Employee Profile)
    formData.append("name", step1Data.value.name);
    formData.append("email", step1Data.value.email);
    formData.append("password", step1Data.value.password);
    formData.append("identity_number", step1Data.value.identity_number);
    formData.append("phone", step1Data.value.phone);
    formData.append("date_of_birth", step1Data.value.date_of_birth);
    appendIfNotEmpty(formData, "religion", step1Data.value.religion);
    appendIfNotEmpty(formData, "marital_status", step1Data.value.marital_status);
    appendIfNotEmpty(formData, "blood_type", step1Data.value.blood_type);
    formData.append("place_of_birth", step1Data.value.place_of_birth);
    formData.append("gender", step1Data.value.gender);
    formData.append("address", step1Data.value.address);
    formData.append("city", step1Data.value.city);
    formData.append("postal_code", step1Data.value.postal_code);

    if (step1Data.value.profile_photo instanceof File) {
      formData.append("profile_photo", step1Data.value.profile_photo);
    }

    // Step 2 data (Job Information & Bank Information)
    formData.append("job_title", step2Data.value.job_title);
    appendIfNotEmpty(formData, "team_id", step2Data.value.team_id);
    formData.append("status", step2Data.value.status);
    formData.append("employment_type", step2Data.value.employment_type);
    formData.append("work_location", step2Data.value.work_location);
    formData.append("start_date", step2Data.value.start_date);
    formData.append(
      "monthly_salary",
      normalizeRupiah(step2Data.value.monthly_salary),
    );
    formData.append("bank_name", step2Data.value.bank_name);
    formData.append("account_number", step2Data.value.account_number);
    formData.append("account_holder_name", step2Data.value.account_holder_name);
    appendIfNotEmpty(formData, "npwp", step2Data.value.npwp);
    appendIfNotEmpty(formData, "bpjs_ketenagakerjaan", step2Data.value.bpjs_ketenagakerjaan);
    appendIfNotEmpty(formData, "bpjs_kesehatan", step2Data.value.bpjs_kesehatan);
    appendIfNotEmpty(formData, "ptkp_status", step2Data.value.ptkp_status);
    if (step2Data.value.role) {
      formData.append("roles[]", step2Data.value.role);
    }

    // Step 3 data (Emergency Contacts & Additional Info)
    // Emergency contacts as array (required format by API)
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

    await staffMemberStore.createStaffMember(formData);

    // Redirect to success page on success
    router.push({ name: "admin.staffMembers.success" });
  } catch (err) {
    toast.error(
      "Failed to create staff member",
      error.value ||
        err?.response?.data?.message ||
        "Failed to create staff member.",
    );
    const validationErrors = err?.response?.data?.errors;
    if (validationErrors) {
      toast.error(
        "Failed to create staff member",
        error.value ||
          err?.response?.data?.message ||
          "Failed to create staff member.",
      );
      goToErrorStep(validationErrors);
    }
    // Show error modal when validation fails
    if (error.value) {
      showErrorModal.value = true;
    }
  }
};

const closeErrorModal = () => {
  showErrorModal.value = false;
};
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-6">
    <!-- Step 1: Personal Information -->
    <Step1PersonalInfo
      v-if="currentStep === 1"
      v-model="step1Data"
      :errors="error"
      :is-edit-mode="false"
      @email-blur="handleEmailBlur"
      @identity-number-blur="handleIdentityNumberBlur"
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
                ? "Ready to add this staff member?"
                : `Step ${currentStep} of ${totalSteps}`
            }}
          </p>
          <p class="text-brand-light text-xs font-normal mt-1">
            {{
              currentStep === 4
                ? "Review and confirm all information before submitting"
                : currentStep === 1
                  ? "Fill in the personal information"
                  : currentStep === 2
                    ? "Fill in the job information"
                    : "Fill in the emergency contact information"
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
            @click="previousStep"
            class="w-full sm:w-auto justify-center border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-4 sm:px-6 py-3 flex items-center gap-2"
          >
            <span class="text-brand-dark text-sm sm:text-base font-semibold">Cancel</span>
          </button>

          <button
            v-if="currentStep < totalSteps"
            type="button"
            @click="handleNextStep"
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
            <UserPlus class="w-4 h-4 text-white" />
            <span class="text-brand-white text-sm sm:text-base font-semibold whitespace-nowrap">
              {{ loading ? "Adding..." : "Add Staff Member" }}
            </span>
          </button>
        </div>
      </div>
    </div>
  </form>

  <!-- Error Modal -->
  <ErrorModal :show="showErrorModal" @close="closeErrorModal" />
</template>
