<script setup>
import { ref, watch } from "vue";
import { X, Clock } from "lucide-vue-next";

const props = defineProps({
    show: Boolean,
    attendanceRecord: Object,
    loading: Boolean,
});

const emit = defineEmits(["close", "submit"]);

const form = ref({
    requested_check_in: "",
    requested_check_out: "",
    reason: "",
});

watch(
    () => props.show,
    (newVal) => {
        if (newVal && props.attendanceRecord) {
            form.value.requested_check_in = props.attendanceRecord.check_in
                ? formatDateTimeLocal(props.attendanceRecord.check_in)
                : "";
            form.value.requested_check_out = props.attendanceRecord.check_out
                ? formatDateTimeLocal(props.attendanceRecord.check_out)
                : "";
            form.value.reason = "";
        }
    },
);

function formatDateTimeLocal(dateString) {
    const date = new Date(dateString);
    const offset = date.getTimezoneOffset() * 60000;
    const localIso = new Date(date.getTime() - offset).toISOString().slice(0, 16);
    return localIso;
}

const handleSubmit = () => {
    if (!form.value.reason) {
        return;
    }

    // Combine to format standard API accepts
    const payload = {
        attendance_id: props.attendanceRecord.id,
        requested_check_in: form.value.requested_check_in
            ? new Date(form.value.requested_check_in).toISOString()
            : null,
        requested_check_out: form.value.requested_check_out
            ? new Date(form.value.requested_check_out).toISOString()
            : null,
        reason: form.value.reason,
    };
    emit("submit", payload);
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-brand-dark/50 p-4">
        <div class="bg-white rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl">
            <div class="px-6 py-4 flex items-center justify-between border-b">
                <h3 class="text-lg font-bold text-brand-dark">Ajukan Koreksi</h3>
                <button @click="$emit('close')" class="text-brand-light hover:text-brand-dark transition-colors">
                    <X class="w-5 h-5" />
                </button>
            </div>

            <div class="p-6">
                <p class="text-sm text-brand-light mb-4">
                    Please correct your clocking times below. Your request will be sent to HR/Manager.
                </p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-brand-dark mb-1">Check In Time</label>
                        <input
                            v-model="form.requested_check_in"
                            type="datetime-local"
                            class="w-full px-4 py-2 border rounded-lg"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-brand-dark mb-1">Check Out Time</label>
                        <input
                            v-model="form.requested_check_out"
                            type="datetime-local"
                            class="w-full px-4 py-2 border rounded-lg"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-brand-dark mb-1">Reason</label>
                        <textarea
                            v-model="form.reason"
                            class="w-full px-4 py-2 border rounded-lg"
                            rows="3"
                            placeholder="Why are you making this correction?"
                            required
                        ></textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="px-5 py-2.5 text-brand-light font-medium border rounded-lg hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        @click="handleSubmit"
                        :disabled="loading || !form.reason"
                        class="btn-primary blue-gradient blue-btn-shadow px-6 py-2.5 text-white font-semibold rounded-lg flex items-center justify-center gap-2 disabled:opacity-50"
                    >
                        <Clock class="w-4 h-4" v-if="!loading" />
                        <span>{{ loading ? "Submitting..." : "Submit Request" }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
