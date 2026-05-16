<script setup>
import { ref, computed, watch, onUnmounted } from "vue";
import {
    SearchIcon,
    ChevronDownIcon,
    XIcon,
    BuildingIcon,
    CheckCircleIcon,
    BriefcaseIcon,
    TagIcon,
    FilterIcon,
} from "lucide-vue-next";

const props = defineProps({
    /**
     * Search input placeholder text
     */
    placeholder: {
        type: String,
        default: "Search...",
    },
    /**
     * Array of filter configurations:
     * [{ key: 'status', label: 'All Status', icon: 'CheckCircle', options: [{ value, label }] }]
     */
    filters: {
        type: Array,
        default: () => [],
    },
    /**
     * Whether to show the search button
     */
    showSearchButton: {
        type: Boolean,
        default: false,
    },
    /**
     * modelValue for v-model support: { search: '', ...filterKeys }
     */
    modelValue: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(["update:modelValue", "search", "reset"]);

// Internal state
const searchQuery = ref(props.modelValue?.search || "");
const filterValues = ref({});
let debounceTimer = null;

// Initialize filter values from modelValue
props.filters.forEach((f) => {
    filterValues.value[f.key] = props.modelValue?.[f.key] || "";
});

// Icon mapping
const iconMap = {
    Building: BuildingIcon,
    CheckCircle: CheckCircleIcon,
    Briefcase: BriefcaseIcon,
    Tag: TagIcon,
    Filter: FilterIcon,
};

const getIcon = (iconName) => {
    return iconMap[iconName] || FilterIcon;
};

// Check if any filter is active
const hasActiveFilters = computed(() => {
    if (searchQuery.value && searchQuery.value.trim()) return true;
    return Object.values(filterValues.value).some((v) => v !== "" && v != null);
});

// Build params object
const buildParams = () => {
    const params = {};
    if (searchQuery.value && searchQuery.value.trim()) {
        params.search = searchQuery.value.trim();
    }
    props.filters.forEach((f) => {
        if (filterValues.value[f.key]) {
            params[f.key] = filterValues.value[f.key];
        }
    });
    return params;
};

const emitSearch = () => {
    const params = buildParams();
    emit("update:modelValue", { search: searchQuery.value, ...filterValues.value });
    emit("search", params);
};

// Debounced auto-search on typing (300ms)
watch(searchQuery, () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        emitSearch();
    }, 300);
});

// Instant trigger on dropdown change
watch(
    filterValues,
    () => {
        emitSearch();
    },
    { deep: true },
);

// Reset all filters
const handleReset = () => {
    searchQuery.value = "";
    props.filters.forEach((f) => {
        filterValues.value[f.key] = "";
    });
    emit("update:modelValue", { search: "", ...filterValues.value });
    emit("reset");
    emit("search", {});
};

// Manual search button
const handleSearchClick = () => {
    emitSearch();
};

onUnmounted(() => {
    if (debounceTimer) clearTimeout(debounceTimer);
});
</script>

<template>
    <div class="bg-white border border-brand-border rounded-2xl p-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
            <!-- Search Bar -->
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <SearchIcon class="h-5 w-5 text-gray-500" />
                </div>
                <input
                    v-model="searchQuery"
                    type="text"
                    aria-label="Search"
                    class="w-full pl-12 pr-4 py-3 border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:border-brand-primary focus:border-2 focus:bg-white transition-all duration-300 font-semibold"
                    :placeholder="placeholder"
                />
            </div>

            <!-- Filters and Actions -->
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full sm:w-auto">
                <!-- Dynamic Filter Dropdowns -->
                <div v-for="filter in filters" :key="filter.key" class="relative w-full sm:w-auto">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <component :is="getIcon(filter.icon)" class="h-4 w-4 text-gray-500" />
                    </div>
                    <select
                        v-model="filterValues[filter.key]"
                        :aria-label="filter.label"
                        class="w-full sm:w-auto pl-10 pr-8 py-3 border border-brand-border rounded-2xl hover:ring-2 hover:ring-brand-primary/20 focus:border-brand-primary focus:border-2 transition-all duration-300 bg-white appearance-none font-semibold"
                    >
                        <option value="">{{ filter.label }}</option>
                        <option v-for="opt in filter.options" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                        <ChevronDownIcon class="h-4 w-4 text-gray-500" />
                    </div>
                </div>

                <!-- Reset Button (only shown when filters active) -->
                <button
                    v-if="hasActiveFilters"
                    @click="handleReset"
                    class="w-full sm:w-auto rounded-lg border border-brand-border hover:border-red-400 hover:bg-red-50 transition-all duration-300 px-4 py-3 flex items-center justify-center sm:justify-start gap-2"
                >
                    <XIcon class="w-4 h-4 text-red-500" />
                    <span class="text-red-500 text-sm font-semibold">Reset</span>
                </button>

                <!-- Search Button (optional) -->
                <button
                    v-if="showSearchButton"
                    @click="handleSearchClick"
                    class="btn-primary w-full sm:w-auto rounded-lg border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 blue-gradient blue-btn-shadow px-6 py-3 flex items-center justify-center sm:justify-start gap-2"
                >
                    <SearchIcon class="w-4 h-4 text-white" />
                    <span class="text-brand-white text-base font-semibold">Search</span>
                </button>
            </div>
        </div>
    </div>
</template>
