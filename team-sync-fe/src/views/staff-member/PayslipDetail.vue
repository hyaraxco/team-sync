<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { usePayrollStore } from "@/stores/payroll";
import { useAuthStore } from "@/stores/auth";
import { useToast } from "@/composables/useToast";
import {
  ArrowLeft,
  Download,
  Printer,
  Calendar,
  Building,
  Mail,
  DollarSign,
  TrendingUp,
  TrendingDown,
  FileText,
  BadgeCheck,
  ChevronDown,
  ChevronUp,
} from "lucide-vue-next";

const route = useRoute();
const router = useRouter();
const payrollStore = usePayrollStore();
const authStore = useAuthStore();
const toast = useToast();

const payslip = ref(null);
const loading = ref(true);
const expandedAdjustmentIds = ref([]);

onMounted(async () => {
  try {
    payslip.value = await payrollStore.fetchMyPayslip(route.params.id);
  } catch (error) {
    toast.error(
      "Failed to load payslip",
      payrollStore.error ||
        error?.response?.data?.message ||
        "Failed to load payslip.",
    );
  } finally {
    loading.value = false;
  }
});

const handleDownload = async () => {
  try {
    const blob = await payrollStore.downloadPayslip(route.params.id);
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `payslip-${route.params.id}.pdf`;
    link.click();
    window.URL.revokeObjectURL(url);
  } catch (error) {
    toast.error(
      "Download failed",
      payrollStore.error ||
        error?.response?.data?.message ||
        "Failed to download payslip.",
    );
  }
};

const handlePrint = () => {
  window.print();
};

const formatCurrency = (value) => {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    minimumFractionDigits: 0,
  }).format(value);
};

const formatSignedCurrency = (value) => {
  const amount = Number(value || 0);
  const absolute = formatCurrency(Math.abs(amount));

  if (amount > 0) {
    return `+${absolute}`;
  }

  if (amount < 0) {
    return `-${absolute}`;
  }

  return absolute;
};

const formatDate = (date) => {
  return new Date(date).toLocaleDateString("id-ID", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
};

const formatPeriod = (date) => {
  return new Date(date).toLocaleDateString("id-ID", {
    year: "numeric",
    month: "long",
  });
};

const employeeContactEmail = computed(
  () => payslip.value?.employee_email || authStore.user?.email,
);

const adjustments = computed(() => payslip.value?.adjustments || []);

const toggleAdjustmentDetails = (adjustmentId) => {
  const currentIds = [...expandedAdjustmentIds.value];
  const targetIndex = currentIds.indexOf(adjustmentId);

  if (targetIndex >= 0) {
    currentIds.splice(targetIndex, 1);
  } else {
    currentIds.push(adjustmentId);
  }

  expandedAdjustmentIds.value = currentIds;
};

const isAdjustmentExpanded = (adjustmentId) =>
  expandedAdjustmentIds.value.includes(adjustmentId);

const formatAdjustmentKind = (kind) => {
  if (!kind) {
    return "Unknown adjustment";
  }

  return kind
    .replaceAll("_", " ")
    .replace(/\b\w/g, (char) => char.toUpperCase());
};

const formatAdjustmentStatus = (status) => {
  if (!status) {
    return "Unknown";
  }

  return status.charAt(0).toUpperCase() + status.slice(1);
};

const getAdjustmentStatusClass = (status) => {
  if (status === "applied") {
    return "bg-green-100 text-green-700";
  }

  if (status === "approved") {
    return "bg-blue-100 text-blue-700";
  }

  return "bg-gray-100 text-gray-700";
};
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6 print:hidden">
      <button
        @click="router.back()"
        data-testid="payslip-detail-back"
        class="flex items-center gap-2 px-4 py-2 border border-[#DCDEDD] rounded-[12px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300"
      >
        <ArrowLeft class="w-5 h-5" />
        <span class="font-semibold">Back</span>
      </button>

      <div class="flex items-center gap-3">
        <button
          @click="handlePrint"
          data-testid="payslip-detail-print"
          class="flex items-center gap-2 px-4 py-2 border border-[#DCDEDD] rounded-[12px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300"
        >
          <Printer class="w-5 h-5" />
          <span class="font-semibold">Print</span>
        </button>
        <button
          @click="handleDownload"
          data-testid="payslip-detail-download"
          class="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-[12px] hover:brightness-110 transition-all duration-300"
        >
          <Download class="w-5 h-5" />
          <span class="font-semibold">Download PDF</span>
        </button>
      </div>
    </div>

    <div
      class="bg-white border border-[#DCDEDD] rounded-[20px] p-8 max-w-4xl mx-auto"
    >
      <div v-if="loading" class="text-center py-12">
        <p class="text-gray-500">Loading payslip...</p>
      </div>

      <div v-else-if="payslip">
        <div class="border-b-2 border-gray-200 pb-6 mb-6">
          <div class="flex items-start justify-between">
            <div>
              <h1 class="text-3xl font-extrabold text-brand-dark mb-2">
                My Payroll
              </h1>
              <p class="text-gray-600">
                {{ formatPeriod(payslip.period) }}
              </p>
              <div
                class="mt-2 flex w-fit items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold"
                :class="payslip.status === 'paid'
                  ? 'bg-green-100 text-green-800'
                  : 'bg-yellow-100 text-yellow-800'"
              >
                <BadgeCheck class="w-4 h-4" />
                {{ payslip.status === 'paid' ? 'Paid' : payslip.status || 'Paid' }}
              </div>
            </div>
            <div class="text-right">
              <div
                class="w-16 h-16 bg-gradient-to-br from-primary-500 to-primary-600 rounded-[16px] flex items-center justify-center mb-2 ml-auto"
              >
                <Building class="w-8 h-8 text-white" />
              </div>
              <p class="text-sm font-semibold text-brand-dark">
                {{ payslip.company_name || "Team Sync Pro" }}
              </p>
              <p class="text-xs text-gray-600">
                {{ payslip.company_address || "Indonesia" }}
              </p>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
          <div class="rounded-[16px] border border-[#DCDEDD] p-5">
            <p class="text-sm text-gray-500 mb-1">Payroll Period</p>
            <p class="text-lg font-bold text-brand-dark">
              {{ formatPeriod(payslip.period) }}
            </p>
          </div>
          <div class="rounded-[16px] border border-[#DCDEDD] p-5">
            <p class="text-sm text-gray-500 mb-1">Payment Date</p>
            <p class="text-lg font-bold text-brand-dark">
              {{ formatDate(payslip.payment_date || payslip.created_at) }}
            </p>
          </div>
          <div class="rounded-[16px] border border-[#DCDEDD] p-5">
            <p class="text-sm text-gray-500 mb-1">Take Home Pay</p>
            <p class="text-lg font-bold text-brand-dark">
              {{ formatCurrency(payslip.net_salary) }}
            </p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-6 mb-8">
          <div>
            <h3 class="text-sm font-semibold text-gray-500 mb-4">
              EMPLOYEE INFORMATION
            </h3>
            <div class="space-y-3">
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 bg-blue-50 rounded-[12px] flex items-center justify-center"
                >
                  <FileText class="w-5 h-5 text-blue-600" />
                </div>
                <div>
                  <p class="text-xs text-gray-500">Full Name</p>
                  <p class="font-semibold text-brand-dark">
                    {{ payslip.employee_name }}
                  </p>
                  <p
                    v-if="payslip.employee_code"
                    class="text-xs text-gray-500 mt-1"
                  >
                    Employee ID: {{ payslip.employee_code }}
                  </p>
                </div>
              </div>
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 bg-purple-50 rounded-[12px] flex items-center justify-center"
                >
                  <Building class="w-5 h-5 text-purple-600" />
                </div>
                <div>
                  <p class="text-xs text-gray-500">Department</p>
                  <p class="font-semibold text-brand-dark">
                    {{ payslip.department }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div>
            <h3 class="text-sm font-semibold text-gray-500 mb-4">
              CONTACT INFORMATION
            </h3>
            <div class="space-y-3">
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 bg-green-50 rounded-[12px] flex items-center justify-center"
                >
                  <Mail class="w-5 h-5 text-green-600" />
                </div>
                <div>
                  <p class="text-xs text-gray-500">Email</p>
                  <p class="font-semibold text-brand-dark">
                    {{ employeeContactEmail }}
                  </p>
                </div>
              </div>
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 bg-orange-50 rounded-[12px] flex items-center justify-center"
                >
                  <Calendar class="w-5 h-5 text-orange-600" />
                </div>
                <div>
                  <p class="text-xs text-gray-500">Payment Date</p>
                  <p class="font-semibold text-brand-dark">
                    {{ formatDate(payslip.payment_date || payslip.created_at) }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
          <div class="bg-green-50 rounded-[16px] p-6">
            <div class="flex items-center gap-2 mb-4">
              <TrendingUp class="w-5 h-5 text-green-600" />
              <h3 class="text-lg font-bold text-green-900">Earnings</h3>
            </div>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-green-700">Basic Salary</span>
                <span class="font-semibold text-green-900">
                  {{ formatCurrency(payslip.basic_salary) }}
                </span>
              </div>
              <div
                v-if="payslip.allowances > 0"
                class="flex items-center justify-between"
              >
                <span class="text-sm text-green-700">Allowances</span>
                <span class="font-semibold text-green-900">
                  {{ formatCurrency(payslip.allowances) }}
                </span>
              </div>
              <div
                v-if="payslip.bonus > 0"
                class="flex items-center justify-between"
              >
                <span class="text-sm text-green-700">Bonus</span>
                <span class="font-semibold text-green-900">
                  {{ formatCurrency(payslip.bonus) }}
                </span>
              </div>
              <div
                class="flex items-center justify-between pt-3 border-t-2 border-green-200"
              >
                <span class="text-sm font-bold text-green-900"
                  >Gross Salary</span
                >
                <span class="text-lg font-extrabold text-green-900">
                  {{ formatCurrency(payslip.gross_salary) }}
                </span>
              </div>
            </div>
          </div>

          <div class="bg-red-50 rounded-[16px] p-6">
            <div class="flex items-center gap-2 mb-4">
              <TrendingDown class="w-5 h-5 text-red-600" />
              <h3 class="text-lg font-bold text-red-900">Deductions</h3>
            </div>
            <div class="space-y-3">
              <div
                v-if="payslip.tax > 0"
                class="flex items-center justify-between"
              >
                <span class="text-sm text-red-700">Tax</span>
                <span class="font-semibold text-red-900">
                  {{ formatCurrency(payslip.tax) }}
                </span>
              </div>
              <div
                v-if="payslip.insurance > 0"
                class="flex items-center justify-between"
              >
                <span class="text-sm text-red-700">Insurance</span>
                <span class="font-semibold text-red-900">
                  {{ formatCurrency(payslip.insurance) }}
                </span>
              </div>
              <div
                v-if="payslip.other_deductions > 0"
                class="flex items-center justify-between"
              >
                <span class="text-sm text-red-700">Other Deductions</span>
                <span class="font-semibold text-red-900">
                  {{ formatCurrency(payslip.other_deductions) }}
                </span>
              </div>
              <div
                class="flex items-center justify-between pt-3 border-t-2 border-red-200"
              >
                <span class="text-sm font-bold text-red-900"
                  >Total Deductions</span
                >
                <span class="text-lg font-extrabold text-red-900">
                  {{ formatCurrency(payslip.total_deductions) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div
          v-if="adjustments.length > 0"
          data-testid="payslip-adjustments"
          class="bg-amber-50 rounded-[16px] p-6 mb-8"
        >
          <div class="flex items-center gap-2 mb-4">
            <BadgeCheck class="w-5 h-5 text-amber-700" />
            <h3 class="text-lg font-bold text-amber-900">Adjustments</h3>
          </div>
          <div class="space-y-3">
            <div
              v-for="adjustment in adjustments"
              :key="adjustment.id"
              class="rounded-[12px] border border-amber-200 bg-white/80 px-4 py-3"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-sm font-semibold text-amber-900">
                    {{ adjustment.reason || formatAdjustmentKind(adjustment.adjustment_kind) }}
                  </p>
                  <p class="text-xs text-amber-700 mt-1">
                    {{ formatAdjustmentKind(adjustment.adjustment_kind) }}
                  </p>
                  <div class="mt-2">
                    <span
                      :class="[
                        'inline-flex rounded-full px-2 py-1 text-xs font-semibold',
                        getAdjustmentStatusClass(adjustment.status),
                      ]"
                    >
                      {{ formatAdjustmentStatus(adjustment.status) }}
                    </span>
                  </div>
                </div>
                <div class="text-right">
                  <span
                    :class="[
                      'text-sm font-semibold',
                      Number(adjustment.amount_delta || 0) >= 0 ? 'text-green-700' : 'text-red-700',
                    ]"
                  >
                    {{ formatSignedCurrency(adjustment.amount_delta) }}
                  </span>
                  <button
                    type="button"
                    class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-amber-800 hover:text-amber-900"
                    @click="toggleAdjustmentDetails(adjustment.id)"
                    :data-testid="`payslip-adjustment-toggle-${adjustment.id}`"
                  >
                    <span>{{ isAdjustmentExpanded(adjustment.id) ? "Hide details" : "View details" }}</span>
                    <ChevronUp v-if="isAdjustmentExpanded(adjustment.id)" class="w-3 h-3" />
                    <ChevronDown v-else class="w-3 h-3" />
                  </button>
                </div>
              </div>

              <div
                v-if="isAdjustmentExpanded(adjustment.id)"
                class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs text-amber-800"
              >
                <p>
                  Days Delta:
                  <span class="font-semibold text-amber-900">{{ Number(adjustment.days_delta || 0) }}</span>
                </p>
                <p>
                  Source Period:
                  <span class="font-semibold text-amber-900">#{{ adjustment.source_period_id ?? '-' }}</span>
                </p>
                <p>
                  Target Period:
                  <span class="font-semibold text-amber-900">#{{ adjustment.target_period_id ?? '-' }}</span>
                </p>
              </div>
            </div>
          </div>
        </div>

        <div
          class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-[20px] p-6 text-white"
        >
          <div class="flex items-center justify-between">
            <div>
              <p class="text-white/80 text-sm font-medium mb-2">
                NET SALARY (TAKE HOME)
              </p>
              <p class="text-4xl font-extrabold">
                {{ formatCurrency(payslip.net_salary) }}
              </p>
            </div>
            <div
              class="w-16 h-16 bg-white/20 rounded-[20px] flex items-center justify-center backdrop-blur-sm"
            >
              <DollarSign class="w-8 h-8 text-white" />
            </div>
          </div>
        </div>

        <div v-if="payslip.notes" class="mt-6 p-4 bg-gray-50 rounded-[12px]">
          <p class="text-sm font-semibold text-gray-700 mb-2">Payroll Notes</p>
          <p class="text-sm text-gray-600">{{ payslip.notes }}</p>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-200 text-center">
          <p class="text-xs text-gray-500">
            This is a computer-generated payslip and does not require a
            signature.
          </p>
          <p class="text-xs text-gray-500 mt-1">
            For any queries, please contact the HR department.
          </p>
        </div>
      </div>

      <div v-else class="text-center py-12">
        <p class="text-gray-500">Payroll detail not found</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
@media print {
  body * {
    visibility: hidden;
  }
  .bg-white,
  .bg-white * {
    visibility: visible;
  }
  .bg-white {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
}
</style>
