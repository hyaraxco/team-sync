<template>
    <div class="hybrid-schedules-container min-h-screen bg-neutral-900 text-neutral-100 p-8">
        <div class="max-w-6xl mx-auto space-y-8 relative">
            <div
                class="absolute top-0 right-0 -mr-32 -mt-32 w-96 h-96 bg-cyan-600/20 rounded-full blur-[120px] pointer-events-none"
            ></div>

            <header
                class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-end gap-6 border-b border-white/30 pb-8"
            >
                <div class="space-y-2">
                    <h1
                        class="text-5xl font-extralight tracking-tight font-display bg-clip-text text-transparent bg-gradient-to-r from-white to-neutral-500"
                    >
                        Hybrid Schedule
                    </h1>
                    <p class="text-neutral-400 font-light tracking-wide max-w-xl">
                        View your default weekly schedule and request day-specific overrides.
                    </p>
                </div>
                <button
                    @click="showOverrideModal = true"
                    class="px-6 py-2.5 rounded-full bg-white text-black font-medium text-sm hover:scale-105 transition-transform duration-300 shadow-lg shadow-white/10"
                >
                    Request Override
                </button>
            </header>

            <div class="relative z-10 space-y-8">
                <div
                    v-if="error"
                    class="p-6 rounded-2xl border border-rose-500/20 bg-rose-500/5 text-rose-400 flex items-center gap-3"
                >
                    <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                        ></path>
                    </svg>
                    <p>Failed to load schedule. The service might be temporarily unavailable.</p>
                </div>

                <div
                    v-else-if="loading"
                    class="flex justify-center p-16 border border-white/15 rounded-3xl bg-white/[0.06]"
                >
                    <svg
                        class="animate-spin w-8 h-8 text-cyan-500"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        ></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
                </div>

                <div v-else class="grid gap-8 lg:grid-cols-2">
                    <div class="space-y-4">
                        <h2 class="text-xl font-light mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                ></path>
                            </svg>
                            Base Schedule
                        </h2>
                        <div class="grid gap-3">
                            <div
                                v-for="(location, day) in baseSchedule"
                                :key="day"
                                class="flex items-center justify-between p-4 rounded-xl bg-white/[0.08] border border-white/15 hover:bg-white/[0.12] transition-colors"
                            >
                                <span class="font-medium text-white capitalize">{{ day }}</span>
                                <span
                                    class="px-3 py-1 text-xs font-semibold uppercase tracking-wider rounded-full border"
                                    :class="
                                        location === 'office'
                                            ? 'bg-cyan-500/10 border-cyan-500/20 text-cyan-400'
                                            : 'bg-purple-500/10 border-purple-500/20 text-purple-400'
                                    "
                                >
                                    {{ location }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h2 class="text-xl font-light mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                ></path>
                            </svg>
                            Overrides & Exceptions
                        </h2>

                        <div
                            v-if="!schedule.overrides?.length"
                            class="text-center p-8 border border-dashed border-white/30 rounded-2xl bg-white/[0.06] text-neutral-500"
                        >
                            <p class="font-light italic">No overrides requested.</p>
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="override in schedule.overrides"
                                :key="override.id"
                                class="p-4 rounded-xl bg-white/[0.08] border border-white/15"
                            >
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <p class="font-medium text-white">{{ override.date }}</p>
                                        <p class="text-sm text-neutral-400">
                                            Change to:
                                            <span class="text-white">
                                                {{ override.planned_work_mode || override.requested_location }}
                                            </span>
                                        </p>
                                    </div>
                                    <span
                                        class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider rounded bg-amber-500/10 text-amber-400 border border-amber-500/20"
                                    >
                                        {{ override.status }}
                                    </span>
                                </div>
                                <p class="text-xs text-neutral-500 italic mt-2 border-t border-white/15 pt-2">
                                    Reason: {{ override.reason }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showOverrideModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
            <form
                class="w-full max-w-md rounded-2xl bg-white p-6 text-neutral-900 space-y-4"
                @submit.prevent="submitOverride"
            >
                <h2 class="text-xl font-semibold">Request Schedule Override</h2>
                <div>
                    <label class="block text-sm font-medium mb-1">Date</label>
                    <input
                        v-model="overrideForm.date"
                        type="date"
                        required
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Requested Work Mode</label>
                    <select
                        v-model="overrideForm.planned_work_mode"
                        required
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2"
                    >
                        <option value="office">Office</option>
                        <option value="remote">Remote</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Reason</label>
                    <textarea
                        v-model="overrideForm.reason"
                        rows="3"
                        required
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2"
                    ></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button
                        type="button"
                        class="rounded-lg border border-neutral-300 px-4 py-2"
                        @click="showOverrideModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="submittingOverride"
                        class="rounded-lg bg-neutral-900 px-4 py-2 text-white disabled:opacity-50"
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

<style scoped>
.font-display {
    font-family: "Outfit", "Inter", sans-serif;
}
</style>
