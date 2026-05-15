<script setup>
import Statistics from "@/components/admin/staff-member/list/Statistics.vue";
import CardList from "@/components/admin/staff-member/list/CardList.vue";
import SearchFilter from "@/components/common/SearchFilter.vue";
import { useStaffMemberStore } from "@/stores/staffMember";
import { useOptionStore } from "@/stores/option";
import { storeToRefs } from "pinia";
import { onMounted } from "vue";
import { Upload, UserPlus } from "lucide-vue-next";
import Pagination from "@/components/admin/team/Pagination.vue";
import { can } from "@/helpers/permissionHelper";
import Alert from "@/components/common/Alert.vue";
import { useSearchFilter } from "@/composables/useSearchFilter";

const staffMemberStore = useStaffMemberStore();
const { staffMembers: employees, meta, loading, success } = storeToRefs(staffMemberStore);

const optionStore = useOptionStore();
const { employmentTypes, jobStatuses } = storeToRefs(optionStore);
const { fetchEmploymentTypes, fetchJobStatuses } = optionStore;

const { filters, fetchData, handleSearch, handleReset, handlePageChange, handlePerPageChange } = useSearchFilter({
    defaultFilters: { search: null, type: "", status: "" },
    fetchFn: staffMemberStore.fetchStaffMembersPaginated,
});

onMounted(async () => {
    await fetchData();
    await fetchEmploymentTypes();
    await fetchJobStatuses();
});
</script>

<template>
    <Statistics />

    <!-- Search Section -->
    <div class="mb-6">
        <SearchFilter
            placeholder="Search employees by name, department, role..."
            :filters="[
                {
                    key: 'type',
                    label: 'All Types',
                    icon: 'Briefcase',
                    options: employmentTypes,
                },
                {
                    key: 'status',
                    label: 'All Status',
                    icon: 'CheckCircle',
                    options: jobStatuses,
                },
            ]"
            @search="handleSearch"
            @reset="handleReset"
        />
    </div>

    <Alert type="success" :title="success || ''" :message="success || ''" :show="Boolean(success)" />

    <div class="bg-white border border-brand-border rounded-2xl mb-6 p-5">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-brand-dark font-['Plus_Jakarta_Sans'] text-[20px] font-bold">All Staff Members</h3>
                <p class="text-brand-light font-['Plus_Jakarta_Sans'] text-[14px] font-normal mt-1">
                    Showing {{ meta.from }} - {{ meta.to }} of {{ meta.total }} employees
                </p>
            </div>
            <div class="flex items-center gap-[10px]">
                <button
                    v-if="can('staff-member-create')"
                    class="border border-brand-border rounded-lg hover:ring-2 hover:ring-primary-500/20 hover:bg-gray-50 transition-all duration-300 px-4 py-3 flex items-center gap-2"
                >
                    <Upload class="w-4 h-4 text-gray-600" />
                    <span class="text-brand-dark text-sm font-semibold">Import CSV</span>
                </button>
                <RouterLink
                    :to="{ name: 'admin.staffMembers.create' }"
                    class="btn-primary rounded-lg border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-primary-500 transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3"
                    v-if="can('staff-member-create')"
                >
                    <UserPlus class="w-4 h-4 text-white" />
                    <span class="text-brand-white text-sm font-semibold">Add Staff Member</span>
                </RouterLink>
            </div>
        </div>

        <!-- Employee Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-6">
            <CardList v-for="employee in employees" :key="employee.id" :data="employee" />
        </div>

        <!-- Pagination -->
        <Pagination
            :meta="meta"
            :loading="loading"
            @page-change="handlePageChange"
            @per-page-change="handlePerPageChange"
        />
    </div>
</template>
