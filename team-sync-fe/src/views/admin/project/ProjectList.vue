<script setup>
import CardList from "@/components/admin/project/list/CardList.vue";
import Statistics from "@/components/admin/project/list/Statistics.vue";
import SearchFilter from "@/components/common/SearchFilter.vue";
import { useProjectStore } from "@/stores/project";
import { storeToRefs } from "pinia";
import { Upload, Plus, Briefcase, SearchX } from "lucide-vue-next";
import { onMounted } from "vue";
import Pagination from "@/components/admin/team/Pagination.vue";
import { can } from "@/helpers/permissionHelper";
import Alert from "@/components/common/Alert.vue";
import { useSearchFilter } from "@/composables/useSearchFilter";
import EmptyState from "@/components/common/EmptyState.vue";

const projectStore = useProjectStore();
const { projects, meta, loading, success } = storeToRefs(projectStore);

const projectStatuses = [
    { value: "draft", label: "Draft" },
    { value: "planning", label: "Planning" },
    { value: "active", label: "Active" },
    { value: "on_hold", label: "On Hold" },
    { value: "completed", label: "Completed" },
    { value: "cancelled", label: "Cancelled" },
];

const { filters, fetchData, handleSearch, handleReset, handlePageChange, handlePerPageChange } = useSearchFilter({
    defaultFilters: { search: null, status: "" },
    fetchFn: projectStore.fetchProjectsPaginated,
});

onMounted(async () => {
    await fetchData();
});
</script>

<template>
    <Statistics v-if="can('project-statistic')" />

    <Alert type="success" :title="success || ''" :message="success || ''" :show="Boolean(success)" />

    <!-- Projects Grid Section -->
    <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6 mb-6">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center">
                    <Briefcase class="w-6 h-6 text-blue-600" />
                </div>
                <div>
                    <h3 class="text-brand-dark text-xl font-bold">All Projects</h3>
                    <p class="text-brand-light text-sm font-normal">View and manage all project information</p>
                </div>
            </div>
            <div class="flex items-center gap-4" v-if="can('project-create')">
                <button
                    class="bg-white border border-[#DCDEDD] text-brand-dark py-3 px-4 rounded-[8px] font-medium hover:bg-gray-50 transition-colors flex items-center gap-2"
                >
                    <Upload class="w-4 h-4" />
                    <span class="text-sm font-semibold">Import CSV</span>
                </button>
                <RouterLink
                    class="btn-primary rounded-[8px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center gap-2"
                    :to="{ name: 'admin.projects.create' }"
                >
                    <Plus class="w-4 h-4 text-white" />
                    <span class="text-brand-white text-sm font-semibold">Add Project</span>
                </RouterLink>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="mb-6">
            <SearchFilter
                placeholder="Search projects..."
                :filters="[
                    {
                        key: 'status',
                        label: 'All Status',
                        icon: 'CheckCircle',
                        options: projectStatuses,
                    },
                ]"
                @search="handleSearch"
                @reset="handleReset"
            />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <CardList v-for="project in projects" :key="project.id" :data="project" />
        </div>

        <EmptyState
            v-if="projects.length === 0"
            icon="SearchX"
            title="No projects found"
            subtitle="Try adjusting your search terms or filters"
            class="py-12"
        />
    </div>

    <Pagination
        :meta="meta"
        :loading="loading"
        @page-change="handlePageChange"
        @per-page-change="handlePerPageChange"
    />
</template>
