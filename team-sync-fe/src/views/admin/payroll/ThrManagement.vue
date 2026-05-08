<script setup>
import { onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { Gift, Check, Plus, DollarSign, Users, AlertCircle } from "lucide-vue-next";
import { useThrStore } from "@/stores/thr";
import { formatDateShort } from "@/utils/dateUtils";
import Pagination from "@/components/admin/team/Pagination.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import { useToast } from "@/composables/useToast";
import { can } from "@/helpers/permissionHelper";

const store = useThrStore();
const { thrPayrolls, yearSummary, simulation, meta, loading } = storeToRefs(store);
const toast = useToast();

const selectedYear = ref(new Date().getFullYear());
const showGenerateModal = ref(false);
const showSimulationModal = ref(false);
const showPaymentModal = ref(false);
const selectedThr = ref(null);
const paymentDate = ref("");

const generateForm = ref({
    religion_event: "",
    year: new Date().getFullYear(),
    religion_holiday_date: "",
    notes: "",
});

const religionEvents = [
    { value: "idul_fitri", label: "Idul Fitri (Islam)" },
    { value: "natal", label: "Natal / Christmas (Kristen/Katolik)" },
    { value: "nyepi", label: "Nyepi (Hindu)" },
    { value: "waisak", label: "Waisak (Buddha)" },
    { value: "imlek", label: "Imlek (Konghucu)" },
];

const statusColors = {
    draft: "bg-gray-100 text-gray-700",
    processing: "bg-blue-100 text-blue-700",
    pending: "bg-yellow-100 text-yellow-700",
    approved: "bg-green-100 text-green-700",
    paid: "bg-emerald-100 text-emerald-800",
};

onMounted(async () => {
    await fetchData();
});

async function fetchData() {
    await Promise.all([
        store.fetchThrPayrolls({ year: selectedYear.value }),
        store.fetchYearSummary(selectedYear.value),
    ]);
}

async function handleYearChange() {
    await fetchData();
}

async function handleSimulate() {
    if (!generateForm.value.religion_event || !generateForm.value.religion_holiday_date) {
        toast.error("Please fill in religion event and holiday date");
        return;
    }
    try {
        await store.simulate({
            religion_event: generateForm.value.religion_event,
            year: generateForm.value.year,
            religion_holiday_date: generateForm.value.religion_holiday_date,
        });
        showSimulationModal.value = true;
    } catch (_e) {
        toast.error(store.error || "Simulation failed");
    }
}

async function handleGenerate() {
    try {
        const result = await store.generate(generateForm.value);
        toast.success(result.message || "THR generated successfully");
        showGenerateModal.value = false;
        showSimulationModal.value = false;
        resetForm();
        await fetchData();
    } catch (_e) {
        toast.error(store.error || "Generation failed");
    }
}

async function handleApprove(thr) {
    if (!confirm(`Approve THR ${thr.event_label} ${thr.year}?`)) return;
    try {
        const result = await store.approve(thr.id);
        toast.success(result.message || "THR approved");
        await fetchData();
    } catch (_e) {
        toast.error(store.error || "Approval failed");
    }
}

function openPaymentModal(thr) {
    selectedThr.value = thr;
    paymentDate.value = new Date().toISOString().split("T")[0];
    showPaymentModal.value = true;
}

async function handleMarkAsPaid() {
    if (!selectedThr.value || !paymentDate.value) return;
    try {
        const result = await store.markAsPaid(selectedThr.value.id, paymentDate.value);
        toast.success(result.message || "THR marked as paid");
        showPaymentModal.value = false;
        selectedThr.value = null;
        await fetchData();
    } catch (_e) {
        toast.error(store.error || "Failed to mark as paid");
    }
}

function resetForm() {
    generateForm.value = {
        religion_event: "",
        year: selectedYear.value,
        religion_holiday_date: "",
        notes: "",
    };
}

function formatCurrency(amount) {
    return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", minimumFractionDigits: 0 }).format(
        amount,
    );
}

function handlePageChange(page) {
    store.fetchThrPayrolls({ year: selectedYear.value, page });
}
</script>

<template>
    <div class="p-6 space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">THR Management</h1>
                <p class="text-sm text-gray-500 mt-1">Tunjangan Hari Raya — Religious Holiday Bonus</p>
            </div>
            <div class="flex items-center gap-3">
                <select v-model="selectedYear" @change="handleYearChange" class="rounded-lg border-gray-300 text-sm">
                    <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
                </select>
                <button
                    v-if="can('thr-generate')"
                    @click="showGenerateModal = true"
                    class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition"
                >
                    <Plus :size="16" />
                    Generate THR
                </button>
            </div>
        </div>

        <!-- Year Summary Cards -->
        <div v-if="yearSummary" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-50 rounded-lg">
                        <Gift :size="20" class="text-indigo-600" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Total Events</p>
                        <p class="text-lg font-bold">{{ yearSummary.total_events }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-green-50 rounded-lg">
                        <Users :size="20" class="text-green-600" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Total Employees</p>
                        <p class="text-lg font-bold">{{ yearSummary.total_employees }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-blue-50 rounded-lg">
                        <DollarSign :size="20" class="text-blue-600" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Total THR (Gross)</p>
                        <p class="text-lg font-bold">{{ formatCurrency(yearSummary.total_thr_amount) }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-emerald-50 rounded-lg">
                        <DollarSign :size="20" class="text-emerald-600" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Total Net</p>
                        <p class="text-lg font-bold">{{ formatCurrency(yearSummary.total_net_amount) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- THR Payrolls Table -->
        <div class="bg-white rounded-xl border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Event</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Year</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Holiday Date</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Payment Deadline</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Employees</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Total Net</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="thr in thrPayrolls" :key="thr.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium">{{ thr.event_label }}</td>
                            <td class="px-4 py-3">{{ thr.year }}</td>
                            <td class="px-4 py-3">{{ formatDateShort(thr.religion_holiday_date) }}</td>
                            <td class="px-4 py-3">{{ formatDateShort(thr.payment_deadline) }}</td>
                            <td class="px-4 py-3">{{ thr.total_employees }}</td>
                            <td class="px-4 py-3 font-medium">{{ formatCurrency(thr.total_net_amount) }}</td>
                            <td class="px-4 py-3">
                                <span :class="['px-2 py-1 rounded-full text-xs font-medium', statusColors[thr.status]]">
                                    {{ thr.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <router-link
                                        :to="{ name: 'admin.payroll.thr.detail', params: { id: thr.id } }"
                                        class="text-indigo-600 hover:text-indigo-800 text-xs font-medium"
                                    >
                                        View
                                    </router-link>
                                    <button
                                        v-if="thr.status === 'pending' && can('thr-approve')"
                                        @click="handleApprove(thr)"
                                        class="text-green-600 hover:text-green-800"
                                        title="Approve"
                                    >
                                        <Check :size="16" />
                                    </button>
                                    <button
                                        v-if="thr.status === 'approved' && can('thr-process')"
                                        @click="openPaymentModal(thr)"
                                        class="text-emerald-600 hover:text-emerald-800 text-xs font-medium"
                                    >
                                        Mark Paid
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState
                v-if="!loading && thrPayrolls.length === 0"
                title="No THR Payrolls"
                description="Generate THR for a religious holiday event to get started."
            />
        </div>

        <Pagination v-if="meta && meta.last_page > 1" :meta="meta" @page-change="handlePageChange" />

        <!-- Generate Modal -->
        <ModalWrapper :show="showGenerateModal" title="Generate THR" @close="showGenerateModal = false">
            <div class="space-y-4 p-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Religion Event</label>
                    <select v-model="generateForm.religion_event" class="w-full rounded-lg border-gray-300">
                        <option value="">Select event...</option>
                        <option v-for="event in religionEvents" :key="event.value" :value="event.value">
                            {{ event.label }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                    <input v-model.number="generateForm.year" type="number" class="w-full rounded-lg border-gray-300" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Holiday Date</label>
                    <input
                        v-model="generateForm.religion_holiday_date"
                        type="date"
                        class="w-full rounded-lg border-gray-300"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                    <textarea
                        v-model="generateForm.notes"
                        rows="2"
                        class="w-full rounded-lg border-gray-300"
                    ></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button
                        @click="handleSimulate"
                        :disabled="loading"
                        class="px-4 py-2 border border-indigo-600 text-indigo-600 rounded-lg hover:bg-indigo-50 transition"
                    >
                        Preview / Simulate
                    </button>
                    <button
                        @click="handleGenerate"
                        :disabled="loading"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition"
                    >
                        Generate THR
                    </button>
                </div>
            </div>
        </ModalWrapper>

        <!-- Simulation Results Modal -->
        <ModalWrapper :show="showSimulationModal" title="THR Simulation Preview" @close="showSimulationModal = false">
            <div v-if="simulation" class="p-4 space-y-4">
                <div class="grid grid-cols-3 gap-4">
                    <div class="text-center p-3 bg-green-50 rounded-lg">
                        <p class="text-xs text-gray-500">Eligible</p>
                        <p class="text-xl font-bold text-green-700">{{ simulation.eligible_count }}</p>
                    </div>
                    <div class="text-center p-3 bg-red-50 rounded-lg">
                        <p class="text-xs text-gray-500">Ineligible</p>
                        <p class="text-xl font-bold text-red-700">{{ simulation.ineligible_count }}</p>
                    </div>
                    <div class="text-center p-3 bg-blue-50 rounded-lg">
                        <p class="text-xs text-gray-500">Total Net</p>
                        <p class="text-lg font-bold text-blue-700">{{ formatCurrency(simulation.total_net_amount) }}</p>
                    </div>
                </div>

                <div class="border-t pt-3">
                    <p class="text-sm font-medium mb-2">Payment Deadline: {{ simulation.payment_deadline }}</p>
                    <p class="text-sm text-gray-500">
                        Total Gross: {{ formatCurrency(simulation.total_gross_amount) }}
                    </p>
                    <p class="text-sm text-gray-500">Total Tax: {{ formatCurrency(simulation.total_tax_amount) }}</p>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button @click="showSimulationModal = false" class="px-4 py-2 border rounded-lg">Close</button>
                    <button
                        @click="handleGenerate"
                        :disabled="loading"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                    >
                        Confirm & Generate
                    </button>
                </div>
            </div>
        </ModalWrapper>

        <!-- Payment Modal -->
        <ModalWrapper :show="showPaymentModal" title="Mark THR as Paid" @close="showPaymentModal = false">
            <div class="p-4 space-y-4">
                <p class="text-sm text-gray-600">
                    Mark
                    <strong>{{ selectedThr?.event_label }} {{ selectedThr?.year }}</strong>
                    as paid for
                    <strong>{{ selectedThr?.total_employees }}</strong>
                    employees.
                </p>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
                    <input v-model="paymentDate" type="date" class="w-full rounded-lg border-gray-300" />
                </div>
                <div class="flex items-center gap-2 p-3 bg-yellow-50 rounded-lg">
                    <AlertCircle :size="16" class="text-yellow-600" />
                    <p class="text-xs text-yellow-700">
                        This will send notifications to all {{ selectedThr?.total_employees }} employees.
                    </p>
                </div>
                <div class="flex justify-end gap-3">
                    <button @click="showPaymentModal = false" class="px-4 py-2 border rounded-lg">Cancel</button>
                    <button
                        @click="handleMarkAsPaid"
                        :disabled="loading"
                        class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700"
                    >
                        Confirm Payment
                    </button>
                </div>
            </div>
        </ModalWrapper>
    </div>
</template>
