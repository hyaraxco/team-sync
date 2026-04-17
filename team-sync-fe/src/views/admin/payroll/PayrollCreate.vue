<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { usePayrollStore } from "@/stores/payroll";
import { useRouter } from "vue-router";
import { useToast } from "@/composables/useToast";
import {
  Calendar,
  Calculator,
  Plus,
  ArrowLeft,
  AlertCircle,
  Search,
} from "lucide-vue-next";

const router = useRouter();
const toast = useToast();
const payrollStore = usePayrollStore();
const loading = computed(() => payrollStore.loading);
const payrolls = computed(() => payrollStore.payrolls ?? []);
const readiness = ref(null);
const readinessDashboard = ref(null);
const readinessLoading = ref(false);
const readinessRequestId = ref(0);
const lastWarnedState = ref(null);
const readinessStatusFilter = ref("all");
const readinessSearchQuery = ref("");
const readinessExpanded = ref(false);
const readinessPreviewLimit = 5;

const readinessReasonLabels = {
  pending_leave_approval: "Pending leave approval",
  sick_proof_unresolved: "Sick proof unresolved",
  missing_attendance_or_valid_leave: "Missing attendance or valid leave",
  invalid_leave_entitlement: "Invalid leave entitlement",
};

const readinessWarningLabels = {
  absent_pct_threshold_reached: "Absent threshold reached",
  unresolved_policy_mismatch: "Unresolved policy mismatch",
  high_late_trend: "High late trend",
  high_half_day_trend: "High half-day trend",
};

const formatDateToMonthKey = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  return `${year}-${month}`;
};

const form = ref({
  salary_month: formatDateToMonthKey(new Date()),
});
const currentMonthKey = formatDateToMonthKey(new Date());

const toMonthKey = (value) => {
  if (!value || typeof value !== "string") return null;

  if (/^\d{4}-\d{2}$/.test(value)) return value;
  if (/^\d{4}-\d{2}-\d{2}/.test(value)) return value.slice(0, 7);

  return null;
};

const existingSalaryMonths = computed(
  () =>
    new Set(
      payrolls.value
        .map((payroll) =>
          toMonthKey(
            payroll?.salary_month ?? payroll?.period ?? payroll?.month ?? null
          )
        )
        .filter(Boolean)
    )
);

const isMonthAlreadyGenerated = computed(() =>
  existingSalaryMonths.value.has(form.value.salary_month)
);
const isFutureMonthSelected = computed(() => {
  if (!form.value.salary_month) return false;
  return form.value.salary_month > currentMonthKey;
});
const readinessReasonCode = computed(() => readiness.value?.reason_code ?? null);
const readinessMessage = computed(() => {
  if (isFutureMonthSelected.value) {
    return "Future payroll months are locked until the period starts.";
  }

  return readiness.value?.message ?? "";
});

const readinessStatusFilters = [
  { value: "all", label: "All" },
  { value: "blocked", label: "Blocked" },
  { value: "warning", label: "Warning" },
  { value: "ready", label: "Ready" },
];

const readinessEmployees = computed(() => readinessDashboard.value?.employees ?? []);
const readinessStatusCounts = computed(() => {
  const employees = readinessEmployees.value;

  return {
    all: employees.length,
    blocked: employees.filter((row) => row.status === "blocked").length,
    warning: employees.filter((row) => row.status === "warning").length,
    ready: employees.filter((row) => row.status === "ready").length,
  };
});

const filteredReadinessEmployees = computed(() => {
  const query = readinessSearchQuery.value.trim().toLowerCase();

  return readinessEmployees.value.filter((row) => {
    if (
      readinessStatusFilter.value !== "all" &&
      row.status !== readinessStatusFilter.value
    ) {
      return false;
    }

    if (!query) {
      return true;
    }

    const searchable = `${row.employee_name ?? ""} ${row.employee_code ?? ""}`.toLowerCase();
    return searchable.includes(query);
  });
});

const visibleReadinessEmployees = computed(() => {
  if (readinessExpanded.value) {
    return filteredReadinessEmployees.value;
  }

  return filteredReadinessEmployees.value.slice(0, readinessPreviewLimit);
});

const hiddenReadinessCount = computed(() =>
  Math.max(
    0,
    filteredReadinessEmployees.value.length - visibleReadinessEmployees.value.length
  )
);

const canToggleReadinessExpansion = computed(
  () => filteredReadinessEmployees.value.length > readinessPreviewLimit
);

const attendancePeriodStatusLabel = computed(() => {
  const status = readinessDashboard.value?.attendance_period?.status;
  const labels = {
    open: "Open",
    review: "Review",
    locked: "Locked",
  };

  return labels[status] ?? "Unknown";
});

const attendancePeriodStatusClass = computed(() => {
  const status = readinessDashboard.value?.attendance_period?.status;

  if (status === "review") return "text-green-700";
  if (status === "open") return "text-amber-700";
  if (status === "locked") return "text-slate-700";

  return "text-slate-500";
});

const attendanceCutoffLabel = computed(() => {
  const cutoffDate = readinessDashboard.value?.attendance_period?.cutoff_date;

  if (!cutoffDate) return "-";

  const parsedDate = new Date(cutoffDate);
  if (Number.isNaN(parsedDate.getTime())) return cutoffDate;

  return parsedDate.toLocaleDateString("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
});

const mapReadinessLabel = (value, dictionary) => {
  if (!value) return "-";
  return (
    dictionary[value] ||
    value
      .split("_")
      .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
      .join(" ")
  );
};

const getStatusLabel = (status) => {
  const labels = {
    ready: "Ready",
    warning: "Warning",
    blocked: "Blocked",
  };

  return labels[status] || "Unknown";
};

const setReadinessStatusFilter = (status) => {
  readinessStatusFilter.value = status;
  readinessExpanded.value = false;
};

const toggleReadinessExpanded = () => {
  readinessExpanded.value = !readinessExpanded.value;
};

const openAttendanceWorkspace = (row) => {
  const target = row?.attendance_workspace_url || "/admin/attendances";
  router.push(target);
};

const findNextAvailableMonth = (startMonth) => {
  const parsedMonth = toMonthKey(startMonth);
  if (!parsedMonth) return startMonth;

  const [year, month] = parsedMonth.split("-").map(Number);
  const cursor = new Date(year, month - 1, 1);

  for (let i = 0; i < 60; i += 1) {
    const nextMonth = formatDateToMonthKey(cursor);
    if (!existingSalaryMonths.value.has(nextMonth)) {
      return nextMonth;
    }
    cursor.setMonth(cursor.getMonth() + 1);
  }

  return parsedMonth;
};

const preloadExistingPayrollMonths = async () => {
  payrollStore.error = null;
  await payrollStore.fetchPayrolls({ page: 1, row_per_page: 500 });

  if (payrollStore.error) {
    toast.warning(
      "Data payroll belum lengkap",
      "Validasi bulan duplikat akan dicek saat submit."
    );
    return;
  }

  if (isMonthAlreadyGenerated.value) {
    form.value.salary_month = findNextAvailableMonth(form.value.salary_month);
  }
};

const refreshGenerateReadiness = async () => {
  const salaryMonth = form.value.salary_month;

  if (!salaryMonth) {
    readiness.value = null;
    readinessDashboard.value = null;
    return;
  }

  if (isFutureMonthSelected.value) {
    readiness.value = {
      can_generate: false,
      reason_code: "future_month",
      message: "Future payroll months are locked until the period starts.",
    };
    readinessDashboard.value = null;
    return;
  }

  const requestId = readinessRequestId.value + 1;
  readinessRequestId.value = requestId;
  readinessLoading.value = true;

  try {
    const response = await payrollStore.fetchGenerateReadiness(salaryMonth);
    if (readinessRequestId.value === requestId) {
      readiness.value = response;
    }

    const dashboard = await payrollStore.fetchReadinessDashboard(salaryMonth);
    if (readinessRequestId.value === requestId) {
      readinessDashboard.value = dashboard;
      readinessExpanded.value = false;
    }
  } catch (error) {
    if (readinessRequestId.value === requestId) {
      readiness.value = null;
      readinessDashboard.value = null;
      readinessExpanded.value = false;
    }
  } finally {
    if (readinessRequestId.value === requestId) {
      readinessLoading.value = false;
    }
  }
};

const parseErrorMessage = (err) =>
  payrollStore.error ||
  err?.response?.data?.data?.message ||
  err?.response?.data?.message ||
  err?.message ||
  "Gagal membuat payroll. Silakan coba lagi.";

const isSubmitDisabled = computed(
  () =>
    loading.value ||
    readinessLoading.value ||
    !form.value.salary_month ||
    isFutureMonthSelected.value ||
    (readiness.value ? !readiness.value.can_generate : false)
);

const handleSubmit = async () => {
  if (!form.value.salary_month) {
    toast.warning("Salary month wajib dipilih");
    return;
  }

  if (isFutureMonthSelected.value) {
    toast.warning(
      "Future month locked",
      `Payroll for ${formatMonth(form.value.salary_month)} cannot be generated yet.`
    );
    return;
  }

  if (readiness.value && !readiness.value.can_generate) {
    toast.warning("Payroll not ready", readiness.value.message);
    return;
  }

  try {
    payrollStore.error = null;
    await payrollStore.generatePayroll(form.value);
    toast.success(
      "Payroll berhasil digenerate",
      `Payroll ${formatMonth(form.value.salary_month)} berhasil dibuat.`
    );
    router.push({ name: "admin.payroll.dashboard" });
  } catch (err) {
    toast.error("Generate payroll gagal", parseErrorMessage(err));
  }
};

const formatMonth = (month) => {
  if (!month) return "-";
  const [year, monthNum] = month.split("-");
  const date = new Date(year, monthNum - 1);
  return date.toLocaleDateString("id-ID", { year: "numeric", month: "long" });
};

onMounted(async () => {
  await preloadExistingPayrollMonths();
  await refreshGenerateReadiness();
});

watch(
  () => form.value.salary_month,
  async (newMonth) => {
    if (!newMonth) return;

    await refreshGenerateReadiness();

    const warningKey = `${newMonth}:${readinessReasonCode.value ?? "ready"}`;
    if (lastWarnedState.value === warningKey) {
      return;
    }

    if (readinessReasonCode.value && readinessReasonCode.value !== "ready") {
      lastWarnedState.value = warningKey;
      toast.warning("Payroll period unavailable", readinessMessage.value);
      return;
    }

    lastWarnedState.value = warningKey;
  }
);
</script>

<template>
  <div class="flex gap-5 items-start">
    <!-- Form Section -->
    <div class="flex-1">
      <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- Payroll Period Section -->
        <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
          <div class="flex items-center gap-3 mb-6">
            <div
              class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center"
            >
              <Calendar class="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-xl font-bold">Generate Payroll</h3>
              <p class="text-brand-light text-sm font-normal">
                Select the salary month to generate payroll for all active employees
              </p>
            </div>
          </div>

          <div class="mb-4">
            <label class="block text-brand-dark text-base font-semibold mb-1"
              >Salary Month *</label
            >
            <div class="relative">
              <div
                class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"
              >
                <Calendar class="h-5 w-5 text-gray-400" />
              </div>
              <input
                type="month"
                v-model="form.salary_month"
                :max="currentMonthKey"
                data-testid="payroll-create-month"
                required
                class="w-full pl-12 pr-4 py-3 border rounded-[16px] transition-all duration-300 font-semibold"
                :class="
                  readiness && !readiness.can_generate
                    ? 'border-red-300 hover:border-red-500 hover:border-2 focus:border-red-500 focus:border-2'
                    : 'border-[#DCDEDD] hover:border-[#0C51D9] hover:border-2 focus:border-[#0C51D9] focus:border-2'
                "
              />
            </div>
            <p
              v-if="readinessMessage"
              data-testid="payroll-create-readiness-message"
              class="text-sm mt-2"
              :class="readiness?.can_generate ? 'text-green-600' : 'text-amber-700'"
            >
              {{ readinessMessage }}
            </p>
          </div>

          <!-- Info Box -->
          <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-[12px] flex items-start gap-3">
            <AlertCircle class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
            <div>
              <h4 class="text-blue-900 text-sm font-semibold mb-1">Automatic Generation</h4>
              <p class="text-blue-800 text-sm">
                Payroll will be automatically generated for all active employees based on their attendance records for the selected month. Salaries will be calculated based on attendance, sick days, and absences.
              </p>
            </div>
          </div>
        </div>

      </form>
    </div>

    <!-- Right Sidebar -->
    <div class="w-100 flex-shrink-0">
      <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6 sticky top-6">
        <div class="flex items-center gap-3 mb-6">
          <div
            class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center"
          >
            <Calculator class="w-6 h-6 text-blue-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-xl font-bold">Payroll Summary</h3>
            <p class="text-brand-light text-sm font-normal">Generation details</p>
          </div>
        </div>

        <div class="space-y-4">
          <div
            class="flex justify-between items-center py-2 bg-blue-50 px-3 rounded-[8px] mb-4"
          >
            <span class="text-blue-700 text-base font-semibold"
              >Selected Month:</span
            >
            <span class="text-blue-700 text-base font-bold">{{
              formatMonth(form.salary_month)
            }}</span>
          </div>

          <div
            v-if="readiness"
            class="rounded-[12px] border px-4 py-3"
            :class="
              readiness.can_generate
                ? 'border-green-200 bg-green-50'
                : 'border-amber-200 bg-amber-50'
            "
          >
            <p
              class="text-sm font-semibold"
              :class="readiness.can_generate ? 'text-green-700' : 'text-amber-700'"
            >
              {{ readiness.can_generate ? "Payroll ready" : "Payroll not ready" }}
            </p>
            <p
              class="text-sm mt-1"
              :class="readiness.can_generate ? 'text-green-700' : 'text-amber-700'"
            >
              {{ readiness.message }}
            </p>
          </div>

          <div
            v-if="readinessDashboard?.summary"
            data-testid="payroll-create-readiness-dashboard"
            class="rounded-[12px] border border-slate-200 bg-slate-50 px-4 py-4"
          >
            <p class="text-slate-800 text-sm font-semibold">Readiness Dashboard</p>
            <div class="grid grid-cols-2 gap-2 mt-3 text-xs">
              <div class="rounded-[8px] bg-white border border-slate-200 px-3 py-2">
                <p class="text-slate-500">Total</p>
                <p class="text-slate-900 font-bold">
                  {{ readinessDashboard.summary.total_employees }}
                </p>
              </div>
              <div class="rounded-[8px] bg-white border border-green-200 px-3 py-2">
                <p class="text-green-600">Ready</p>
                <p class="text-green-700 font-bold">
                  {{ readinessDashboard.summary.ready_employees }}
                </p>
              </div>
              <div class="rounded-[8px] bg-white border border-amber-200 px-3 py-2">
                <p class="text-amber-600">Warning</p>
                <p class="text-amber-700 font-bold">
                  {{ readinessDashboard.summary.warning_employees }}
                </p>
              </div>
              <div class="rounded-[8px] bg-white border border-red-200 px-3 py-2">
                <p class="text-red-600">Blocked</p>
                <p class="text-red-700 font-bold">
                  {{ readinessDashboard.summary.blocked_employees }}
                </p>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-2 mt-2 text-xs">
              <div class="rounded-[8px] bg-white border border-slate-200 px-3 py-2">
                <p class="text-slate-500">Period Status</p>
                <p class="font-bold" :class="attendancePeriodStatusClass">
                  {{ attendancePeriodStatusLabel }}
                </p>
              </div>
              <div class="rounded-[8px] bg-white border border-slate-200 px-3 py-2">
                <p class="text-slate-500">Cut-off Date</p>
                <p class="text-slate-900 font-bold">
                  {{ attendanceCutoffLabel }}
                </p>
              </div>
            </div>

            <div class="mt-4 space-y-3">
              <div class="flex items-center justify-between gap-2">
                <p class="text-slate-700 text-xs font-semibold">Employee workspace</p>
                <p class="text-slate-500 text-[11px]">
                  {{ filteredReadinessEmployees.length }} employee(s)
                </p>
              </div>

              <div class="flex flex-wrap gap-2">
                <button
                  v-for="filter in readinessStatusFilters"
                  :key="filter.value"
                  type="button"
                  :data-testid="`payroll-readiness-filter-${filter.value}`"
                  class="text-[11px] px-3 py-1 rounded-full border transition-all duration-300"
                  :class="
                    readinessStatusFilter === filter.value
                      ? 'border-[#0C51D9] bg-blue-50 text-[#0C51D9]'
                      : 'border-slate-200 bg-white text-slate-600 hover:border-[#0C51D9] hover:text-[#0C51D9]'
                  "
                  @click="setReadinessStatusFilter(filter.value)"
                >
                  {{ filter.label }}
                  <span class="font-semibold">({{ readinessStatusCounts[filter.value] ?? 0 }})</span>
                </button>
              </div>

              <div class="relative">
                <Search
                  class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"
                />
                <input
                  v-model="readinessSearchQuery"
                  data-testid="payroll-readiness-search"
                  type="text"
                  placeholder="Search employee name or code"
                  class="w-full pl-9 pr-3 py-2 text-xs border border-slate-200 rounded-[8px] bg-white focus:border-[#0C51D9] outline-none"
                />
              </div>

              <p
                v-if="filteredReadinessEmployees.length === 0"
                data-testid="payroll-readiness-empty"
                class="text-xs text-slate-500 bg-white border border-dashed border-slate-200 rounded-[8px] px-3 py-3"
              >
                No employees match the current readiness filter.
              </p>

              <div
                v-for="row in visibleReadinessEmployees"
                :key="row.employee_id"
                :data-testid="`payroll-readiness-row-${row.employee_id}`"
                class="rounded-[10px] border bg-white px-3 py-3"
                :class="
                  row.status === 'blocked'
                    ? 'border-red-200'
                    : row.status === 'warning'
                      ? 'border-amber-200'
                      : 'border-slate-200'
                "
              >
                <div class="flex items-start justify-between gap-2">
                  <div>
                    <p class="text-slate-900 text-sm font-semibold">{{ row.employee_name }}</p>
                    <p class="text-slate-500 text-xs">{{ row.employee_code || '-' }}</p>
                  </div>
                  <span
                    class="text-[10px] font-semibold px-2 py-1 rounded-full"
                    :class="
                      row.status === 'blocked'
                        ? 'bg-red-100 text-red-700'
                        : row.status === 'warning'
                          ? 'bg-amber-100 text-amber-700'
                          : 'bg-green-100 text-green-700'
                    "
                  >
                    {{ getStatusLabel(row.status) }}
                  </span>
                </div>

                <div v-if="row.blocker_reasons?.length" class="mt-2 flex flex-wrap gap-1">
                  <span
                    v-for="reason in row.blocker_reasons"
                    :key="`${row.employee_id}-${reason}`"
                    class="text-[10px] px-2 py-1 rounded-full bg-red-100 text-red-700"
                  >
                    {{ mapReadinessLabel(reason, readinessReasonLabels) }}
                  </span>
                </div>

                <div
                  v-if="!row.blocker_reasons?.length && row.warning_flags?.length"
                  class="mt-2 flex flex-wrap gap-1"
                >
                  <span
                    v-for="flag in row.warning_flags"
                    :key="`${row.employee_id}-${flag}`"
                    class="text-[10px] px-2 py-1 rounded-full bg-amber-100 text-amber-700"
                  >
                    {{ mapReadinessLabel(flag, readinessWarningLabels) }}
                  </span>
                </div>

                <button
                  type="button"
                  :data-testid="`payroll-readiness-open-attendance-${row.employee_id}`"
                  @click="openAttendanceWorkspace(row)"
                  class="mt-3 w-full border border-slate-200 rounded-[8px] px-3 py-2 text-xs font-semibold text-slate-700 hover:border-[#0C51D9] hover:text-[#0C51D9] transition-all duration-300"
                >
                  Open Attendance Workspace
                </button>
              </div>

              <button
                v-if="canToggleReadinessExpansion"
                type="button"
                data-testid="payroll-readiness-toggle"
                class="w-full text-xs font-semibold border border-slate-200 bg-white rounded-[8px] px-3 py-2 text-slate-700 hover:border-[#0C51D9] hover:text-[#0C51D9] transition-all duration-300"
                @click="toggleReadinessExpanded"
              >
                {{ readinessExpanded ? 'Show less' : `Show ${hiddenReadinessCount} more` }}
              </button>
            </div>
          </div>

          <!-- Form Actions -->
          <div class="space-y-3 pt-4 border-t border-gray-100">
            <button
              type="submit"
              data-testid="payroll-create-submit"
              @click="handleSubmit"
              :disabled="isSubmitDisabled"
              class="btn-primary w-full rounded-[8px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-6 py-3 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span class="text-brand-white text-base font-semibold">{{
                loading ? "Generating..." : "Generate Payroll"
              }}</span>
              <Plus class="w-4 h-4 text-white" />
            </button>
            <button
              type="button"
              @click="router.back()"
              :disabled="loading"
              class="w-full border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-6 py-3 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <ArrowLeft class="w-4 h-4 text-gray-600" />
              <span class="text-brand-dark text-base font-semibold">Cancel</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
