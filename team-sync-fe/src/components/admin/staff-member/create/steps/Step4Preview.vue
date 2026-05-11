<script setup lang="ts">
import { User, Briefcase, Phone, CreditCard, CheckCircle } from "lucide-vue-next";
import { DEFAULT_AVATAR } from "@/helpers/format";
import { parseSalaryNumber } from "@/utils/salaryUtils";

interface Props {
    step1Data: any;
    step2Data: any;
    step3Data: any;
}

defineProps<Props>();

// Format date to readable format
const formatDate = (date: string) => {
    if (!date) return "-";
    return new Date(date).toLocaleDateString("en-US", {
        year: "numeric",
        month: "long",
        day: "numeric",
    });
};

const formatCurrency = (amount: string) => {
    const parsed = parseSalaryNumber(amount);
    if (parsed === null) return "-";

    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(parsed);
};

// Format text with capitalization
const formatText = (text: string) => {
    if (!text) return "-";
    return text.charAt(0).toUpperCase() + text.slice(1).replace(/-/g, " ");
};
</script>

<template>
    <div class="space-y-6">
        <!-- Preview Header -->
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-[20px] p-6 text-white">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-12 h-12 bg-white/20 rounded-[12px] flex items-center justify-center">
                    <CheckCircle class="w-6 h-6 text-white" />
                </div>
                <div>
                    <h3 class="text-xl font-bold">Review Employee Information</h3>
                    <p class="text-blue-100 text-sm">Please review all information before submitting</p>
                </div>
            </div>
        </div>

        <!-- Personal Information -->
        <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center">
                    <User class="w-6 h-6 text-blue-600" />
                </div>
                <div>
                    <h3 class="text-brand-dark text-xl font-bold">Personal Information</h3>
                    <p class="text-brand-light text-sm font-normal">Employee basic details</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Profile Photo</p>
                    <div>
                        <img
                            :src="step1Data.profile_photo_url || DEFAULT_AVATAR"
                            alt="Profile Photo"
                            class="w-20 h-20 rounded-full object-cover border-2 border-[#DCDEDD]"
                        />
                    </div>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Full Name</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step1Data.name || "-" }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Email Address</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step1Data.email || "-" }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Identity Number</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step1Data.identity_number || "-" }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Phone Number</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step1Data.phone || "-" }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Gender</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ formatText(step1Data.gender) }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Date of Birth</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ formatDate(step1Data.date_of_birth) }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Place of Birth</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step1Data.place_of_birth || "-" }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Religion</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ formatText(step1Data.religion) || "-" }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Marital Status</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ formatText(step1Data.marital_status) || "-" }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Blood Type</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step1Data.blood_type || "-" }}
                    </p>
                </div>
                <div class="lg:col-span-2">
                    <p class="text-brand-light text-xs font-semibold mb-1">Address</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step1Data.address || "-" }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">City</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step1Data.city || "-" }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Postal Code</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step1Data.postal_code || "-" }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Job Information -->
        <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-12 h-12 bg-green-50 rounded-[12px] flex items-center justify-center">
                    <Briefcase class="w-6 h-6 text-green-600" />
                </div>
                <div>
                    <h3 class="text-brand-dark text-xl font-bold">Job Information</h3>
                    <p class="text-brand-light text-sm font-normal">Position and employment details</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Job Title</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step2Data.job_title || "-" }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Role</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step2Data.role || "-" }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Team</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step2Data.team_id || "-" }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Status</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ formatText(step2Data.status) }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Employment Type</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ formatText(step2Data.employment_type) }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Work Location</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ formatText(step2Data.work_location) }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Start Date</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ formatDate(step2Data.start_date) }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Monthly Salary</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ formatCurrency(step2Data.monthly_salary) }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">NPWP</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step2Data.npwp || "-" }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">PTKP Status</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step2Data.ptkp_status || "-" }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">BPJS Ketenagakerjaan</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step2Data.bpjs_ketenagakerjaan || "-" }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">BPJS Kesehatan</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step2Data.bpjs_kesehatan || "-" }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Bank Information -->
        <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-12 h-12 bg-purple-50 rounded-[12px] flex items-center justify-center">
                    <CreditCard class="w-6 h-6 text-purple-600" />
                </div>
                <div>
                    <h3 class="text-brand-dark text-xl font-bold">Bank Information</h3>
                    <p class="text-brand-light text-sm font-normal">Banking details for payroll</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Bank Name</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ formatText(step2Data.bank_name) }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Account Number</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step2Data.account_number || "-" }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Account Holder Name</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step2Data.account_holder_name || "-" }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Emergency Contact -->
        <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-12 h-12 bg-red-50 rounded-[12px] flex items-center justify-center">
                    <Phone class="w-6 h-6 text-red-600" />
                </div>
                <div>
                    <h3 class="text-brand-dark text-xl font-bold">Emergency Contact</h3>
                    <p class="text-brand-light text-sm font-normal">Person to contact in emergency</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Full Name</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step3Data.emergency_contact_name || "-" }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Relationship</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step3Data.emergency_contact_relationship || "-" }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Phone Number</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step3Data.emergency_contact_phone || "-" }}
                    </p>
                </div>
                <div>
                    <p class="text-brand-light text-xs font-semibold mb-1">Email Address</p>
                    <p class="text-brand-dark text-base font-semibold">
                        {{ step3Data.emergency_contact_email || "-" }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
