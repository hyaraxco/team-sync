<script setup>
import { ref, computed, watch } from "vue";
import { ChevronLeft, ChevronRight, CalendarDays } from "lucide-vue-next";
import { DateTime } from "luxon";

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
        // { from: 'YYYY-MM-DD', to: 'YYYY-MM-DD' }
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["update:modelValue"]);

const selectedMonth = ref(DateTime.now().startOf("month"));

const monthLabel = computed(() => selectedMonth.value.toFormat("MMMM yyyy"));

const dateRange = computed(() => ({
    from: selectedMonth.value.startOf("month").toISODate(),
    to: selectedMonth.value.endOf("month").toISODate(),
}));

const monthInputValue = computed(() => selectedMonth.value.toFormat("yyyy-MM"));

const navigate = (newMonth) => {
    selectedMonth.value = newMonth;
    emit("update:modelValue", {
        from: newMonth.startOf("month").toISODate(),
        to: newMonth.endOf("month").toISODate(),
    });
};

const goToPrev = () => {
    if (!props.loading) {
        navigate(selectedMonth.value.minus({ months: 1 }));
    }
};

const goToNext = () => {
    if (!props.loading) {
        navigate(selectedMonth.value.plus({ months: 1 }));
    }
};

const goToToday = () => {
    if (!props.loading) {
        navigate(DateTime.now().startOf("month"));
    }
};

const onMonthPickerChange = (event) => {
    const val = event.target.value; // "YYYY-MM"
    if (val) {
        const [year, month] = val.split("-").map(Number);
        navigate(DateTime.fromObject({ year, month, day: 1 }).startOf("month"));
    }
};

// Sync initial value
watch(
    () => props.modelValue,
    (val) => {
        if (val?.from) {
            const dt = DateTime.fromISO(val.from);
            if (dt.isValid) {
                selectedMonth.value = dt.startOf("month");
            }
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <!-- Left: Month/Year display -->
        <div class="flex items-center gap-2">
            <CalendarDays class="w-4 h-4 text-brand-light" />
            <span class="text-sm font-semibold text-brand-dark">{{ monthLabel }}</span>
        </div>

        <!-- Right: Navigation controls -->
        <div class="flex items-center gap-2">
            <!-- Prev -->
            <button
                @click="goToPrev"
                :disabled="loading"
                aria-label="Previous month"
                class="p-2 border border-brand-border rounded-lg hover:bg-brand-border/20 hover:ring-2 hover:ring-brand-primary/20 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <ChevronLeft class="w-4 h-4 text-brand-dark" />
            </button>

            <!-- Today -->
            <button
                @click="goToToday"
                :disabled="loading"
                class="px-3 py-1.5 text-xs font-semibold border border-brand-border rounded-lg hover:bg-brand-border/20 hover:ring-2 hover:ring-brand-primary/20 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                Today
            </button>

            <!-- Month picker -->
            <input
                type="month"
                :value="monthInputValue"
                @change="onMonthPickerChange"
                :disabled="loading"
                class="px-2 py-1.5 text-xs border border-brand-border rounded-lg hover:border-brand-primary focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 transition-all duration-200 bg-white disabled:opacity-50 disabled:cursor-not-allowed"
            />

            <!-- Next -->
            <button
                @click="goToNext"
                :disabled="loading"
                aria-label="Next month"
                class="p-2 border border-brand-border rounded-lg hover:bg-brand-border/20 hover:ring-2 hover:ring-brand-primary/20 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <ChevronRight class="w-4 h-4 text-brand-dark" />
            </button>
        </div>
    </div>
</template>
