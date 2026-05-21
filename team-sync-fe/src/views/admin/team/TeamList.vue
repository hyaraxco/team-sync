<script setup>
import Statistic from "@/components/admin/team/Statistic.vue";
import CardList from "@/components/admin/team/CardList.vue";
import Pagination from "@/components/admin/team/Pagination.vue";
import SearchFilter from "@/components/common/SearchFilter.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import { useTeamStore } from "@/stores/team";
import { useOptionStore } from "@/stores/option";
import { storeToRefs } from "pinia";
import { onMounted } from "vue";
import { Upload, Users } from "lucide-vue-next";
import Alert from "@/components/common/Alert.vue";
import { useSearchFilter } from "@/composables/useSearchFilter";

const teamStore = useTeamStore();
const { teams, meta, loading, success } = storeToRefs(teamStore);

const optionStore = useOptionStore();
const { departments } = storeToRefs(optionStore);
const { fetchDepartments } = optionStore;

const teamStatuses = [
    { value: "active", label: "Active" },
    { value: "forming", label: "Forming" },
    { value: "planning", label: "Planning" },
    { value: "dormant", label: "Dormant" },
];

const { filters, fetchData, handleSearch, handleReset, handlePageChange, handlePerPageChange } = useSearchFilter({
    defaultFilters: { search: null, status: "", department: "" },
    fetchFn: teamStore.fetchTeamsPaginated,
});

onMounted(async () => {
    await fetchDepartments();
    fetchData();
});
</script>

<template>
    <Statistic />

    <!-- Search Section -->
    <div class="mb-6">
        <SearchFilter
            placeholder="Search teams by name, lead, status..."
            :filters="[
                {
                    key: 'status',
                    label: 'All Status',
                    icon: 'CheckCircle',
                    options: teamStatuses,
                },
                {
                    key: 'department',
                    label: 'All Departments',
                    icon: 'Building',
                    options: departments,
                },
            ]"
            @search="handleSearch"
            @reset="handleReset"
        />
    </div>

    <Alert type="success" :title="success || ''" :message="success || ''" :show="Boolean(success)" />

    <!-- Team List Section -->
    <div class="bg-white border border-brand-border rounded-2xl mb-6 p-4 sm:p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <div>
                <h3 class="text-brand-dark text-xl font-bold">All Teams</h3>
                <p class="text-brand-light text-sm font-normal mt-1">
                    Showing {{ meta.from }} - {{ meta.to }} of {{ meta.total }} teams
                </p>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
                <button
                    class="border border-brand-border rounded-lg hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 px-4 py-3 flex items-center gap-2 w-full sm:w-auto"
                >
                    <Upload class="w-4 h-4 text-gray-600" />
                    <span class="text-brand-dark text-sm font-semibold">Import CSV</span>
                </button>
                <RouterLink
                    :to="{ name: 'admin.team.create' }"
                    class="btn-primary rounded-lg hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center gap-2 w-full sm:w-auto"
                >
                    <Users class="w-4 h-4 text-white" />
                    <span class="text-brand-white text-sm font-semibold">Add Team</span>
                </RouterLink>
            </div>
        </div>

        <!-- Teams Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mb-6" v-if="teams.length > 0">
            <CardList v-for="team in teams" :key="team.id" :data="team" />
        </div>

        <!-- Empty State -->
        <EmptyState v-else icon="Users" title="Belum ada tim" />

        <!-- Pagination -->
        <Pagination
            :meta="meta"
            :loading="loading"
            @page-change="handlePageChange"
            @per-page-change="handlePerPageChange"
        />
    </div>
</template>
