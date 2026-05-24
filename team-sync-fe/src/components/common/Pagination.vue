<script setup>
import { ChevronLeft, ChevronRight } from "lucide-vue-next";
import { computed } from "vue";

const props = defineProps({
    meta: {
        type: Object,
        required: true,
        default: () => ({
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
            from: 0,
            to: 0,
        }),
    },
    loading: {
        type: Boolean,
        default: false,
    },
    perPageOptions: {
        type: Array,
        default: () => [10, 15, 24, 48],
    },
    showSummary: {
        type: Boolean,
        default: false,
    },
    summaryLabel: {
        type: String,
        default: "items",
    },
});

const emit = defineEmits(["page-change", "per-page-change"]);

const currentPage = computed(() => props.meta.current_page || 1);
const lastPage = computed(() => props.meta.last_page || 1);
const perPage = computed(() => {
    const val = props.meta.per_page;
    if (val && props.perPageOptions.includes(val)) return val;
    // If API returns a value not in options list, add it dynamically
    return val || props.perPageOptions[0];
});
const total = computed(() => props.meta.total || 0);
const from = computed(() => props.meta.from || 0);
const to = computed(() => props.meta.to || 0);

const visiblePages = computed(() => {
    const pages = [];
    const current = currentPage.value;
    const last = lastPage.value;

    if (last <= 7) {
        for (let page = 1; page <= last; page += 1) {
            pages.push(page);
        }

        return pages;
    }

    if (current <= 4) {
        for (let page = 1; page <= 5; page += 1) {
            pages.push(page);
        }
        pages.push("...");
        pages.push(last);

        return pages;
    }

    if (current >= last - 3) {
        pages.push(1);
        pages.push("...");
        for (let page = last - 4; page <= last; page += 1) {
            pages.push(page);
        }

        return pages;
    }

    pages.push(1);
    pages.push("...");
    for (let page = current - 1; page <= current + 1; page += 1) {
        pages.push(page);
    }
    pages.push("...");
    pages.push(last);

    return pages;
});

const handlePageChange = (page) => {
    if (page !== currentPage.value && !props.loading) {
        emit("page-change", page);
    }
};

const handlePerPageChange = (newPerPage) => {
    if (newPerPage !== perPage.value && !props.loading) {
        emit("per-page-change", newPerPage);
    }
};

const goToPrevious = () => {
    if (currentPage.value > 1 && !props.loading) {
        handlePageChange(currentPage.value - 1);
    }
};

const goToNext = () => {
    if (currentPage.value < lastPage.value && !props.loading) {
        handlePageChange(currentPage.value + 1);
    }
};
</script>

<template>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
            <p v-if="showSummary" class="text-sm text-brand-dark">
                Showing {{ from }} - {{ to }} of {{ total }} {{ summaryLabel }}
            </p>

            <div class="flex items-center gap-2 flex-wrap">
                <p class="text-brand-light font-['Plus_Jakarta_Sans'] text-xs sm:text-[14px] font-normal">Show</p>
                <select
                    :value="perPage"
                    @change="handlePerPageChange(parseInt($event.target.value))"
                    :disabled="loading"
                    class="w-full sm:w-auto px-3 py-2 border border-brand-border rounded-lg hover:border-brand-primary focus:border-brand-primary transition-all duration-300 appearance-none disabled:opacity-50 disabled:cursor-not-allowed" style="background-color: var(--color-surface); color: var(--color-brand-dark);"
                >
                    <option
                        v-if="perPage && !perPageOptions.includes(perPage)"
                        :value="perPage"
                    >{{ perPage }}</option>
                    <option v-for="option in perPageOptions" :key="option" :value="option">
                        {{ option }}
                    </option>
                </select>
                <p class="text-brand-light font-['Plus_Jakarta_Sans'] text-xs sm:text-[14px] font-normal">items per page</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 justify-center sm:justify-start">
            <button
                @click="goToPrevious"
                :disabled="currentPage <= 1 || loading"
                class="px-3 sm:px-4 py-2 border border-brand-border text-brand-dark rounded-lg hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
                aria-label="Previous page"
            >
                <ChevronLeft class="w-4 h-4" />
            </button>

            <template v-for="page in visiblePages" :key="page">
                <button
                    v-if="page !== '...'"
                    @click="handlePageChange(page)"
                    :disabled="loading"
                    :aria-label="`Go to page ${page}`"
                    :aria-current="page === currentPage ? 'page' : undefined"
                    :class="[
                        'px-3 sm:px-4 py-2 border rounded-lg font-semibold transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed',
                        page === currentPage
                            ? 'border-primary-700 blue-gradient blue-btn-shadow text-white'
                            : 'border-brand-border text-brand-dark hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50',
                    ]"
                >
                    {{ page }}
                </button>
                <span v-else class="px-2 text-gray-500">...</span>
            </template>

            <button
                @click="goToNext"
                :disabled="currentPage >= lastPage || loading"
                class="px-3 sm:px-4 py-2 border border-brand-border text-brand-dark rounded-lg hover:ring-2 hover:ring-brand-primary/20 hover:bg-gray-50 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
                aria-label="Next page"
            >
                <ChevronRight class="w-4 h-4" />
            </button>
        </div>
    </div>
</template>
