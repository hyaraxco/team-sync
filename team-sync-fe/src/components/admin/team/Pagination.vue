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
    }),
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["page-change", "per-page-change"]);

const currentPage = computed(() => props.meta.current_page);
const lastPage = computed(() => props.meta.last_page);
const perPage = computed(() => props.meta.per_page);
const total = computed(() => props.meta.total);

const perPageOptions = [10, 12, 24, 48];

// Calculate visible page numbers
const visiblePages = computed(() => {
  const pages = [];
  const current = currentPage.value;
  const last = lastPage.value;

  if (last <= 7) {
    // Show all pages if total pages <= 7
    for (let i = 1; i <= last; i++) {
      pages.push(i);
    }
  } else {
    // Show pages with ellipsis
    if (current <= 4) {
      // Show first 5 pages + ellipsis + last page
      for (let i = 1; i <= 5; i++) {
        pages.push(i);
      }
      pages.push("...");
      pages.push(last);
    } else if (current >= last - 3) {
      // Show first page + ellipsis + last 5 pages
      pages.push(1);
      pages.push("...");
      for (let i = last - 4; i <= last; i++) {
        pages.push(i);
      }
    } else {
      // Show first page + ellipsis + current-1, current, current+1 + ellipsis + last page
      pages.push(1);
      pages.push("...");
      for (let i = current - 1; i <= current + 1; i++) {
        pages.push(i);
      }
      pages.push("...");
      pages.push(last);
    }
  }

  return pages;
});

const handlePageChange = (page) => {
  if (page !== currentPage.value && !props.loading) {
    console.log("Page changed to:", page);
    emit("page-change", page);
  }
};

const handlePerPageChange = (newPerPage) => {
  if (newPerPage !== perPage.value && !props.loading) {
    console.log("Per page changed to:", newPerPage);
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
    <!-- Per Page Selector -->
    <div class="flex items-center gap-2 flex-wrap">
      <p
        class="text-[#6B7280] font-['Plus_Jakarta_Sans'] text-xs sm:text-[14px] font-normal"
      >
        Show
      </p>
      <select
        :value="perPage"
        @change="handlePerPageChange(parseInt($event.target.value))"
        :disabled="loading"
        class="w-full sm:w-auto px-3 py-2 border border-[#DCDEDD] rounded-lg hover:border-[#0C51D9] focus:border-[#0C51D9] transition-all duration-300 bg-white appearance-none disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <option v-for="option in perPageOptions" :key="option" :value="option">
          {{ option }}
        </option>
      </select>
      <p
        class="text-[#6B7280] font-['Plus_Jakarta_Sans'] text-xs sm:text-[14px] font-normal"
      >
        teams per page
      </p>
    </div>

    <!-- Pagination Controls -->
    <div class="flex flex-wrap items-center gap-2 justify-center sm:justify-start">
      <!-- Previous Button -->
      <button
        @click="goToPrevious"
        :disabled="currentPage <= 1 || loading"
        class="px-3 sm:px-4 py-2 border border-[#DCDEDD] rounded-lg hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <ChevronLeft class="w-4 h-4" />
      </button>

      <!-- Page Numbers -->
      <template v-for="page in visiblePages" :key="page">
        <button
          v-if="page !== '...'"
          @click="handlePageChange(page)"
          :disabled="loading"
          :class="[
            'px-3 sm:px-4 py-2 border rounded-lg font-semibold transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed',
            page === currentPage
              ? 'border-[#2151A0] blue-gradient blue-btn-shadow text-white'
              : 'border-[#DCDEDD] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50',
          ]"
        >
          {{ page }}
        </button>
        <span v-else class="px-2 text-gray-500">...</span>
      </template>

      <!-- Next Button -->
      <button
        @click="goToNext"
        :disabled="currentPage >= lastPage || loading"
        class="px-3 sm:px-4 py-2 border border-[#DCDEDD] rounded-lg hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <ChevronRight class="w-4 h-4" />
      </button>
    </div>
  </div>
</template>
