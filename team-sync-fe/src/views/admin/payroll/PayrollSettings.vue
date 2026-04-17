<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { usePayrollStore } from "@/stores/payroll";
import { useToast } from "@/composables/useToast";
import { storeToRefs } from "pinia";
import {
  Settings,
  Calendar,
  Calculator,
  FileText,
  History,
  ArrowLeft,
} from "lucide-vue-next";

const router = useRouter();
const payrollStore = usePayrollStore();
const toast = useToast();
const { loading, settings } = storeToRefs(payrollStore);

const fallbackSettings = {
  payday_day: 25,
  attendance_cutoff_day: 25,
  working_days_mode: "auto_business_days",
  default_working_days: 22,
  absent_deduction_rate: 1,
  rounding_mode: "nearest",
  rounding_unit: 1000,
  note_template:
    "Hari kerja: {working_days} | Hadir: {attended_days} | Terlambat: {late_days} | Sakit: {sick_days} | Izin: {permission_days} | Alpha: {absent_days} | Potongan: Rp {deduction}",
};

const form = reactive({ ...fallbackSettings });
const settingsHistory = ref([]);
const loadingHistory = ref(false);
const selectedHistoryVersionId = ref(null);

const trackedVersionFields = [
  "payday_day",
  "attendance_cutoff_day",
  "working_days_mode",
  "default_working_days",
  "absent_deduction_rate",
  "rounding_mode",
  "rounding_unit",
  "note_template",
];

const versionFieldLabels = {
  payday_day: "Payday day",
  attendance_cutoff_day: "Attendance cut-off",
  working_days_mode: "Working days mode",
  default_working_days: "Default working days",
  absent_deduction_rate: "Absent deduction rate",
  rounding_mode: "Rounding mode",
  rounding_unit: "Rounding unit",
  note_template: "Note template",
};

const hydrateForm = (payload = {}) => {
  Object.assign(form, {
    ...fallbackSettings,
    ...payload,
  });
};

const lastUpdatedLabel = computed(() => {
  if (!settings.value?.updated_at) {
    return "Default system settings";
  }

  const updatedAt = new Date(settings.value.updated_at).toLocaleString("id-ID");
  const actor = settings.value.updated_by?.name || "System";
  return `Updated by ${actor} on ${updatedAt}`;
});

const activeVersionLabel = computed(() => {
  const versionNumber = settings.value?.active_version?.version_number;

  if (!versionNumber) {
    return "Legacy";
  }

  return `v${versionNumber}`;
});

const notePreview = computed(() => {
  const previewTemplate = form.note_template || fallbackSettings.note_template;

  return previewTemplate
    .replaceAll(
      "{working_days}",
      String(
        form.working_days_mode === "fixed" ? form.default_working_days : 22,
      ),
    )
    .replaceAll("{attended_days}", "20")
    .replaceAll("{late_days}", "1")
    .replaceAll("{sick_days}", "1")
    .replaceAll("{permission_days}", "0")
    .replaceAll("{absent_days}", "2")
    .replaceAll("{deduction}", "1.500.000");
});

const loadSettings = async () => {
  try {
    const payload = await payrollStore.fetchSettings();
    hydrateForm(payload);
  } catch (error) {
    toast.error(
      "Failed to load payroll settings",
      payrollStore.error || "Please try again.",
    );
  }
};

const loadSettingsHistory = async () => {
  try {
    loadingHistory.value = true;
    settingsHistory.value = await payrollStore.fetchSettingsHistory();
    selectedHistoryVersionId.value = settingsHistory.value[0]?.id ?? null;
  } catch (error) {
    toast.error(
      "Failed to load payroll settings history",
      payrollStore.error || "Please try again.",
    );
    settingsHistory.value = [];
    selectedHistoryVersionId.value = null;
  } finally {
    loadingHistory.value = false;
  }
};

const formatHistoryDate = (value) => {
  if (!value) {
    return "Unknown date";
  }

  return new Date(value).toLocaleString("id-ID", {
    year: "numeric",
    month: "short",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const formatWorkingDaysMode = (mode) => {
  if (mode === "auto_business_days") {
    return "Auto business days";
  }

  if (mode === "fixed") {
    return "Fixed";
  }

  return "Unknown";
};

const formatComparisonValue = (field, value) => {
  if (value === null || value === undefined || value === "") {
    return "-";
  }

  if (field === "working_days_mode") {
    return formatWorkingDaysMode(value);
  }

  if (field === "absent_deduction_rate") {
    return Number(value).toFixed(2);
  }

  if (field === "note_template") {
    const normalized = String(value).trim();

    return normalized.length > 80
      ? `${normalized.slice(0, 80)}...`
      : normalized;
  }

  return String(value);
};

const normalizeComparisonValue = (field, value) => {
  if (field === "absent_deduction_rate") {
    return Number(value || 0).toFixed(2);
  }

  if (field === "note_template") {
    return String(value || "").trim();
  }

  return String(value ?? "");
};

const selectedHistoryVersionIndex = computed(() =>
  settingsHistory.value.findIndex(
    (version) => version.id === selectedHistoryVersionId.value,
  ),
);

const selectedHistoryVersion = computed(() => {
  if (selectedHistoryVersionIndex.value === -1) {
    return null;
  }

  return settingsHistory.value[selectedHistoryVersionIndex.value] ?? null;
});

const previousHistoryVersion = computed(() => {
  if (selectedHistoryVersionIndex.value === -1) {
    return null;
  }

  return settingsHistory.value[selectedHistoryVersionIndex.value + 1] ?? null;
});

const historyComparisonRows = computed(() => {
  if (!selectedHistoryVersion.value || !previousHistoryVersion.value) {
    return [];
  }

  return trackedVersionFields
    .map((field) => {
      const currentValue = selectedHistoryVersion.value[field];
      const previousValue = previousHistoryVersion.value[field];
      const hasChanged =
        normalizeComparisonValue(field, currentValue) !==
        normalizeComparisonValue(field, previousValue);

      if (!hasChanged) {
        return null;
      }

      return {
        field,
        label: versionFieldLabels[field] || field,
        previous: formatComparisonValue(field, previousValue),
        current: formatComparisonValue(field, currentValue),
      };
    })
    .filter(Boolean);
});

const selectHistoryVersion = (versionId) => {
  selectedHistoryVersionId.value = versionId;
};

const handleSubmit = async () => {
  try {
    await payrollStore.updateSettings({
      payday_day: Number(form.payday_day),
      attendance_cutoff_day: Number(form.attendance_cutoff_day),
      working_days_mode: form.working_days_mode,
      default_working_days:
        form.working_days_mode === "fixed"
          ? Number(form.default_working_days)
          : Number(fallbackSettings.default_working_days),
      absent_deduction_rate: Number(form.absent_deduction_rate),
      rounding_mode: form.rounding_mode,
      rounding_unit:
        form.rounding_mode === "none" ? 1 : Number(form.rounding_unit || 1),
      note_template:
        form.note_template?.trim() || fallbackSettings.note_template,
    });

    toast.success(
      "Payroll settings saved",
      "These settings will apply to payroll drafts generated after this update.",
    );
    hydrateForm(settings.value);
    await loadSettingsHistory();
  } catch (error) {
    toast.error(
      "Failed to save payroll settings",
      payrollStore.error || "Please check the form and try again.",
    );
  }
};

onMounted(() => {
  loadSettings();
  loadSettingsHistory();
});
</script>

<template>
  <div class="space-y-6">
    <div class="items-center">
      <button
        type="button"
        data-testid="payroll-settings-back"
        @click="router.push({ name: 'admin.payroll.dashboard' })"
        class="mb-4 border border-[#DCDEDD] rounded-[8px] px-4 py-3 flex items-center gap-2 hover:border-[#0C51D9] transition-all duration-300"
      >
        <ArrowLeft class="w-4 h-4 text-gray-600" />
        <span class="text-brand-dark text-sm font-semibold">Back</span>
      </button>
      <div>
        <h2 class="text-brand-dark text-[32px] font-bold leading-tight">
          Payroll Settings
        </h2>
        <p class="text-brand-light text-base font-normal mt-2">
          Configure how future payroll drafts are calculated and documented.
        </p>
      </div>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-[20px] px-5 py-4">
      <div class="flex items-start justify-between gap-3">
        <p class="text-blue-900 text-sm font-semibold">
          Applies to future payroll drafts only
        </p>
        <span
          data-testid="payroll-settings-active-version"
          class="inline-flex rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-700"
        >
          Active {{ activeVersionLabel }}
        </span>
      </div>
      <p class="text-blue-800 text-sm mt-1">
        Existing payroll records stay unchanged. Update settings before HR
        generates the next draft.
      </p>
      <p
        class="text-blue-700 text-xs mt-2"
        data-testid="payroll-settings-updated-by"
      >
        {{ lastUpdatedLabel }}
      </p>
    </div>

    <div
      class="grid grid-cols-1 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)] items-start gap-6"
    >
      <div class="xl:col-span-1 space-y-6 min-w-0">
        <section
          class="bg-white border border-[#DCDEDD] rounded-[20px] p-6 overflow-hidden shadow-[0_1px_2px_rgba(15,23,42,0.03)]"
        >
          <div class="flex items-center gap-3 mb-6">
            <div
              class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center"
            >
              <Calendar class="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-xl font-bold">
                Payroll Schedule
              </h3>
              <p class="text-brand-light text-sm">
                Define default payday and attendance cut-off.
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-brand-dark text-sm font-semibold mb-2"
                >Default payday</label
              >
              <input
                v-model="form.payday_day"
                data-testid="payroll-settings-payday-day"
                type="number"
                min="1"
                max="31"
                class="w-full px-4 py-3 border border-[#DCDEDD] rounded-[12px] hover:border-[#0C51D9] focus:border-[#0C51D9] transition-all duration-300"
              />
            </div>
            <div>
              <label class="block text-brand-dark text-sm font-semibold mb-2"
                >Attendance cut-off day</label
              >
              <input
                v-model="form.attendance_cutoff_day"
                data-testid="payroll-settings-cutoff-day"
                type="number"
                min="1"
                max="31"
                class="w-full px-4 py-3 border border-[#DCDEDD] rounded-[12px] hover:border-[#0C51D9] focus:border-[#0C51D9] transition-all duration-300"
              />
            </div>
          </div>
        </section>

        <section
          class="bg-white border border-[#DCDEDD] rounded-[20px] p-6 overflow-hidden shadow-[0_1px_2px_rgba(15,23,42,0.03)]"
        >
          <div class="flex items-center gap-3 mb-6">
            <div
              class="w-12 h-12 bg-emerald-50 rounded-[12px] flex items-center justify-center"
            >
              <Calculator class="w-6 h-6 text-emerald-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-xl font-bold">
                Calculation Rules
              </h3>
              <p class="text-brand-light text-sm">
                Control working day basis, absence deductions, and rounding.
              </p>
            </div>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-brand-dark text-sm font-semibold mb-2"
                >Working days mode</label
              >
              <select
                v-model="form.working_days_mode"
                data-testid="payroll-settings-working-days-mode"
                class="w-full px-4 py-3 border border-[#DCDEDD] rounded-[12px] hover:border-[#0C51D9] focus:border-[#0C51D9] transition-all duration-300"
              >
                <option value="auto_business_days">Auto business days</option>
                <option value="fixed">Fixed working days</option>
              </select>
            </div>

            <div v-if="form.working_days_mode === 'fixed'">
              <label class="block text-brand-dark text-sm font-semibold mb-2"
                >Default working days</label
              >
              <input
                v-model="form.default_working_days"
                data-testid="payroll-settings-default-working-days"
                type="number"
                min="1"
                max="31"
                class="w-full px-4 py-3 border border-[#DCDEDD] rounded-[12px] hover:border-[#0C51D9] focus:border-[#0C51D9] transition-all duration-300"
              />
            </div>

            <div>
              <label class="block text-brand-dark text-sm font-semibold mb-2"
                >Absent deduction multiplier</label
              >
              <input
                v-model="form.absent_deduction_rate"
                data-testid="payroll-settings-absent-deduction-rate"
                type="number"
                min="0"
                max="5"
                step="0.01"
                class="w-full px-4 py-3 border border-[#DCDEDD] rounded-[12px] hover:border-[#0C51D9] focus:border-[#0C51D9] transition-all duration-300"
              />
              <p class="text-brand-light text-xs mt-2">
                `1.00` means one daily salary per absent day. Higher values
                increase the deduction.
              </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-brand-dark text-sm font-semibold mb-2"
                  >Rounding mode</label
                >
                <select
                  v-model="form.rounding_mode"
                  data-testid="payroll-settings-rounding-mode"
                  class="w-full px-4 py-3 border border-[#DCDEDD] rounded-[12px] hover:border-[#0C51D9] focus:border-[#0C51D9] transition-all duration-300"
                >
                  <option value="none">No rounding</option>
                  <option value="nearest">Nearest</option>
                  <option value="floor">Round down</option>
                  <option value="ceil">Round up</option>
                </select>
              </div>
              <div>
                <label class="block text-brand-dark text-sm font-semibold mb-2"
                  >Rounding unit</label
                >
                <input
                  v-model="form.rounding_unit"
                  data-testid="payroll-settings-rounding-unit"
                  type="number"
                  min="1"
                  step="1"
                  :disabled="form.rounding_mode === 'none'"
                  class="w-full px-4 py-3 border border-[#DCDEDD] rounded-[12px] hover:border-[#0C51D9] focus:border-[#0C51D9] transition-all duration-300 disabled:bg-gray-100 disabled:cursor-not-allowed"
                />
              </div>
            </div>
          </div>
        </section>

        <section
          class="bg-white border border-[#DCDEDD] rounded-[20px] p-6 overflow-hidden shadow-[0_1px_2px_rgba(15,23,42,0.03)]"
        >
          <div class="flex items-center gap-3 mb-6">
            <div
              class="w-12 h-12 bg-amber-50 rounded-[12px] flex items-center justify-center"
            >
              <FileText class="w-6 h-6 text-amber-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-xl font-bold">
                Payroll Note Template
              </h3>
              <p class="text-brand-light text-sm">
                Use placeholders to keep every generated payroll note
                consistent.
              </p>
            </div>
          </div>

          <label class="block text-brand-dark text-sm font-semibold mb-2"
            >Template</label
          >
          <textarea
            v-model="form.note_template"
            data-testid="payroll-settings-note-template"
            rows="5"
            class="w-full px-4 py-3 border border-[#DCDEDD] rounded-[12px] hover:border-[#0C51D9] focus:border-[#0C51D9] transition-all duration-300 resize-none"
          />
          <p class="text-brand-light text-xs mt-2">
            Available placeholders: `{working_days}`, `{attended_days}`,
            `{late_days}`, `{sick_days}`, `{permission_days}`, `{absent_days}`,
            `{deduction}`.
          </p>
        </section>
      </div>

      <div class="self-start xl:sticky xl:top-6">
        <div
          class="bg-white border border-[#DCDEDD] rounded-[20px] p-6 overflow-hidden shadow-[0_1px_2px_rgba(15,23,42,0.03)]"
        >
          <h3 class="text-brand-dark text-lg font-bold mb-4">Preview</h3>
          <div class="space-y-3">
            <div class="border border-[#DCDEDD] rounded-[16px] px-4 py-3">
              <p class="text-brand-light text-xs font-semibold uppercase">
                Future draft note
              </p>
              <p
                class="text-brand-dark text-sm mt-2 leading-6"
                data-testid="payroll-settings-note-preview"
              >
                {{ notePreview }}
              </p>
            </div>
            <div class="border border-[#DCDEDD] rounded-[16px] px-4 py-3">
              <p class="text-brand-light text-xs font-semibold uppercase">
                Summary
              </p>
              <p class="text-brand-dark text-sm mt-2">
                Payday day {{ form.payday_day }}, cut-off day
                {{ form.attendance_cutoff_day }}, rounding
                {{ form.rounding_mode }}.
              </p>
            </div>
            <button
              type="button"
              data-testid="payroll-settings-save"
              :disabled="loading"
              @click="handleSubmit"
              class="w-full btn-primary rounded-[12px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center justify-center gap-2 disabled:opacity-70"
            >
              <Settings class="w-4 h-4 text-white" />
              <span class="text-brand-white text-sm font-semibold">
                {{ loading ? "Saving..." : "Save Payroll Settings" }}
              </span>
            </button>
          </div>
        </div>

        <div
          class="mt-6 bg-white border border-[#DCDEDD] rounded-[20px] p-6 overflow-hidden shadow-[0_1px_2px_rgba(15,23,42,0.03)]"
        >
          <div class="flex items-center gap-3 mb-4">
            <div
              class="w-12 h-12 bg-violet-50 rounded-[12px] flex items-center justify-center"
            >
              <History class="w-6 h-6 text-violet-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-lg font-bold">Version History</h3>
              <p class="text-brand-light text-sm">
                Latest payroll settings snapshots used for future drafts.
              </p>
            </div>
          </div>

          <div v-if="loadingHistory" class="text-sm text-brand-light">
            Loading settings history...
          </div>

          <div
            v-else-if="settingsHistory.length === 0"
            data-testid="payroll-settings-history-empty"
            class="rounded-[12px] border border-dashed border-[#DCDEDD] px-4 py-4 text-sm text-brand-light"
          >
            No settings history is available yet.
          </div>

          <div
            v-else
            data-testid="payroll-settings-history-list"
            class="space-y-2"
          >
            <div
              v-for="version in settingsHistory"
              :key="version.id"
              data-testid="payroll-settings-history-item"
              :class="[
                'rounded-[12px] border px-3 py-3 transition-colors duration-200',
                selectedHistoryVersionId === version.id
                  ? 'border-blue-300 bg-blue-50'
                  : 'border-[#DCDEDD]'
              ]"
            >
              <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-brand-dark">
                  Version v{{ version.version_number }}
                </p>
                <p class="text-xs text-brand-light">
                  {{ formatHistoryDate(version.effective_at || version.updated_at) }}
                </p>
              </div>
              <p class="mt-1 text-xs text-brand-light">
                Updated by
                <span class="font-semibold text-brand-dark">
                  {{ version.updated_by?.name || "System" }}
                </span>
              </p>
              <button
                type="button"
                :data-testid="`payroll-settings-history-compare-select-${version.id}`"
                @click="selectHistoryVersion(version.id)"
                class="mt-2 text-xs font-semibold text-blue-700 hover:text-blue-800"
              >
                Compare with previous version
              </button>
            </div>

            <div
              v-if="selectedHistoryVersion"
              data-testid="payroll-settings-history-compare-panel"
              class="mt-4 rounded-[12px] border border-[#DCDEDD] bg-gray-50 px-4 py-4"
            >
              <p class="text-xs uppercase tracking-wide text-brand-light">
                Version Comparison
              </p>
              <p class="text-sm font-semibold text-brand-dark mt-1">
                v{{ selectedHistoryVersion.version_number }}
                <template v-if="previousHistoryVersion">
                  vs v{{ previousHistoryVersion.version_number }}
                </template>
              </p>

              <div
                v-if="!previousHistoryVersion"
                data-testid="payroll-settings-history-compare-empty"
                class="mt-3 text-xs text-brand-light"
              >
                This is the oldest version in history. No previous version is available for comparison.
              </div>

              <div
                v-else-if="historyComparisonRows.length === 0"
                data-testid="payroll-settings-history-compare-no-change"
                class="mt-3 text-xs text-brand-light"
              >
                No tracked settings changed between these two versions.
              </div>

              <div v-else class="mt-3 space-y-2">
                <div
                  v-for="row in historyComparisonRows"
                  :key="row.field"
                  data-testid="payroll-settings-history-compare-row"
                  class="rounded-[10px] border border-[#DCDEDD] bg-white px-3 py-2"
                >
                  <p class="text-xs font-semibold text-brand-dark">{{ row.label }}</p>
                  <p class="text-xs text-brand-light mt-1">
                    From <span class="font-semibold text-brand-dark">{{ row.previous }}</span>
                    to <span class="font-semibold text-blue-700">{{ row.current }}</span>
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
