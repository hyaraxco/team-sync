<script setup>
import { ref, onMounted, computed } from "vue";
import { RouterLink } from "vue-router";
import { useStaffMemberStore } from "@/stores/staffMember";
import { useAuthStore } from "@/stores/auth";
import { storeToRefs } from "pinia";
import { can } from "@/helpers/permissionHelper";
import { DEFAULT_AVATAR } from "@/helpers/format";
import StatusBadge from "@/components/common/StatusBadge.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import { formatDateLong as formatDate } from "@/utils/dateUtils.js";
import { formatRupiah as formatCurrency, capitalize } from "@/utils/formatUtils.js";
import { useToast } from "@/composables/useToast";
import { Edit, Contact, Briefcase, MapPin, Phone, Users, Calendar, Code, Star, Building, User } from "lucide-vue-next";

const staffMemberStore = useStaffMemberStore();
const authStore = useAuthStore();
const toast = useToast();
const { loading, performanceStatistics, error } = storeToRefs(staffMemberStore);

const profile = ref(null);
const authUser = computed(() => authStore.user);

const loadProfile = async () => {
    try {
        profile.value = await staffMemberStore.fetchMyProfile();
        if (profile.value?.id) {
            await staffMemberStore.fetchPerformanceStatistics(profile.value.id);
        }
    } catch (fetchError) {
        toast.error(
            "Failed to load profile",
            staffMemberStore.error || fetchError?.response?.data?.message || "Failed to load employee profile.",
        );
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
            class="bg-amber-50 border border-amber-200 text-amber-900 rounded-2xl px-5 py-4 mb-6"
        >
            {{ error }}. Showing basic account information while the full employee profile is unavailable.
        </div>

        <div class="bg-white border border-brand-border rounded-2xl mb-6 p-6">
            <div class="flex items-center gap-6">
                <div class="relative">
                    <img loading="lazy"
                        :src="resolvedProfile?.user?.profile_photo || DEFAULT_AVATAR"
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
                            <span>{{ capitalize(resolvedProfile?.job_information?.work_location) }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <User class="w-4 h-4" />
                            <span>{{ resolvedProfile?.code || "-" }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Calendar class="w-4 h-4" />
                            <span>Joined {{ formatDate(resolvedProfile?.job_information?.start_date) }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center">
                    <RouterLink
                        v-if="can('staff-member-edit') && resolvedProfile?.id"
                        :to="{
                            name: 'admin.staffMembers.edit',
                            params: { id: resolvedProfile.id },
                        }"
                        class="btn-primary rounded-lg hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 blue-gradient blue-btn-shadow px-6 py-3 flex items-center gap-2"
                    >
                        <Edit class="w-4 h-4 text-white" />
                        <span class="text-brand-white text-sm font-semibold">Edit Profile</span>
                    </RouterLink>
                    <RouterLink
                        v-else
                        :to="{ name: 'staffMember.profile.edit' }"
                        class="btn-primary rounded-lg hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 blue-gradient blue-btn-shadow px-6 py-3 flex items-center gap-2"
                    >
                        <Edit class="w-4 h-4 text-white" />
                        <span class="text-brand-white text-sm font-semibold">Edit Profile</span>
                    </RouterLink>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6 items-start">
            <div class="space-y-6">
                <div class="bg-white border border-brand-border rounded-2xl p-4">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 bg-teal-50 rounded-xl flex items-center justify-center">
                            <Contact class="w-6 h-6 text-teal-600" />
                        </div>
                        <div>
                            <h3 class="text-brand-dark text-lg font-bold">Personal Information</h3>
                            <p class="text-brand-light text-base">Your contact and profile details</p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Email</span>
                            <span class="text-brand-dark text-base font-medium">
                                {{ resolvedProfile?.user?.email }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Phone</span>
                            <span class="text-brand-dark text-base font-medium">
                                {{ resolvedProfile?.phone || "-" }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Date of Birth</span>
                            <span class="text-brand-dark text-base font-medium">
                                {{ formatDate(resolvedProfile?.date_of_birth) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Staff Member ID</span>
                            <span class="text-brand-dark text-base font-medium">
                                {{ resolvedProfile?.code || "-" }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Office Location</span>
                            <span class="text-brand-dark text-base font-medium">
                                {{ capitalize(resolvedProfile?.job_information?.work_location) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-start">
                            <span class="text-brand-light text-base">Hobbies</span>
                            <span class="text-brand-dark text-base font-medium text-right max-w-[60%]">
                                {{ resolvedProfile?.hobby || "-" }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-brand-border rounded-2xl p-4">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center">
                            <MapPin class="w-6 h-6 text-purple-600" />
                        </div>
                        <div>
                            <h3 class="text-brand-dark text-lg font-bold">Location Details</h3>
                            <p class="text-brand-light text-base">Address and location information</p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Gender</span>
                            <span class="text-brand-dark text-base font-medium">
                                {{ capitalize(resolvedProfile?.gender) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Place of Birth</span>
                            <span class="text-brand-dark text-base font-medium">
                                {{ resolvedProfile?.place_of_birth || "-" }}
                            </span>
                        </div>
                        <div class="flex justify-between items-start">
                            <span class="text-brand-light text-base">Address</span>
                            <span class="text-brand-dark text-base font-medium text-right max-w-[60%]">
                                {{ resolvedProfile?.address || "-" }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">City</span>
                            <span class="text-brand-dark text-base font-medium">
                                {{ resolvedProfile?.city || "-" }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Post Code</span>
                            <span class="text-brand-dark text-base font-medium">
                                {{ resolvedProfile?.postal_code || "-" }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Country</span>
                            <span class="text-brand-dark text-base font-medium">Indonesia</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-brand-border rounded-2xl p-4">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center">
                            <Phone class="w-6 h-6 text-red-600" />
                        </div>
                        <div>
                            <h3 class="text-brand-dark text-lg font-bold">Emergency Contact</h3>
                            <p class="text-brand-light text-base">Person to contact in case of emergency</p>
                        </div>
                    </div>
                    <div
                        class="space-y-4"
                        v-if="resolvedProfile?.emergency_contacts && resolvedProfile.emergency_contacts.length > 0"
                    >
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Contact Name</span>
                            <span class="text-brand-dark text-base font-medium">
                                {{ resolvedProfile.emergency_contacts[0]?.full_name || "-" }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Relationship</span>
                            <span class="text-brand-dark text-base font-medium">
                                {{ capitalize(resolvedProfile.emergency_contacts[0]?.relationship) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Phone Number</span>
                            <span class="text-brand-dark text-base font-medium">
                                {{ resolvedProfile.emergency_contacts[0]?.phone || "-" }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Email</span>
                            <span class="text-brand-dark text-base font-medium">
                                {{ resolvedProfile.emergency_contacts[0]?.email || "-" }}
                            </span>
                        </div>
                    </div>
                    <EmptyState v-else icon="Users" title="Kontak darurat belum ditambahkan" size="sm" />
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white border border-brand-border rounded-2xl p-4">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                            <Briefcase class="w-6 h-6 text-green-600" />
                        </div>
                        <div>
                            <h3 class="text-brand-dark text-lg font-bold">Employment Details</h3>
                            <p class="text-brand-light text-base">Work and compensation information</p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Job Title</span>
                            <span class="text-brand-dark text-base font-medium">
                                {{ capitalize(resolvedProfile?.job_information?.job_title) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Start Date</span>
                            <span class="text-brand-dark text-base font-medium">
                                {{ formatDate(resolvedProfile?.job_information?.start_date) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Employment Type</span>
                            <span class="text-brand-dark text-base font-medium">
                                {{ capitalize(resolvedProfile?.job_information?.employment_type) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Monthly Salary</span>
                            <span class="text-brand-dark text-base font-medium">
                                {{ formatCurrency(resolvedProfile?.job_information?.monthly_salary) }}
                            </span>
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
                            <span class="text-brand-dark text-base font-medium">
                                {{ capitalize(resolvedProfile?.job_information?.work_location) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-brand-border rounded-2xl p-4 h-fit" v-if="resolvedProfile?.team">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center">
                                <Users class="w-6 h-6 text-indigo-600" />
                            </div>
                            <div>
                                <h3 class="text-brand-dark text-lg font-bold">My Team</h3>
                                <p class="text-brand-light text-base">Team information</p>
                            </div>
                        </div>
                        <button
                            class="border border-brand-border rounded-lg hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 px-4 py-2 flex items-center gap-2"
                        >
                            <Users class="w-4 h-4 text-gray-600" />
                            <span class="text-brand-dark text-sm font-semibold">View Team</span>
                        </button>
                    </div>

                    <div
                        class="flex items-center gap-4 mb-4 p-4 bg-brand-primary rounded-2xl"
                    >
                        <div class="w-16 h-16 relative flex items-center justify-center rounded-xl overflow-hidden">
                            <div class="w-full h-full absolute bg-white/20 rounded-xl"></div>
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

                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Team Lead</span>
                            <div class="flex items-center gap-2">
                                <span class="text-brand-dark text-base font-medium">
                                    {{ resolvedProfile.team.leader?.name || "-" }}
                                </span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Department</span>
                            <span class="text-brand-dark text-base font-medium">
                                {{ capitalize(resolvedProfile.team.department) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-brand-light text-base">Team Size</span>
                            <span class="text-brand-dark text-base font-medium">
                                {{ resolvedProfile.team.members_count || 0 }} members
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-else class="bg-white border border-brand-border rounded-2xl p-8 text-center text-gray-600">
        Your employee profile is not available yet.
    </div>
</template>
