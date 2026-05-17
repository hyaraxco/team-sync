<script setup lang="ts">
import { ref, inject, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useStaffMemberStore } from "@/stores/staffMember";
import { storeToRefs } from "pinia";
import { ArrowRight, ArrowLeft, Save } from "lucide-vue-next";
import { useToast } from "@/composables/useToast";

import Step1PersonalInfo from "@/components/admin/staff-member/create/steps/Step1PersonalInfo.vue";
import Step2JobInfo from "@/components/admin/staff-member/create/steps/Step2JobInfo.vue";
import Step3EmergencyContact from "@/components/admin/staff-member/create/steps/Step3EmergencyContact.vue";
import Step4Preview from "@/components/admin/staff-member/create/steps/Step4Preview.vue";
import ErrorModal from "@/components/admin/staff-member/create/ErrorModal.vue";

const router = useRouter();
const route = useRoute();
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
    last_education: "",
    seniority_level: "",
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

// Load staff member data
const loadStaffMemberData = async () => {
    try {
        const staffMemberId = route.params.id as string;
        const staffMember = await staffMemberStore.fetchStaffMember(staffMemberId);

        if (staffMember) {
            const jobInformation = staffMember.job_information || {};
            const bankInformation = staffMember.bank_information || {};

            // Step 1 - Personal Information
            step1Data.value.name = staffMember.user?.name || "";
            step1Data.value.email = staffMember.user?.email || "";
            step1Data.value.identity_number = staffMember.identity_number || "";
            step1Data.value.phone = staffMember.phone || "";
            step1Data.value.date_of_birth = formatDateForInput(staffMember.date_of_birth);
            step1Data.value.religion = staffMember.religion || "";
            step1Data.value.marital_status = staffMember.marital_status || "";
            step1Data.value.blood_type = staffMember.blood_type || "";
            step1Data.value.place_of_birth = staffMember.place_of_birth || "";
            step1Data.value.gender = staffMember.gender || "";
            step1Data.value.address = staffMember.address || "";
            step1Data.value.city = staffMember.city || "";
            step1Data.value.postal_code = staffMember.postal_code || "";
            step1Data.value.last_education = staffMember.last_education || "";
            step1Data.value.seniority_level = staffMember.seniority_level || "";
            step1Data.value.profile_photo_url = staffMember.user?.profile_photo || "";

            // Step 2 - Job Information & Bank Information
            step2Data.value.job_title = jobInformation.job_title || "";
            step2Data.value.team_id = String(jobInformation.team?.id ?? staffMember.team?.id ?? "");
            step2Data.value.status = jobInformation.status || "";
            step2Data.value.employment_type = jobInformation.employment_type || "";
            step2Data.value.work_location = jobInformation.work_location || "";
            step2Data.value.start_date = formatDateForInput(jobInformation.start_date);
            step2Data.value.monthly_salary = String(jobInformation.monthly_salary ?? "");
            step2Data.value.bank_name = bankInformation.bank_name || "";
            step2Data.value.account_number = bankInformation.account_number || "";
            step2Data.value.account_holder_name = bankInformation.account_holder_name || "";
            step2Data.value.npwp = staffMember.npwp || "";
            step2Data.value.bpjs_ketenagakerjaan = staffMember.bpjs_ketenagakerjaan || "";
            step2Data.value.bpjs_kesehatan = staffMember.bpjs_kesehatan || "";
            step2Data.value.ptkp_status = staffMember.ptkp_status || "";
            step2Data.value.role = extractRoleValue(staffMember.user?.roles);

            // Reset emergency contact values before applying loaded data.
            step3Data.value.emergency_contact_name = "";
            step3Data.value.emergency_contact_relationship = "";
            step3Data.value.emergency_contact_phone = "";
            step3Data.value.emergency_contact_email = "";

            // Step 3 - Emergency Contact
            if (staffMember.emergency_contacts && staffMember.emergency_contacts.length > 0) {
                const contact = staffMember.emergency_contacts[0];
                step3Data.value.emergency_contact_name = contact.full_name || "";
                step3Data.value.emergency_contact_relationship = contact.relationship || "";
                step3Data.value.emergency_contact_phone = contact.phone || "";
                step3Data.value.emergency_contact_email = contact.email || "";
            }
        }
    } catch (err) {
        toast.error(
            "Failed to load staff member",
            staffMemberStore.error || err?.response?.data?.message || "Failed to load staff member data.",
        );
        router.push({ name: "admin.staffMembers" });
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
        if (step1Data.value.religion) formData.append("religion", step1Data.value.religion);
        if (step1Data.value.marital_status) formData.append("marital_status", step1Data.value.marital_status);
        if (step1Data.value.blood_type) formData.append("blood_type", step1Data.value.blood_type);
        formData.append("place_of_birth", step1Data.value.place_of_birth);
        formData.append("gender", step1Data.value.gender);
        formData.append("address", step1Data.value.address);
        formData.append("city", step1Data.value.city);
        formData.append("postal_code", step1Data.value.postal_code);
        if (step1Data.value.last_education) {
            formData.append("last_education", step1Data.value.last_education);
        }
        if (step1Data.value.seniority_level) {
            formData.append("seniority_level", step1Data.value.seniority_level);
        }

        if (step1Data.value.profile_photo) {
            formData.append("profile_photo", step1Data.value.profile_photo);
        }

        // Step 2 data (Job Information & Bank Information)
        formData.append("job_title", step2Data.value.job_title);
        formData.append("team_id", step2Data.value.team_id);
        formData.append("status", step2Data.value.status);
        formData.append("employment_type", step2Data.value.employment_type);
        formData.append("work_location", step2Data.value.work_location);
        formData.append("start_date", step2Data.value.start_date);
        formData.append("monthly_salary", normalizeRupiah(step2Data.value.monthly_salary));
        formData.append("bank_name", step2Data.value.bank_name);
        formData.append("account_number", step2Data.value.account_number);
        formData.append("account_holder_name", step2Data.value.account_holder_name);
        if (step2Data.value.npwp) formData.append("npwp", step2Data.value.npwp);
        if (step2Data.value.bpjs_ketenagakerjaan)
            formData.append("bpjs_ketenagakerjaan", step2Data.value.bpjs_ketenagakerjaan);
        if (step2Data.value.bpjs_kesehatan) formData.append("bpjs_kesehatan", step2Data.value.bpjs_kesehatan);
        if (step2Data.value.ptkp_status) formData.append("ptkp_status", step2Data.value.ptkp_status);
        formData.append("roles[]", step2Data.value.role);

        // Step 3 data (Emergency Contacts & Additional Info)
        formData.append("emergency_contacts[0][full_name]", step3Data.value.emergency_contact_name);
        formData.append("emergency_contacts[0][relationship]", step3Data.value.emergency_contact_relationship);
        formData.append("emergency_contacts[0][phone]", step3Data.value.emergency_contact_phone);
        if (step3Data.value.emergency_contact_email) {
            formData.append("emergency_contacts[0][email]", step3Data.value.emergency_contact_email);
        }

        const staffMemberId = route.params.id as string;
        await staffMemberStore.updateStaffMember(staffMemberId, formData);

        // Redirect to staff member list on success
        router.push({ name: "admin.staffMembers" });
    } catch (err) {
        toast.error(
            "Failed to update staff member",
            staffMemberStore.error || err?.response?.data?.message || "Failed to update staff member.",
        );
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
        loadStaffMemberData();
    },
    { immediate: true },
);
</script>

<template>
    <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- Step 1: Personal Information -->
        <Step1PersonalInfo v-if="currentStep === 1" v-model="step1Data" :errors="error" :is-edit-mode="true" />

        <!-- Step 2: Job Information -->
        <Step2JobInfo v-if="currentStep === 2" v-model="step2Data" :errors="error" />

        <!-- Step 3: Emergency Contact -->
        <Step3EmergencyContact v-if="currentStep === 3" v-model="step3Data" :errors="error" />

        <!-- Step 4: Preview -->
        <Step4Preview v-if="currentStep === 4" :step1Data="step1Data" :step2Data="step2Data" :step3Data="step3Data" />

        <!-- Form Navigation -->
        <div class="bg-white border border-brand-border rounded-2xl p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <p class="text-brand-dark text-sm font-medium">
                        {{
                            currentStep === 4
                                ? "Ready to update this staff member?"
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
                        class="w-full sm:w-auto justify-center border border-brand-border rounded-lg hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 px-4 sm:px-6 py-3 flex items-center gap-2"
                    >
                        <ArrowLeft class="w-4 h-4 text-gray-600" />
                        <span class="text-brand-dark text-sm sm:text-base font-semibold">Previous</span>
                    </button>
                    <button
                        v-else
                        type="button"
                        @click="router.push({ name: 'admin.staffMembers' })"
                        class="w-full sm:w-auto justify-center border border-brand-border rounded-lg hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 px-4 sm:px-6 py-3 flex items-center gap-2"
                    >
                        <span class="text-brand-dark text-sm sm:text-base font-semibold">Cancel</span>
                    </button>

                    <button
                        v-if="currentStep < totalSteps"
                        type="button"
                        @click="nextStep"
                        class="w-full sm:w-auto justify-center btn-primary rounded-lg hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 blue-gradient blue-btn-shadow px-4 sm:px-6 py-3 flex items-center gap-2"
                    >
                        <span class="text-brand-white text-sm sm:text-base font-semibold whitespace-nowrap">
                            <span class="lg:hidden">Next</span>
                            <span class="hidden lg:inline">
                                Next:
                                {{
                                    currentStep === 1 ? "Job Info" : currentStep === 2 ? "Emergency Contact" : "Review"
                                }}
                            </span>
                        </span>
                        <ArrowRight class="w-4 h-4 text-white" />
                    </button>
                    <button
                        v-else
                        type="submit"
                        :disabled="loading"
                        class="w-full sm:w-auto justify-center btn-primary rounded-lg hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 blue-gradient blue-btn-shadow px-4 sm:px-6 py-3 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <Save class="w-4 h-4 text-white" />
                        <span class="text-brand-white text-sm sm:text-base font-semibold whitespace-nowrap">
                            {{ loading ? "Updating..." : "Update Staff Member" }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Error Modal -->
    <ErrorModal :show="showErrorModal" @close="closeErrorModal" />
</template>
