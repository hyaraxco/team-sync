<script setup>
import { onMounted } from "vue";
import SearchFilter from "@/components/common/SearchFilter.vue";
import { useOptionStore } from "@/stores/option";
import { storeToRefs } from "pinia";

const emit = defineEmits(["search"]);

const optionStore = useOptionStore();
const { departments, jobStatuses } = storeToRefs(optionStore);

onMounted(async () => {
    if (departments.value.length === 0) {
        await optionStore.fetchDepartments();
    }
    if (jobStatuses.value.length === 0) {
        await optionStore.fetchJobStatuses();
    }
});

const handleSearch = (params) => {
    emit("search", params);
};
</script>

<template>
    <SearchFilter
        placeholder="Search employees, tasks, reports, projects..."
        :filters="[
            {
                key: 'department',
                label: 'All Departments',
                icon: 'Building',
                options: departments,
            },
            {
                key: 'status',
                label: 'All Status',
                icon: 'CheckCircle',
                options: jobStatuses,
            },
        ]"
        @search="handleSearch"
    />
</template>
