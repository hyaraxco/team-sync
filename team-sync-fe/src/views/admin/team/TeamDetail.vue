<script setup>
import { onMounted, ref, watch, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useTeamStore } from "@/stores/team";
import { storeToRefs } from "pinia";
import {
    Calendar,
    Folder,
    CheckCircle,
    Users,
    UserPlus,
    Mail,
    Crown,
    Phone,
    MessageCircle,
    UserCheck,
    Settings,
    ListCheck,
    Check,
    Eye,
    User,
    Clock,
    Activity,
    FileText,
    ExternalLink,
    MessageSquare,
    AlertTriangle,
    Trash2,
} from "lucide-vue-next";
import _ from "lodash";
import { debounce } from "lodash";
import { formatToClientTimezone } from "@/helpers/format";
import Alert from "@/components/common/Alert.vue";
import ConfirmationModal from "@/components/common/ConfirmationModal.vue";
import Header from "@/components/admin/team/detail/Header.vue";
import Statistic from "@/components/admin/team/detail/Statistic.vue";
import Chart from "@/components/admin/team/detail/Chart.vue";
import { useStaffMemberStore } from "@/stores/staffMember";
import { Search, X } from "lucide-vue-next";
import EmptyState from "@/components/common/EmptyState.vue";
import { useToast } from "@/composables/useToast";

const route = useRoute();
const router = useRouter();
const toast = useToast();
const id = route.params.id;

const teamStore = useTeamStore();
const { loading, success, error } = storeToRefs(teamStore);
const { fetchTeam, deleteTeam, addMember, removeMember, updateTeam } = teamStore;

const staffMemberStore = useStaffMemberStore();
const { staffMembers } = storeToRefs(staffMemberStore);
const { fetchStaffMembers } = staffMemberStore;

const team = ref({});
const showDeleteModal = ref(false);
const showAddMemberModal = ref(false);
const searchMember = ref("");
const addingMember = ref(false);
const removingMember = ref(false);
const assigningLead = ref(false);
const showRemoveMemberModal = ref(false);
const memberToRemove = ref(null);

const availableEmployees = computed(() => {
    if (!team.value.members || !Array.isArray(team.value.members)) {
        return staffMembers.value;
    }

    const memberIds = team.value.members.map((member) => member.staff_member.id);
    return staffMembers.value.filter((employee) => !memberIds.includes(employee.id));
});

const handleFetchTeam = async () => {
    try {
        const response = await fetchTeam(id);
        team.value = response;
    } catch (err) {
        toast.error(
            "Failed to load team",
            teamStore.error || err?.response?.data?.message || "Failed to load team details.",
        );
    }
};

const handleDeleteTeam = async () => {
    await deleteTeam(id);

    if (success.value) {
        showDeleteModal.value = false;
        router.push({ name: "admin.teams" });
    }
};

const openAddMemberModal = () => {
    showAddMemberModal.value = true;
    fetchStaffMembers({ limit: 6 });
};

const closeAddMemberModal = () => {
    showAddMemberModal.value = false;
    searchMember.value = "";
};

const handleAddMember = async (employee) => {
    try {
        addingMember.value = true;
        await addMember(id, employee.id);

        // Refresh team data
        await handleFetchTeam();

        closeAddMemberModal();
    } catch (error) {
        toast.error(
            "Failed to add member",
            teamStore.error || error?.response?.data?.message || "Failed to add member.",
        );
    } finally {
        addingMember.value = false;
    }
};

const handleRemoveMember = async (member) => {
    try {
        removingMember.value = true;
        await removeMember(id, member.staff_member.id);
        await handleFetchTeam();
    } catch (error) {
        toast.error(
            "Failed to remove member",
            teamStore.error || error?.response?.data?.message || "Failed to remove member.",
        );
    } finally {
        removingMember.value = false;
    }
};

const isCurrentTeamLead = (member) => {
    const leaderId = team.value?.leader?.id;
    const memberUserId = member?.staff_member?.user?.id;
    return Boolean(leaderId && memberUserId && leaderId === memberUserId);
};

const handleAssignTeamLead = async (member) => {
    const targetLeadId = member?.staff_member?.user?.id;

    if (!targetLeadId || isCurrentTeamLead(member)) {
        return;
    }

    try {
        assigningLead.value = true;
        await updateTeam(id, { team_lead_id: targetLeadId });
        await handleFetchTeam();
        toast.success(
            "Team lead assigned",
            `${member?.staff_member?.user?.name || "Selected member"} is now the team lead.`,
        );
    } catch (error) {
        toast.error(
            "Failed to assign team lead",
            teamStore.error || error?.response?.data?.message || "Failed to update team lead.",
        );
    } finally {
        assigningLead.value = false;
    }
};

const openRemoveMemberModal = (member) => {
    memberToRemove.value = member;
    showRemoveMemberModal.value = true;
};

const confirmRemoveMember = async () => {
    if (!memberToRemove.value) return;
    await handleRemoveMember(memberToRemove.value);
    showRemoveMemberModal.value = false;
    memberToRemove.value = null;
};

onMounted(async () => {
    await handleFetchTeam();
});

watch(
    searchMember,
    debounce(() => {
        fetchStaffMembers({
            limit: 6,
            search: searchMember.value,
        });
    }, 300),
    { deep: true },
);
</script>

<template>
    <Alert type="success" :title="success || ''" :message="success || ''" :show="Boolean(success)" />

    <Header :team="team" />

    <Statistic :team="team" />

    <Chart :team="team" />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white border border-brand-border rounded-2xl p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                    <Crown class="w-6 h-6 text-green-600" />
                </div>
                <h2 class="text-brand-dark text-lg font-semibold">Team Lead</h2>
            </div>

            <div class="flex flex-col gap-4" v-if="team.leader">
                <div class="border border-brand-border rounded-xl p-4 mb-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="relative">
                                <img loading="lazy"
                                    :src="team.leader.profile_photo"
                                    alt="Team Lead"
                                    class="w-16 h-16 rounded-full object-cover"
                                    v-if="team.leader.profile_photo"
                                />
                                <div
                                    class="w-12 h-12 rounded-xl flex items-center justify-center bg-gray-100"
                                    v-else
                                >
                                    <User class="w-5 h-5 text-gray-400" />
                                </div>
                                <div
                                    class="absolute -bottom-1 -right-1 w-5 h-5 bg-green-500 border-2 border-white rounded-full"
                                ></div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-brand-dark text-base font-bold truncate">
                                    {{ team.leader?.name }}
                                </p>
                                <p class="text-brand-light text-sm">
                                    {{ team.leader?.employee_profile?.job_information?.job_title }}
                                </p>
                            </div>
                        </div>
                        <span class="px-2 py-1 rounded-md text-xs font-semibold bg-primary-100 text-primary-800">
                            Team Lead
                        </span>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="space-y-2">
                        <div
                            class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 transition-all duration-300"
                        >
                            <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center">
                                <Mail class="w-4 h-4 text-blue-600" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-brand-light text-xs font-medium">Email Address</p>
                                <p class="text-brand-dark text-sm font-semibold truncate">
                                    {{ team.leader?.email || "-" }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 transition-all duration-300"
                        >
                            <div class="w-8 h-8 bg-green-50 rounded-lg flex items-center justify-center">
                                <Phone class="w-4 h-4 text-green-600" />
                            </div>
                            <div class="flex-1">
                                <p class="text-brand-light text-xs font-medium">Phone Number</p>
                                <p class="text-brand-dark text-sm font-semibold">
                                    {{ team?.leader?.employee_profile?.phone }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 mt-4 pt-4 border-t border-brand-border">
                    <button
                        class="flex-1 border border-brand-border rounded-lg hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 px-3 py-2 flex items-center justify-center gap-2"
                    >
                        <MessageCircle class="w-5 h-5 text-brand-light" />
                        <span class="text-brand-dark text-base font-semibold">Message</span>
                    </button>
                    <button
                        class="flex-1 border border-brand-border rounded-lg hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 px-3 py-2 flex items-center justify-center gap-2"
                    >
                        <UserCheck class="w-5 h-5 text-brand-light" />
                        <span class="text-brand-dark text-base font-semibold">View Profile</span>
                    </button>
                </div>
            </div>

            <div class="flex flex-1 items-center justify-center min-h-[250px]" v-else>
                <div class="flex flex-col items-center justify-center">
                    <Crown class="w-12 h-12 text-gray-300 mb-4" />
                    <h3 class="text-brand-dark text-base font-semibold mb-2">Belum ada leader tim</h3>
                    <p class="text-brand-light text-sm mb-4 text-center">
                        This team does not currently have a leader assigned.
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white border border-brand-border rounded-2xl p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center">
                        <Settings class="w-6 h-6 text-purple-600" />
                    </div>
                    <h2 class="text-brand-dark text-lg font-semibold">Team Settings</h2>
                </div>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-brand-light text-base">Department</span>
                    <span class="text-brand-dark text-base font-medium">
                        {{ _.capitalize(team.department) }}
                    </span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-brand-light text-base">Expected Size</span>
                    <span class="text-brand-dark text-base font-medium">{{ team.expected_size }} members</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-brand-light text-base">Created Date</span>
                    <span class="text-brand-dark text-base font-medium">
                        {{ formatToClientTimezone(team.created_at) }}
                    </span>
                </div>
            </div>

            <div class="mt-4">
                <img loading="lazy"
                    src="https://images.unsplash.com/photo-1557804506-669a67965ba0"
                    alt="Team Banner"
                    class="w-full h-[138px] object-cover rounded-xl"
                />
            </div>
        </div>
    </div>

    <div class="bg-white border border-brand-border rounded-2xl p-6 mb-6">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 bg-orange-50 rounded-xl flex items-center justify-center">
                <ListCheck class="w-6 h-6 text-orange-600" />
            </div>
            <h2 class="text-brand-dark text-lg font-semibold">Team Responsibilities</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
                class="flex items-start gap-3 p-4 bg-gray-50 rounded-xl"
                v-for="responsibility in team.responsibilities"
                :key="responsibility.id"
            >
                <div
                    class="w-8 h-8 min-w-[32px] bg-blue-100 rounded-full flex items-center justify-center mt-1 flex-shrink-0"
                >
                    <Check class="w-4 h-4 text-blue-600" />
                </div>
                <span class="text-brand-dark text-sm font-medium">{{ responsibility }}</span>
            </div>
        </div>
    </div>

    <div class="bg-white border border-brand-border rounded-2xl p-6 mb-6">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                    <Users class="w-6 h-6 text-blue-600" />
                </div>
                <h2 class="text-brand-dark text-lg font-semibold">Team Members</h2>
            </div>
            <button
                @click="openAddMemberModal"
                class="btn-primary rounded-lg hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center gap-2"
            >
                <UserPlus class="w-4 h-4 text-white" />
                <span class="text-brand-white text-sm font-semibold">Add Member</span>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <div
                class="relative border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 hover:shadow-lg transition-all duration-300 p-4"
                v-for="member in team.members"
                :key="member.id"
            >
                <button
                    @click="openRemoveMemberModal(member)"
                    :disabled="removingMember"
                    title="Remove member"
                    class="absolute top-3 right-3 w-8 h-8 rounded-full bg-red-50 border border-red-200 hover:bg-red-100 transition-all duration-300 flex items-center justify-center disabled:opacity-60"
                >
                    <Trash2 class="w-4 h-4 text-red-600" />
                </button>
                <div class="flex flex-col items-center mb-3">
                    <div class="relative">
                        <img loading="lazy"
                            :src="member.staff_member.user?.profile_photo"
                            alt="Team Member"
                            class="w-[100px] h-[100px] rounded-full object-cover mb-3"
                            v-if="member.staff_member.user?.profile_photo"
                        />
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-gray-100" v-else>
                            <User class="w-5 h-5 text-gray-400" />
                        </div>
                    </div>
                </div>
                <div class="text-center mb-3">
                    <p class="text-brand-dark text-lg font-bold">
                        {{ member.staff_member?.user?.name || "-" }}
                    </p>
                    <p class="text-brand-light text-base">
                        {{ member.staff_member?.job_information?.job_title || "-" }}
                    </p>
                </div>
                <div class="space-y-1 mb-3">
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <Calendar class="w-4 h-4" />
                        <span>Joined {{ formatToClientTimezone(member.joined_at) }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <Clock class="w-4 h-4" />
                        <span>{{ member.staff_member.job_information.experience }} years experience</span>
                    </div>
                </div>
                <RouterLink
                    :to="{
                        name: 'admin.staffMembers.detail',
                        params: { id: member.staff_member.id },
                    }"
                    class="w-full border border-brand-border rounded-lg hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 px-3 py-2 flex items-center justify-center gap-2"
                >
                    <Eye class="w-5 h-5 text-gray-600" />
                    <span class="text-brand-dark text-base font-semibold">View Profile</span>
                </RouterLink>
                <button
                    type="button"
                    @click="handleAssignTeamLead(member)"
                    :disabled="assigningLead || isCurrentTeamLead(member)"
                    class="w-full mt-2 rounded-lg px-3 py-2 flex items-center justify-center gap-2 transition-all duration-300 border"
                    :class="
                        isCurrentTeamLead(member)
                            ? 'border-green-200 bg-green-50 text-green-700 cursor-default'
                            : 'border-brand-border text-brand-dark hover:border-brand-primary hover:bg-gray-50 disabled:opacity-60'
                    "
                >
                    <Crown class="w-4 h-4" />
                    <span class="text-sm font-semibold">
                        {{ isCurrentTeamLead(member) ? "Current Team Lead" : "Set as Team Lead" }}
                    </span>
                </button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white border border-brand-border rounded-2xl p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-12 h-12 bg-gray-50 rounded-xl flex items-center justify-center">
                    <Activity class="w-6 h-6 text-gray-600" />
                </div>
                <h2 class="text-brand-dark text-lg font-semibold">Recent Activity</h2>
            </div>
            <div class="space-y-4">
                <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl">
                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                    <div class="flex-1">
                        <p class="text-brand-dark text-sm font-medium">
                            Project milestone completed: API Integration Phase
                        </p>
                        <p class="text-brand-light text-xs">3 days ago</p>
                    </div>
                    <div class="flex items-center gap-1 text-green-600">
                        <CheckCircle class="w-4 h-4" />
                        <span class="text-sm font-medium">Completed</span>
                    </div>
                </div>
                <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl">
                    <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                    <div class="flex-1">
                        <p class="text-brand-dark text-sm font-medium">New team member joined: Emily Rodriguez</p>
                        <p class="text-brand-light text-xs">1 week ago</p>
                    </div>
                    <div class="flex items-center gap-1 text-blue-600">
                        <UserPlus class="w-4 h-4" />
                        <span class="text-sm font-medium">Member Added</span>
                    </div>
                </div>
                <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl">
                    <div class="w-3 h-3 bg-purple-500 rounded-full"></div>
                    <div class="flex-1">
                        <p class="text-brand-dark text-sm font-medium">
                            Team training session conducted: Advanced React Patterns
                        </p>
                        <p class="text-brand-light text-xs">2 weeks ago</p>
                    </div>
                    <div class="flex items-center gap-1 text-purple-600">
                        <i data-lucide="graduation-cap" class="w-4 h-4"></i>
                        <span class="text-sm font-medium">Training</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white border border-brand-border rounded-2xl p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                    <Folder class="w-6 h-6 text-blue-600" />
                </div>
                <h2 class="text-brand-dark text-lg font-semibold">Team Resources</h2>
            </div>
            <div class="space-y-4">
                <div
                    class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors cursor-pointer"
                >
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <FileText class="w-5 h-5 text-green-600" />
                    </div>
                    <div class="flex-1">
                        <p class="text-brand-dark text-sm font-medium">Team Documentation</p>
                        <p class="text-brand-light text-xs">Project guidelines and processes</p>
                    </div>
                    <ExternalLink class="w-4 h-4 text-gray-400" />
                </div>
                <div
                    class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors cursor-pointer"
                >
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <Calendar class="w-5 h-5 text-purple-600" />
                    </div>
                    <div class="flex-1">
                        <p class="text-brand-dark text-sm font-medium">Team Calendar</p>
                        <p class="text-brand-light text-xs">Meetings and deadlines</p>
                    </div>
                    <ExternalLink class="w-4 h-4 text-gray-400" />
                </div>
                <div
                    class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors cursor-pointer"
                >
                    <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                        <MessageSquare class="w-5 h-5 text-orange-600" />
                    </div>
                    <div class="flex-1">
                        <p class="text-brand-dark text-sm font-medium">Team Chat</p>
                        <p class="text-brand-light text-xs">Communication channel</p>
                    </div>
                    <ExternalLink class="w-4 h-4 text-gray-400" />
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white border border-danger-100 rounded-2xl p-6">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center">
                <AlertTriangle class="w-6 h-6 text-red-600" />
            </div>
            <h2 class="text-brand-dark text-lg font-semibold">Danger Zone</h2>
        </div>
        <div
            class="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center p-4 bg-red-50 rounded-xl"
        >
            <div class="flex-1">
                <h3 class="text-brand-dark text-base font-semibold mb-1">Disband Team</h3>
                <p class="text-brand-light text-sm">
                    Permanently remove this team and reassign all members. This action cannot be undone.
                </p>
            </div>
            <button
                @click="showDeleteModal = true"
                class="btn-primary rounded-lg hover:brightness-110 focus:ring-2 focus:ring-danger-600 transition-all duration-300 bg-danger-600 shadow-lg px-6 py-3 flex items-center gap-2"
            >
                <Trash2 class="w-4 h-4 text-white" />
                <span class="text-brand-white text-sm font-semibold">Disband Team</span>
            </button>
        </div>
    </div>

    <ConfirmationModal
        :show="showDeleteModal"
        title="Disband Team"
        :message="`Are you sure you want to disband '${team.name}'? This will permanently remove the team and reassign all members. This action cannot be undone.`"
        confirmText="Disband Team"
        cancelText="Cancel"
        type="danger"
        :loading="loading"
        @confirm="handleDeleteTeam"
        @cancel="showDeleteModal = false"
    />

    <ConfirmationModal
        :show="showRemoveMemberModal"
        title="Remove Member"
        :message="`Are you sure you want to remove '${memberToRemove?.staff_member?.user?.name || ''}' from this team?`"
        confirmText="Remove"
        cancelText="Cancel"
        type="danger"
        :loading="loading"
        @confirm="confirmRemoveMember"
        @cancel="showRemoveMemberModal = false"
    />

    <div class="fixed inset-0 backdrop-blur-sm z-50 flex items-center justify-center" v-if="showAddMemberModal">
        <div class="bg-white rounded-2xl border border-brand-border w-full max-w-4xl mx-4 max-h-[80vh] overflow-hidden">
            <div class="p-6 border-b border-brand-border">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                            <UserPlus class="w-6 h-6 text-blue-600" />
                        </div>
                        <h3 class="text-brand-dark text-lg font-semibold">Add Team Member</h3>
                    </div>
                    <button
                        type="button"
                        @click="closeAddMemberModal"
                        class="w-10 h-10 rounded-full border border-brand-border flex items-center justify-center hover:ring-2 hover:ring-brand-primary/20 transition-all duration-200"
                    >
                        <X class="w-5 h-5 text-gray-600" />
                    </button>
                </div>
            </div>

            <div class="p-6 border-b border-brand-border">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <Search class="h-5 w-5 text-gray-400" />
                    </div>
                    <input
                        type="text"
                        class="w-full pl-12 pr-4 py-3 border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 focus:bg-white transition-all duration-300 font-semibold"
                        placeholder="Search staffMembers..."
                        v-model="searchMember"
                    />
                </div>
            </div>

            <div class="p-6 overflow-y-auto max-h-96">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        class="border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 hover:shadow-lg transition-all duration-300 p-4 cursor-pointer"
                        v-for="employee in availableEmployees"
                        :key="employee.id"
                        @click="handleAddMember(employee)"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="w-14 h-14 relative flex items-center justify-center rounded-xl overflow-hidden"
                            >
                                <img loading="lazy"
                                    :src="employee.user?.profile_photo"
                                    :alt="employee.user?.name"
                                    class="w-14 h-14 rounded-xl object-cover"
                                    v-if="employee.user?.profile_photo"
                                />

                                <div
                                    class="w-14 h-14 rounded-xl flex items-center justify-center bg-gray-100"
                                    v-else
                                >
                                    <User class="w-5 h-5 text-gray-400" />
                                </div>
                            </div>
                            <div class="flex-1">
                                <p class="text-brand-dark text-base font-bold">
                                    {{ employee.user?.name }}
                                </p>
                                <p class="text-brand-light text-sm font-normal">
                                    {{ employee.job_information?.job_title }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <EmptyState
                    v-if="availableEmployees.length === 0"
                    icon="SearchX"
                    title="Tidak ada karyawan tersedia"
                    subtitle="Semua karyawan sudah menjadi anggota tim ini atau ubah kata kunci pencarian"
                />
            </div>
        </div>
    </div>
</template>
