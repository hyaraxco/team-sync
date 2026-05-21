<template>
    <div class="hybrid-schedules-container p-3 sm:p-4 md:p-6 lg:p-8">
        <div class="max-w-6xl mx-auto space-y-6">
            <header class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-brand-dark">Hybrid Schedule</h1>
                    <p class="text-brand-light text-sm mt-1">
                        View your default weekly schedule and request day-specific overrides.
                    </p>
                </div>
                <button
                    @click="showOverrideModal = true"
                    class="px-4 py-2 rounded-lg blue-gradient blue-btn-shadow text-white font-medium text-sm hover:brightness-110 transition-all cursor-pointer"
                >
                    Request Override
                </button>
            </header>

            <div class="space-y-6">
                <!-- Error State -->
                <div
                    v-if="error"
                    class="bg-white border border-red-200 rounded-2xl p-6 flex items-center gap-3 text-red-600"
                >
                    <AlertTriangle class="w-5 h-5 shrink-0" />
                    <p>Failed to load schedule. The service might be temporarily unavailable.</p>
                </div>

                <!-- Loading State -->
                <div v-else-if="loading" class="space-y-4">
                    <div v-for="i in 5" :key="i" class="h-14 bg-gray-100 rounded-xl animate-pulse" />
                </div>

                <div v-else class="grid gap-6 lg:grid-cols-2">
                    <div class="space-y-4">
                        <h2 class="text-lg font-bold text-brand-dark flex items-center gap-2">
                            <Calendar class="w-5 h-5 text-blue-500" />
                            Base Schedule
                        </h2>
                        <div class="space-y-2">
                            <div
                                v-for="(location, day) in baseSchedule"
                                :key="day"
                                class="flex items-center justify-between p-3 bg-white border border-brand-border rounded-xl hover:ring-2 hover:ring-brand-primary/20 transition-colors"
                            >
                                <span class="font-medium text-brand-dark capitalize">{{ day }}</span>
                                <span
                                    class="px-2 py-1 text-xs font-semibold uppercase rounded-full border"
                                    :class="
                                        location === 'office'
                                            ? 'bg-blue-50 border-blue-200 text-blue-700'
                                            : 'bg-purple-50 border-purple-200 text-purple-700'
                                    "
                                >
                                    {{ location }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h2 class="text-lg font-bold text-brand-dark flex items-center gap-2">
                            <RefreshCw class="w-5 h-5 text-amber-500" />
                            Overrides & Exceptions
                        </h2>

                        <div
                            v-if="!schedule.overrides?.length"
                            class="bg-white border border-brand-border rounded-2xl p-8 text-center"
                        >
                            <p class="text-brand-light">Belum ada pengajuan override.</p>
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="override in schedule.overrides"
                                :key="override.id"
                                class="p-4 bg-white border border-brand-border rounded-xl"
                            >
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <p class="font-medium text-brand-dark">{{ override.date }}</p>
                                        <p class="text-sm text-brand-light">
                                            Change to:
                                            <span class="text-brand-dark font-medium">
                                                {{ override.planned_work_mode || override.requested_location }}
                                            </span>
                                        </p>
                                    </div>
                                    <span
                                        class="px-2 py-1 text-[10px] font-bold uppercase rounded-full bg-yellow-50 text-yellow-700 border border-yellow-200"
                                    >
                                        {{ override.status }}
                                    </span>
                                </div>

                                <p v-if="override.reason" class="text-xs text-brand-light mt-2 border-t border-gray-100 pt-2">
                                    Reason: {{ override.reason }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Override Modal -->
    <div v-if="showOverrideModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md bg-white rounded-2xl p-6 space-y-4">
            <h2 class="text-xl font-bold text-brand-dark">Request Schedule Override</h2>
            <form @submit.prevent="submitOverride" class="space-y-4">
                <div>
                    <label for="override-date" class="block text-sm font-medium text-brand-dark mb-1">Date</label>
                    <input
                        id="override-date"
                        v-model="overrideForm.date"
                        type="date"
                        required
                        class="w-full border border-brand-border rounded-lg px-3 py-2 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary outline-none"
                    />
                </div>
                <div>
                    <label for="override-mode" class="block text-sm font-medium text-brand-dark mb-1">Requested Work Mode</label>
                    <select
                        id="override-mode"
                        v-model="overrideForm.planned_work_mode"
                        required
                        class="w-full border border-brand-border rounded-lg px-3 py-2 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary outline-none"
                    >
                        <option value="office">Office</option>
                        <option value="remote">Remote</option>
                    </select>
                </div>
                <div>
                    <label for="override-reason" class="block text-sm font-medium text-brand-dark mb-1">Reason</label>
                    <textarea
                        id="override-reason"
                        v-model="overrideForm.reason"
                        rows="3"
                        required
                        class="w-full border border-brand-border rounded-lg px-3 py-2 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary outline-none"
                    ></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-lg border border-brand-border text-brand-dark hover:bg-gray-50 transition-colors cursor-pointer"
                        @click="showOverrideModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="submittingOverride"
                        class="px-4 py-2 rounded-lg blue-gradient blue-btn-shadow text-white hover:brightness-110 transition-all disabled:opacity-50 cursor-pointer"
                    >
                        {{ submittingOverride ? "Submitting..." : "Submit" }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from "vue";
import { useHybridScheduleStore } from "@/stores/hybridSchedule";
import { useToast } from "@/composables/useToast";
import { Calendar, RefreshCw, AlertTriangle } from "lucide-vue-next";

const scheduleStore = useHybridScheduleStore();
const toast = useToast();
const schedule = ref({ base_schedule: {}, overrides: [] });
const loading = ref(true);
const error = ref(false);
const showOverrideModal = ref(false);
const submittingOverride = ref(false);
const overrideForm = ref({
    date: "",
    planned_work_mode: "remote",
    reason: "",
});

const baseSchedule = computed(
    () =>
        schedule.value?.base_schedule || {
            monday: schedule.value?.monday,
            tuesday: schedule.value?.tuesday,
            wednesday: schedule.value?.wednesday,
            thursday: schedule.value?.thursday,
            friday: schedule.value?.friday,
        },
);

const loadSchedule = async () => {
    const response = await scheduleStore.fetchMySchedule();
    schedule.value = response?.data || { base_schedule: {}, overrides: [] };
};

onMounted(async () => {
    try {
        await loadSchedule();
    } catch (err) {
        toast.error(
            "Failed to load schedule",
            scheduleStore.error || err?.response?.data?.message || "Failed to load schedule.",
        );
        error.value = true;
    } finally {
        loading.value = false;
    }
});

const submitOverride = async () => {
    submittingOverride.value = true;
    try {
        await scheduleStore.createOverride(overrideForm.value);
        toast.success("Override requested", "Your schedule override request has been submitted.");
        showOverrideModal.value = false;
        overrideForm.value = { date: "", planned_work_mode: "remote", reason: "" };
        await loadSchedule();
    } catch (err) {
        toast.error(
            "Failed to submit override",
            scheduleStore.error || err?.response?.data?.message || "Failed to submit override.",
        );
    } finally {
        submittingOverride.value = false;
    }
};
</script>

