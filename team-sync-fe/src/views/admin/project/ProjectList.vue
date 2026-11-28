<script setup>
import CardList from "@/components/admin/project/list/CardList.vue";
import Statistics from "@/components/admin/project/list/Statistics.vue";
import SearchFilter from "@/components/common/SearchFilter.vue";
import { useProjectStore } from "@/stores/project";
import { storeToRefs } from "pinia";
import { Upload, Plus, Briefcase } from "lucide-vue-next";
import { onMounted, ref } from "vue";
import Pagination from "@/components/admin/team/Pagination.vue";
import { can } from "@/helpers/permissionHelper";
import Alert from "@/components/common/Alert.vue";
import { useSearchFilter } from "@/composables/useSearchFilter";
import EmptyState from "@/components/common/EmptyState.vue";

const projectStore = useProjectStore();
const { projects, meta, loading, success } = storeToRefs(projectStore);
const { filters, fetchData, handleSearch, handleReset, handlePageChange, handlePerPageChange } = useSearchFilter({
    defaultFilters: { search: null, status: "" },
    fetchFn: projectStore.fetchProjectsPaginated,
});

const hasLoaded = ref(false);

onMounted(async () => {
    await fetchData();
    hasLoaded.value = true;
});

const projectStatuses = [
    { value: "draft", label: "Draft" },
    { value: "planning", label: "Planning" },
    { value: "active", label: "Active" },
    { value: "on_hold", label: "On Hold" },
    { value: "completed", label: "Completed" },
    { value: "cancelled", label: "Cancelled" },
];

</script>

<template>
    <Statistics v-if="can('project-statistic')" />

    <Alert type="success" :title="success || ''" :message="success || ''" :show="Boolean(success)" />

    <!-- Projects Grid Section -->
    <div class="bg-[var(--color-surface)] border border-brand-border rounded-2xl p-4 sm:p-5 mb-6">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-[var(--color-surface-overlay)] rounded-xl flex items-center justify-center">
                    <Briefcase class="w-6 h-6 text-brand-dark" />
                </div>
                <div>
                    <h3 class="text-brand-dark text-xl font-bold">All Projects</h3>
                    <p class="text-brand-light text-sm font-normal">View and manage all project information</p>
                </div>
            </div>
            <div class="flex items-center gap-4" v-if="can('project-create')">
                <button
                    class="bg-[var(--color-surface)] border border-brand-border text-brand-dark py-3 px-4 rounded-lg font-medium hover:bg-[var(--color-surface-overlay)] transition-colors flex items-center gap-2"
                >
                    <Upload class="w-4 h-4" />
                    <span class="text-sm font-semibold">Import CSV</span>
                </button>
                <RouterLink
                    class="btn-primary rounded-lg hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 px-4 py-3 flex items-center gap-2"
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
            v-if="hasLoaded && !loading && projects.length === 0"
            icon="SearchX"
            title="Belum ada proyek"
            subtitle="Coba ubah kata kunci atau filter pencarian"
            class="py-12"
        />
    </div>

    <Pagination
        v-if="hasLoaded && meta?.current_page != null && meta?.last_page != null && meta?.per_page != null && meta?.total != null"
        :meta="meta"
        :loading="loading"
        @page-change="handlePageChange"
        @per-page-change="handlePerPageChange"
    />
</template>
