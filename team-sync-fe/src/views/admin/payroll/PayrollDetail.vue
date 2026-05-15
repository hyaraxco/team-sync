<script setup>
import { ref, onMounted, computed, watch } from "vue";
import { DEFAULT_AVATAR } from "@/helpers/format";
import { useRoute, useRouter } from "vue-router";
import { usePayrollStore } from "@/stores/payroll";
import { can } from "@/helpers/permissionHelper";
import {
    ArrowLeft,
    Users,
    DollarSign,
    Banknote,
    CalendarCheck,
    Search,
    Download,
    CheckCircle,
    AlertTriangle,
    RotateCcw,
    Settings,
    Activity,
    ShieldCheck,
    XCircle,
    Minus,
    Bell,
} from "lucide-vue-next";
import { debounce } from "lodash";
import Pagination from "@/components/admin/payroll/Pagination.vue";
import { formatRupiah, formatRupiahCompact } from "@/utils/formatUtils";
import { useConfirmAction } from "@/composables/useConfirmAction";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import AnimatedValue from "@/components/common/AnimatedValue.vue";
import { useToast } from "@/composables/useToast";

const toast = useToast();

const route = useRoute();
const router = useRouter();
const payrollStore = usePayrollStore();
const hasPayrollStatistics = computed(() => can("payroll-statistics"));
const hasPayrollProcess = computed(() => can("payroll-process"));
const hasPayrollList = computed(() => can("payroll-list"));
const hasPayrollEdit = computed(() => can("payroll-edit"));
const showsAutoNotificationInfo = computed(() => hasPayrollProcess.value && payroll.value?.status === "paid");
const canResendNotifications = computed(() => hasPayrollProcess.value && payroll.value?.status === "paid");
const canApprovePayroll = computed(() => hasPayrollEdit.value && payroll.value?.status === "pending");
const canMarkPayrollAsPaid = computed(() => hasPayrollProcess.value && payroll.value?.status === "approved");
const canReopenPayroll = computed(
    () => hasPayrollProcess.value && ["approved", "paid"].includes(payroll.value?.status),
);

const payroll = ref(null);
const payrollStatistics = ref(null);
const employees = ref([]);
const activityLogs = ref([]);
const reconciliation = ref(null);
const notificationDeliveries = ref(null);
const approvalStatus = ref(null);
const pagination = ref({
    current_page: 1,
    per_page: 50,
    total: 0,
    last_page: 1,
    from: 0,
    to: 0,
});
const loading = ref(true);
const loadingStatistics = ref(true);
const loadingDetails = ref(false);
const loadingActivityLogs = ref(false);
const loadingReconciliation = ref(false);
const loadingNotificationDeliveries = ref(false);
const loadingApprovals = ref(false);
const submittingApprovalDecision = ref(false);
const exportingPdf = ref(false);
const searchQuery = ref("");
const departmentFilter = ref("");
const paymentDate = ref(new Date().toISOString().split("T")[0]);
const reopenReason = ref("");
const selectedAdjustmentEmployee = ref(null);
const showAdjustmentDetailsModal = ref(false);
const approvalDecisionNotes = ref("");
const approvalDecisionStatus = ref("approved");

const activeTab = ref("employees");

// Reconciliation resolution modal state
const showResolveExceptionModal = ref(false);
const resolvingException = ref(false);
const resolveExceptionTarget = ref(null);
const resolveExceptionForm = ref({
    resolution_action: "acknowledged",
    reason: "",
});

const openResolveExceptionModal = (exception) => {
    resolveExceptionTarget.value = exception;
    resolveExceptionForm.value = { resolution_action: "acknowledged", reason: "" };
    showResolveExceptionModal.value = true;
};

const closeResolveExceptionModal = () => {
    showResolveExceptionModal.value = false;
    resolveExceptionTarget.value = null;
};

const handleResolveException = async () => {
    if (!resolveExceptionTarget.value || resolveExceptionForm.value.reason.trim().length < 10) {
        return;
    }

    resolvingException.value = true;

    try {
        await payrollStore.resolveReconciliationException(route.params.id, {
            staff_member_id: resolveExceptionTarget.value.staff_member_id,
            exception_type: resolveExceptionTarget.value.type,
            resolution_action: resolveExceptionForm.value.resolution_action,
            reason: resolveExceptionForm.value.reason.trim(),
        });

        toast.success("Exception Resolved", "Reconciliation exception has been resolved successfully.");
        closeResolveExceptionModal();
        await fetchPayrollReconciliation();
    } catch (error) {
        toast.error(
            "Failed to resolve exception",
            payrollStore.error || error?.response?.data?.message || "An error occurred.",
        );
    } finally {
        resolvingException.value = false;
    }
};

const selectedAdjustmentItems = computed(() => selectedAdjustmentEmployee.value?.adjustments || []);

const selectedAdjustmentEmployeeName = computed(() => selectedAdjustmentEmployee.value?.name || "Selected employee");

const reconciliationSummary = computed(() => reconciliation.value?.summary ?? null);
const reconciliationExceptions = computed(() => reconciliation.value?.exceptions ?? []);
const reconciliationSeverityFilter = ref("all");
const reconciliationTypeFilter = ref("all");
const reconciliationIssueTypeOptions = computed(() => {
    const availableTypes = reconciliation.value?.available_types;

    if (!Array.isArray(availableTypes)) {
        return [];
    }

    return [...availableTypes].sort();
});
const filteredReconciliationExceptions = computed(() => reconciliationExceptions.value);
const totalReconciliationIssueCount = computed(() =>
    Number(reconciliationSummary.value?.total_exception_count ?? reconciliationExceptions.value.length),
);
const displayedReconciliationIssueCount = computed(() =>
    Number(reconciliationSummary.value?.filtered_exception_count ?? filteredReconciliationExceptions.value.length),
);
const hasCriticalReconciliationIssue = computed(
    () =>
        (reconciliationSummary.value?.unresolved_critical_count ?? reconciliationSummary.value?.critical_count ?? 0) >
        0,
);
const canTriggerMarkAsPaid = computed(() => canMarkPayrollAsPaid.value && !hasCriticalReconciliationIssue.value);
const notificationDeliverySummary = computed(() => notificationDeliveries.value?.summary ?? null);
const latestNotificationDeliveries = computed(() => notificationDeliveries.value?.latest_by_employee ?? []);
const approvalSteps = computed(() => approvalStatus.value?.approvals ?? []);
const hasApprovalSteps = computed(() => approvalSteps.value.length > 0);
const notificationDeliveryRate = computed(() => {
    const summary = notificationDeliverySummary.value;
    if (!summary) {
        return 0;
    }
    return Number(summary.delivery_rate ?? 0);
});
const hasFailedDeliveries = computed(() => {
    return (notificationDeliverySummary.value?.failed_count ?? 0) > 0;
});
const correctionCount = computed(() => payroll.value?.correction_count ?? 0);
const isCorrected = computed(() => correctionCount.value > 0);

const payrollSettingsVersion = computed(() => payroll.value?.payroll_setting_version ?? null);
const isLegacySettingsVersion = computed(() => payroll.value?.is_legacy_settings_version === true);
const payrollSettingsVersionLabel = computed(() => {
    const versionNumber = payrollSettingsVersion.value?.version_number;

    if (versionNumber) {
        return `v${versionNumber}`;
    }

    if (isLegacySettingsVersion.value) {
        return "Legacy";
    }

    return "Unknown";
});

const {
    isModalOpen: showMarkAsPaidModal,
    isProcessing: markingAsPaid,
    openModal: openMarkAsPaidModal,
    closeModal: closeMarkAsPaidModal,
    confirmAction: doMarkAsPaid,
} = useConfirmAction({
    onOpen: () => {
        paymentDate.value = new Date().toISOString().split("T")[0];
    },
    onSuccess: async () => {
        await fetchPayrollSummary();
        if (hasPayrollStatistics.value) {
            await fetchPayrollStatistics();
        }
        await fetchPayrollNotificationDeliveries();
        await fetchPayrollApprovals();
        await fetchPayrollReconciliation();
        await fetchPayrollDetails(pagination.value.current_page);
        await fetchPayrollActivityLogs();
        toast.success("Payment Complete", "Payroll marked as paid successfully!");
    },
});

const {
    isModalOpen: showApprovePayrollModal,
    isProcessing: approvingPayroll,
    openModal: openApprovePayrollModal,
    closeModal: closeApprovePayrollModal,
    confirmAction: doApprovePayroll,
} = useConfirmAction({
    onSuccess: async () => {
        await fetchPayrollSummary();
        if (hasPayrollStatistics.value) {
            await fetchPayrollStatistics();
        }
        await fetchPayrollApprovals();
        await fetchPayrollReconciliation();
        await fetchPayrollDetails(pagination.value.current_page);
        await fetchPayrollActivityLogs();
        toast.success("Payroll Approved", "Payroll approval flow updated.");
    },
});

const {
    isModalOpen: showResendNotificationsModal,
    isProcessing: resendingNotifications,
    openModal: openResendNotificationsModal,
    closeModal: closeResendNotificationsModal,
    confirmAction: doResendNotifications,
} = useConfirmAction({
    onSuccess: async () => {
        await fetchPayrollActivityLogs();
        await fetchPayrollNotificationDeliveries();
        toast.success("Notifications Resent", "Payroll notifications were resent successfully.");
    },
});

const {
    isModalOpen: showReopenPayrollModal,
    isProcessing: reopeningPayroll,
    openModal: openReopenPayrollModal,
    closeModal: closeReopenPayrollModal,
    confirmAction: doReopenPayroll,
} = useConfirmAction({
    onOpen: () => {
        reopenReason.value = "";
    },
    onSuccess: async () => {
        await fetchPayrollSummary();
        if (hasPayrollStatistics.value) {
            await fetchPayrollStatistics();
        }
        await fetchPayrollNotificationDeliveries();
        await fetchPayrollApprovals();
        await fetchPayrollReconciliation();
        await fetchPayrollDetails(pagination.value.current_page);
        await fetchPayrollActivityLogs();
        toast.success("Payroll Reopened", "Payroll was moved back to pending so corrections can be applied.");
    },
});

const fetchPayrollSummary = async () => {
    try {
        loading.value = true;
        payroll.value = await payrollStore.fetchPayroll(route.params.id);
    } catch (error) {
        toast.error(
            "Failed to load payroll summary",
            payrollStore.error || error?.response?.data?.message || "Failed to load payroll summary.",
        );
    } finally {
        loading.value = false;
    }
};

const fetchPayrollStatistics = async () => {
    try {
        loadingStatistics.value = true;
        payrollStatistics.value = await payrollStore.fetchPayrollStatistics(route.params.id);
    } catch (error) {
        toast.error(
            "Failed to load payroll statistics",
            payrollStore.error || error?.response?.data?.message || "Failed to load payroll statistics.",
        );
    } finally {
        loadingStatistics.value = false;
    }
};

const fetchPayrollDetails = async (page = 1) => {
    try {
        loadingDetails.value = true;

        // Build filter params
        const filterParams = {};
        if (searchQuery.value) {
            filterParams.search = searchQuery.value;
        }
        if (departmentFilter.value) {
            filterParams.position = departmentFilter.value;
        }

        const response = await payrollStore.fetchPayrollDetails(
            route.params.id,
            page,
            pagination.value.per_page,
            filterParams,
        );

        // Map payroll_details to employees format
        const fallbackWorkingDays = Number(payroll.value?.payroll_setting_version?.default_working_days || 22);

        employees.value =
            response.data?.map((detail) => ({
                id: detail.staff_member?.id,
                name: detail.staff_member?.user?.name || "N/A",
                staff_member_id: detail.staff_member?.code || detail.staff_member?.id,
                position: detail.staff_member?.job_information?.job_title || "N/A",
                department: detail.staff_member?.job_information?.team?.name || "N/A",
                profile_photo: detail.staff_member?.user?.profile_photo || null,
                total_work_days: Number(detail.effective_working_days || fallbackWorkingDays),
                attended_days: detail.attended_days || 0,
                sick_days: detail.sick_days || 0,
                absent_days: detail.absent_days || 0,
                basic_salary: parseFloat(detail.original_salary) || 0,
                deductions: parseFloat(detail.deduction_amount) || 0,
                adjustments: detail.adjustments || [],
                adjustment_total_amount: parseFloat(detail.adjustment_total_amount) || 0,
                net_salary: parseFloat(detail.final_salary) || 0,
                status: payroll.value?.status === "paid" ? "paid" : "pending",
                notes: detail.notes,
                bank_name: detail.staff_member?.bank_information?.bank_name || "N/A",
                account_number: detail.staff_member?.bank_information?.account_number || "N/A",
                account_holder_name: detail.staff_member?.bank_information?.account_holder_name || "N/A",
            })) || [];

        // Update pagination meta from response
        if (response.meta) {
            pagination.value = {
                current_page: response.meta.current_page,
                last_page: response.meta.last_page,
                per_page: response.meta.per_page,
                total: response.meta.total,
                from: response.meta.from,
                to: response.meta.to,
            };
        }
    } catch (error) {
        toast.error(
            "Failed to load payroll details",
            payrollStore.error || error?.response?.data?.message || "Failed to load payroll details.",
        );
    } finally {
        loadingDetails.value = false;
    }
};

const fetchPayrollActivityLogs = async () => {
    try {
        loadingActivityLogs.value = true;
        activityLogs.value = await payrollStore.fetchPayrollActivityLogs(route.params.id);
    } catch (error) {
        toast.error(
            "Failed to load payroll activity logs",
            payrollStore.error || error?.response?.data?.message || "Failed to load payroll activity logs.",
        );
    } finally {
        loadingActivityLogs.value = false;
    }
};

const fetchPayrollReconciliation = async () => {
    const filters = {};

    if (reconciliationSeverityFilter.value !== "all") {
        filters.severity = reconciliationSeverityFilter.value;
    }

    if (reconciliationTypeFilter.value !== "all") {
        filters.type = reconciliationTypeFilter.value;
    }

    const hasFilters = Object.keys(filters).length > 0;

    try {
        loadingReconciliation.value = true;
        reconciliation.value = hasFilters
            ? await payrollStore.fetchPayrollReconciliation(route.params.id, filters)
            : await payrollStore.fetchPayrollReconciliation(route.params.id);
    } catch (error) {
        toast.error(
            "Failed to load payroll reconciliation",
            payrollStore.error || error?.response?.data?.message || "Failed to load payroll reconciliation.",
        );
        reconciliation.value = null;
    } finally {
        loadingReconciliation.value = false;
    }
};

const fetchPayrollApprovals = async () => {
    try {
        loadingApprovals.value = true;
        approvalStatus.value = await payrollStore.fetchPayrollApprovals(route.params.id);
    } catch (_error) {
        approvalStatus.value = null;
    } finally {
        loadingApprovals.value = false;
    }
};

const fetchPayrollNotificationDeliveries = async () => {
    if (!hasPayrollProcess.value || payroll.value?.status !== "paid") {
        notificationDeliveries.value = null;
        return;
    }

    try {
        loadingNotificationDeliveries.value = true;
        notificationDeliveries.value = await payrollStore.fetchPayrollNotificationDeliveries(route.params.id);
    } catch (error) {
        toast.error(
            "Failed to load payroll notification deliveries",
            payrollStore.error || error?.response?.data?.message || "Failed to load payroll notification deliveries.",
        );
        notificationDeliveries.value = null;
    } finally {
        loadingNotificationDeliveries.value = false;
    }
};

const handlePageChange = (page) => {
    pagination.value.current_page = page;
    fetchPayrollDetails(page);
};

const handlePerPageChange = (perPage) => {
    pagination.value.per_page = perPage;
    pagination.value.current_page = 1;
    fetchPayrollDetails(1);
};

onMounted(async () => {
    await fetchPayrollSummary();
    if (hasPayrollStatistics.value) {
        await fetchPayrollStatistics();
    }
    await Promise.all([
        fetchPayrollNotificationDeliveries(),
        fetchPayrollApprovals(),
        fetchPayrollReconciliation(),
        fetchPayrollDetails(1),
        fetchPayrollActivityLogs(),
    ]);
});

const filteredEmployees = computed(() => employees.value);

// Watch for search query changes with debounce
watch(
    searchQuery,
    debounce(() => {
        pagination.value.current_page = 1;
        fetchPayrollDetails(1);
    }, 300),
);

// Watch for department filter changes
watch(departmentFilter, () => {
    pagination.value.current_page = 1;
    fetchPayrollDetails(1);
});

const refetchReconciliationWithFilters = debounce(() => {
    if (!payroll.value?.id) {
        return;
    }

    fetchPayrollReconciliation();
}, 250);

watch([reconciliationSeverityFilter, reconciliationTypeFilter], () => {
    refetchReconciliationWithFilters();
});

watch(reconciliationIssueTypeOptions, (options) => {
    if (reconciliationTypeFilter.value !== "all" && !options.includes(reconciliationTypeFilter.value)) {
        reconciliationTypeFilter.value = "all";
    }
});

const getAttendancePercentage = (attendedDays, totalDays) => {
    if (!totalDays) return 0;
    return Math.round((attendedDays / totalDays) * 100);
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

const resolveSnapshotLabel = (log) => {
    const versionNumber = log?.metadata?.settings_version_number;

    if (versionNumber) {
        return `Settings v${versionNumber}`;
    }

    return "Settings snapshot";
};

const formatSignedRupiah = (value) => {
    const amount = Number(value || 0);
    const absolute = formatRupiah(Math.abs(amount));

    if (amount > 0) {
        return `+${absolute}`;
    }

    if (amount < 0) {
        return `-${absolute}`;
    }

    return absolute;
};

const openAdjustmentDetailsModal = (employee) => {
    selectedAdjustmentEmployee.value = employee;
    showAdjustmentDetailsModal.value = true;
};

const closeAdjustmentDetailsModal = () => {
    showAdjustmentDetailsModal.value = false;
    selectedAdjustmentEmployee.value = null;
};

const formatAdjustmentKind = (kind) => {
    if (!kind) {
        return "Unknown adjustment";
    }

    return kind.replaceAll("_", " ").replace(/\b\w/g, (char) => char.toUpperCase());
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

const formatActivityTime = (value) => {
    if (!value) {
        return "Unknown time";
    }

    return new Date(value).toLocaleString("id-ID", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

const formatNotificationTime = (value) => {
    if (!value) {
        return "-";
    }

    return new Date(value).toLocaleString("id-ID", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

const formatReconciliationType = (type) => {
    if (!type) {
        return "Unknown issue";
    }

    return type.replaceAll("_", " ").replace(/\b\w/g, (char) => char.toUpperCase());
};

const getReconciliationSeverityClass = (severity) => {
    if (severity === "critical") {
        return "bg-red-100 text-red-700";
    }

    return "bg-amber-100 text-amber-700";
};

const formatResolutionAction = (action) => {
    if (!action) {
        return "Unknown";
    }

    return action.charAt(0).toUpperCase() + action.slice(1);
};

const formatNotificationStatus = (status) => {
    if (!status) {
        return "Unknown";
    }

    return status.replaceAll("_", " ").replace(/\b\w/g, (char) => char.toUpperCase());
};

const formatNotificationTrigger = (trigger) => {
    if (trigger === "auto_paid") {
        return "Auto Paid";
    }

    if (trigger === "manual_resend") {
        return "Manual Resend";
    }

    return "Unknown Trigger";
};

const getNotificationStatusClass = (status) => {
    if (status === "sent") {
        return "bg-green-100 text-green-700";
    }

    if (status === "failed") {
        return "bg-red-100 text-red-700";
    }

    if (status === "skipped") {
        return "bg-amber-100 text-amber-700";
    }

    return "bg-gray-100 text-gray-700";
};

const exportExcel = async () => {
    try {
        await payrollStore.exportExcel(route.params.id);
    } catch (error) {
        toast.error(
            "Download failed",
            payrollStore.error || error?.response?.data?.message || "Failed to export Excel file.",
        );
    }
};

const exportPdf = async () => {
    try {
        exportingPdf.value = true;
        await payrollStore.exportPdf(route.params.id);
        toast.success("Download ready", "Payroll payslip ZIP exported successfully.");
    } catch (error) {
        toast.error(
            "Download failed",
            payrollStore.error || error?.response?.data?.message || "Failed to export payslip ZIP file.",
        );
    } finally {
        exportingPdf.value = false;
    }
};

const handleMarkAsPaid = () => {
    if (hasCriticalReconciliationIssue.value) {
        toast.warning(
            "Payment blocked by reconciliation",
            "Resolve critical bank account issues first, then regenerate payroll before marking as paid.",
        );
        return;
    }

    doMarkAsPaid(() =>
        payrollStore.markAsPaid(route.params.id, {
            payment_date: paymentDate.value,
        }),
    );
};

const handleResendNotifications = () => {
    doResendNotifications(() => payrollStore.resendNotifications(route.params.id));
};

const handleReopenPayroll = () => {
    const reason = reopenReason.value.trim();

    if (reason.length < 10) {
        toast.warning("Reason required", "Provide at least 10 characters before reopening this payroll.");
        return;
    }

    doReopenPayroll(() =>
        payrollStore.reopenPayroll(route.params.id, {
            reason,
        }),
    );
};

const submitApprovalDecision = async (status) => {
    submittingApprovalDecision.value = true;
    approvalDecisionStatus.value = status;

    try {
        await payrollStore.submitPayrollApproval(route.params.id, {
            status,
            notes: approvalDecisionNotes.value.trim() || null,
        });
        toast.success("Approval decision submitted", "The payroll approval chain has been updated.");
        approvalDecisionNotes.value = "";
        await fetchPayrollSummary();
        await fetchPayrollApprovals();
        await fetchPayrollActivityLogs();
    } catch (error) {
        toast.error(
            "Failed to submit approval decision",
            payrollStore.error || error?.response?.data?.message || "Please verify your role and try again.",
        );
    } finally {
        submittingApprovalDecision.value = false;
    }
};

const handleApprovePayroll = () => {
    doApprovePayroll(async () => {
        await payrollStore.approvePayroll(route.params.id);
        await fetchPayrollApprovals();
    });
};
</script>

<template>
    <div class="space-y-6">
        <button
            @click="router.back()"
            class="border border-brand-border rounded-lg hover:ring-2 hover:ring-primary-500/20 hover:bg-gray-50 transition-all duration-300 px-3 py-2 flex items-center gap-2"
        >
            <ArrowLeft class="w-4 h-4 text-gray-600" />
            <span class="text-brand-dark text-base font-semibold">Back</span>
        </button>

        <template v-if="hasPayrollStatistics">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div
                    class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-primary-500/20 transition-all duration-300 p-6"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-brand-dark text-sm font-medium">Total Staff Members</p>
                            <p class="text-brand-dark text-3xl font-extrabold leading-tight my-2">
                                <template v-if="loadingStatistics">...</template>
                                <AnimatedValue v-else :value="payrollStatistics?.total_employees || 0" />
                            </p>
                            <p class="text-success text-sm font-medium">All departments</p>
                        </div>
                        <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center">
                            <Users class="w-7 h-7 text-blue-600" />
                        </div>
                    </div>
                </div>

                <div
                    class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-primary-500/20 transition-all duration-300 p-6"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-brand-dark text-sm font-medium">Total Payroll</p>
                            <p class="text-brand-dark text-3xl font-extrabold leading-tight my-2">
                                {{
                                    loadingStatistics
                                        ? "..."
                                        : formatRupiahCompact(payrollStatistics?.total_amount || 0)
                                }}
                            </p>
                            <p class="text-success text-sm font-medium">This period</p>
                        </div>
                        <div class="w-14 h-14 bg-green-50 rounded-2xl flex items-center justify-center">
                            <DollarSign class="w-7 h-7 text-green-600" />
                        </div>
                    </div>
                </div>

                <div
                    class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-primary-500/20 transition-all duration-300 p-6"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-brand-dark text-sm font-medium">Average Salary</p>
                            <p class="text-brand-dark text-3xl font-extrabold leading-tight my-2">
                                {{
                                    loadingStatistics
                                        ? "..."
                                        : formatRupiahCompact(payrollStatistics?.average_salary || 0)
                                }}
                            </p>
                            <p class="text-success text-sm font-medium">Per employee</p>
                        </div>
                        <div class="w-14 h-14 bg-purple-50 rounded-2xl flex items-center justify-center">
                            <Banknote class="w-7 h-7 text-purple-600" />
                        </div>
                    </div>
                </div>

                <div
                    class="bg-white border border-brand-border rounded-2xl hover:ring-2 hover:ring-primary-500/20 transition-all duration-300 p-6"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-brand-dark text-sm font-medium">Processed On</p>
                            <p class="text-brand-dark text-2xl font-extrabold leading-tight my-2">
                                {{
                                    loading
                                        ? "..."
                                        : new Date(payroll?.created_at).toLocaleDateString("id-ID", {
                                              month: "short",
                                              day: "numeric",
                                          })
                                }}
                            </p>
                            <p class="text-brand-light text-sm font-medium">
                                {{ new Date(payroll?.created_at).getFullYear() }}
                            </p>
                        </div>
                        <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center">
                            <CalendarCheck class="w-7 h-7 text-orange-600" />
                        </div>
                    </div>
                </div>
            </div>

            <div
                data-testid="payroll-settings-version-banner"
                class="mt-4 bg-white border border-brand-border rounded-2xl px-4 py-3 flex items-start justify-between gap-3"
            >
                <div>
                    <p class="text-brand-dark text-sm font-semibold">Settings Version Used</p>
                    <p class="text-brand-light text-sm mt-1">
                        This payroll draft references settings {{ payrollSettingsVersionLabel }}.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        v-if="isCorrected"
                        data-testid="payroll-correction-badge"
                        class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-700"
                    >
                        Corrected ×{{ correctionCount }}
                    </span>
                    <span class="inline-flex rounded-full bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">
                        {{ payrollSettingsVersionLabel }}
                    </span>
                </div>
            </div>
        </template>

        <div v-else class="bg-white border border-brand-border rounded-2xl p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-brand-dark text-xl font-bold">Payroll Draft Review</h3>
                    <p class="text-brand-light text-sm font-normal mt-1">
                        This view keeps company-wide salary statistics hidden while still allowing draft payroll review.
                    </p>
                    <p
                        data-testid="payroll-settings-version-inline"
                        class="text-brand-light text-xs font-semibold mt-2"
                    >
                        Settings version: {{ payrollSettingsVersionLabel }}
                    </p>
                </div>
                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                    <Users class="w-6 h-6 text-blue-600" />
                </div>
            </div>
        </div>

        <div class="bg-white border border-brand-border rounded-2xl p-3 mt-2 mb-6" data-testid="payroll-tabs">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <button
                    @click="activeTab = 'employees'"
                    class="rounded-lg px-4 py-3 border transition-all duration-300 flex items-center justify-center gap-2"
                    :class="
                        activeTab === 'employees'
                            ? 'blue-gradient blue-btn-shadow border-[#2151A0] text-white'
                            : 'border-brand-border text-brand-dark hover:ring-2 hover:ring-primary-500/20 bg-white'
                    "
                    data-testid="tab-employees"
                >
                    <Users class="w-4 h-4" :class="activeTab === 'employees' ? 'text-white' : 'text-gray-600'" />
                    <span class="text-sm font-semibold">Staff Member Details</span>
                </button>
                <button
                    @click="activeTab = 'reconciliation'"
                    class="rounded-lg px-4 py-3 border transition-all duration-300 flex items-center justify-center gap-2"
                    :class="
                        activeTab === 'reconciliation'
                            ? 'blue-gradient blue-btn-shadow border-[#2151A0] text-white'
                            : 'border-brand-border text-brand-dark hover:ring-2 hover:ring-primary-500/20 bg-white'
                    "
                    data-testid="tab-reconciliation"
                >
                    <AlertTriangle
                        class="w-4 h-4"
                        :class="activeTab === 'reconciliation' ? 'text-white' : 'text-gray-600'"
                    />
                    <span class="text-sm font-semibold">Reconciliation Check</span>
                </button>
                <button
                    @click="activeTab = 'settings'"
                    class="rounded-lg px-4 py-3 border transition-all duration-300 flex items-center justify-center gap-2"
                    :class="
                        activeTab === 'settings'
                            ? 'blue-gradient blue-btn-shadow border-[#2151A0] text-white'
                            : 'border-brand-border text-brand-dark hover:ring-2 hover:ring-primary-500/20 bg-white'
                    "
                    data-testid="tab-settings"
                >
                    <Settings class="w-4 h-4" :class="activeTab === 'settings' ? 'text-white' : 'text-gray-600'" />
                    <span class="text-sm font-semibold">Settings Used</span>
                </button>
                <button
                    @click="activeTab = 'activity'"
                    class="rounded-lg px-4 py-3 border transition-all duration-300 flex items-center justify-center gap-2"
                    :class="
                        activeTab === 'activity'
                            ? 'blue-gradient blue-btn-shadow border-[#2151A0] text-white'
                            : 'border-brand-border text-brand-dark hover:ring-2 hover:ring-primary-500/20 bg-white'
                    "
                    data-testid="tab-activity"
                >
                    <Activity class="w-4 h-4" :class="activeTab === 'activity' ? 'text-white' : 'text-gray-600'" />
                    <span class="text-sm font-semibold">Activity Logs</span>
                </button>
                <button
                    @click="activeTab = 'approvals'"
                    class="rounded-lg px-4 py-3 border transition-all duration-300 flex items-center justify-center gap-2"
                    :class="
                        activeTab === 'approvals'
                            ? 'blue-gradient blue-btn-shadow border-[#2151A0] text-white'
                            : 'border-brand-border text-brand-dark hover:ring-2 hover:ring-primary-500/20 bg-white'
                    "
                    data-testid="tab-approvals"
                >
                    <ShieldCheck class="w-4 h-4" :class="activeTab === 'approvals' ? 'text-white' : 'text-gray-600'" />
                    <span class="text-sm font-semibold">Approvals</span>
                </button>
            </div>
        </div>

        <div
            v-show="activeTab === 'employees'"
            class="bg-white border border-brand-border rounded-2xl p-6 animate-fade-in"
        >
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                        <Users class="w-6 h-6 text-blue-600" />
                    </div>
                    <div>
                        <h3 class="text-brand-dark text-xl font-bold">Staff Member Details</h3>
                        <p class="text-brand-light text-sm font-normal">Complete payroll breakdown by employee</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <Search class="h-4 w-4 text-gray-400" />
                        </div>
                        <input
                            type="text"
                            v-model="searchQuery"
                            placeholder="Search employees..."
                            aria-label="Search employees"
                            class="pl-10 pr-4 py-2 border border-brand-border rounded-[#12px] hover:border-primary-500 focus:border-primary-500 focus:ring-2 focus:ring-blue-100 transition-all duration-300 text-sm"
                        />
                    </div>

                    <select
                        v-model="departmentFilter"
                        aria-label="Filter by position"
                        class="px-3 py-2 border border-brand-border rounded-xl hover:border-primary-500 focus:border-primary-500 focus:ring-2 focus:ring-blue-100 transition-all duration-300 text-sm"
                    >
                        <option value="">All Positions</option>
                        <option value="Software Engineer">Software Engineer</option>
                        <option value="Product Manager">Product Manager</option>
                        <option value="UI/UX Designer">UI/UX Designer</option>
                        <option value="HR Manager">HR Manager</option>
                        <option value="Finance">Finance</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-4 font-semibold text-brand-dark text-sm">Employee</th>
                            <th class="text-left py-3 px-4 font-semibold text-brand-dark text-sm">Job Position</th>
                            <th class="text-left py-3 px-4 font-semibold text-brand-dark text-sm">Bank Account</th>
                            <th class="text-center py-3 px-4 font-semibold text-brand-dark text-sm">Attendance</th>
                            <th class="text-right py-3 px-4 font-semibold text-brand-dark text-sm">Basic Salary</th>
                            <th class="text-right py-3 px-4 font-semibold text-brand-dark text-sm">Deductions</th>
                            <th class="text-right py-3 px-4 font-semibold text-brand-dark text-sm">Adjustments</th>
                            <th class="text-right py-3 px-4 font-semibold text-brand-dark text-sm">Net Salary</th>
                            <th class="text-center py-3 px-4 font-semibold text-brand-dark text-sm">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="emp in filteredEmployees"
                            :key="emp.id"
                            class="border-b border-gray-100 hover:bg-gray-50 transition-colors duration-200"
                        >
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-3">
                                    <img loading="lazy"
                                        :src="emp.profile_photo || DEFAULT_AVATAR"
                                        :alt="emp.name"
                                        class="w-10 h-10 rounded-full object-cover"
                                    />
                                    <div>
                                        <p class="text-brand-dark text-sm font-semibold">
                                            {{ emp.name }}
                                        </p>
                                        <p class="text-brand-light text-xs">
                                            {{ emp.staff_member_id }} • {{ emp.position }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 px-4">
                                <span class="text-brand-dark text-sm">{{ emp.position }}</span>
                            </td>
                            <td class="py-4 px-4">
                                <div class="text-sm">
                                    <p class="text-brand-dark font-semibold">{{ emp.bank_name }}</p>
                                    <p class="text-brand-light text-xs">{{ emp.account_number }}</p>
                                </div>
                            </td>
                            <td class="py-4 px-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <span
                                        :class="[
                                            'text-sm font-semibold',
                                            getAttendancePercentage(emp.attended_days, emp.total_work_days) >= 90
                                                ? 'text-green-600'
                                                : getAttendancePercentage(emp.attended_days, emp.total_work_days) >= 80
                                                  ? 'text-yellow-600'
                                                  : 'text-red-600',
                                        ]"
                                    >
                                        {{ getAttendancePercentage(emp.attended_days, emp.total_work_days) }}%
                                    </span>
                                    <span class="text-xs text-brand-light">
                                        ({{ emp.attended_days }}/{{ emp.total_work_days }})
                                    </span>
                                </div>
                            </td>
                            <td class="py-4 px-4 text-right">
                                <span class="text-brand-dark text-sm font-semibold">
                                    {{ formatRupiah(emp.basic_salary) }}
                                </span>
                            </td>
                            <td class="py-4 px-4 text-right">
                                <span class="text-red-600 text-sm font-semibold">
                                    {{ formatRupiah(emp.deductions) }}
                                </span>
                            </td>
                            <td class="py-4 px-4 text-right" data-testid="payroll-adjustment-cell">
                                <button
                                    v-if="emp.adjustments.length > 0"
                                    type="button"
                                    @click="openAdjustmentDetailsModal(emp)"
                                    :data-testid="`payroll-adjustment-open-${emp.id}`"
                                    class="group ml-auto flex flex-col items-end rounded-lg px-2 py-1 transition-colors duration-200 hover:bg-blue-50"
                                >
                                    <span
                                        :class="[
                                            'text-sm font-semibold',
                                            emp.adjustment_total_amount >= 0 ? 'text-green-600' : 'text-red-600',
                                        ]"
                                    >
                                        {{ formatSignedRupiah(emp.adjustment_total_amount) }}
                                    </span>
                                    <span class="text-xs text-brand-light group-hover:text-blue-700">
                                        {{ emp.adjustments.length }} item • View details
                                    </span>
                                </button>
                                <span v-else class="text-brand-light text-sm">-</span>
                            </td>
                            <td class="py-4 px-4 text-right">
                                <span class="text-green-600 text-sm font-bold">{{ formatRupiah(emp.net_salary) }}</span>
                            </td>
                            <td class="py-4 px-4 text-center">
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">
                                    {{ emp.status === "paid" ? "Paid" : "Pending" }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="employees.length > 0" class="mt-6">
                <Pagination
                    :meta="pagination"
                    :loading="loadingDetails"
                    @page-change="handlePageChange"
                    @per-page-change="handlePerPageChange"
                />
            </div>
        </div>

        <div
            v-show="activeTab === 'approvals'"
            class="bg-white border border-brand-border rounded-2xl p-6 animate-fade-in"
        >
            <div class="flex items-center gap-3 mb-6">
                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                    <ShieldCheck class="w-6 h-6 text-blue-600" />
                </div>
                <div>
                    <h3 class="text-brand-dark text-xl font-bold">Approval Chain</h3>
                    <p class="text-brand-light text-sm font-normal">
                        Review threshold-based payroll approval steps and submit a role-based decision.
                    </p>
                </div>
            </div>

            <div v-if="loadingApprovals" class="py-8 text-sm text-brand-light">Loading approval chain...</div>
            <div
                v-else-if="!hasApprovalSteps"
                class="rounded-xl border border-dashed border-brand-border px-4 py-6 text-sm text-brand-light"
            >
                No multi-step approval chain has been started for this payroll. Use the Approve Payroll action to
                initialize matching approval policies.
            </div>
            <div v-else class="space-y-4">
                <div class="overflow-x-auto rounded-xl border border-brand-border">
                    <table class="min-w-full divide-y divide-[#E5E7EB] text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-brand-dark">Order</th>
                                <th class="px-4 py-3 text-left font-semibold text-brand-dark">Policy</th>
                                <th class="px-4 py-3 text-left font-semibold text-brand-dark">Required Role</th>
                                <th class="px-4 py-3 text-left font-semibold text-brand-dark">Status</th>
                                <th class="px-4 py-3 text-left font-semibold text-brand-dark">Approver</th>
                                <th class="px-4 py-3 text-left font-semibold text-brand-dark">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F1F5F9] bg-white">
                            <tr v-for="step in approvalSteps" :key="step.id">
                                <td class="px-4 py-3 font-semibold text-brand-dark">{{ step.approval_order }}</td>
                                <td class="px-4 py-3 text-brand-dark">{{ step.policy_name || "-" }}</td>
                                <td class="px-4 py-3 text-brand-dark">{{ step.required_role || "-" }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                        :class="
                                            step.status === 'approved'
                                                ? 'bg-green-100 text-green-700'
                                                : step.status === 'rejected'
                                                  ? 'bg-red-100 text-red-700'
                                                  : 'bg-yellow-100 text-yellow-700'
                                        "
                                    >
                                        {{ step.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-brand-dark">{{ step.approver?.name || "-" }}</td>
                                <td class="px-4 py-3 text-brand-light">{{ step.notes || "-" }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="payroll?.status === 'pending'"
                    class="rounded-2xl border border-blue-100 bg-blue-50 p-4 space-y-3"
                >
                    <label class="block text-sm font-semibold text-brand-dark">Decision Notes</label>
                    <textarea
                        v-model="approvalDecisionNotes"
                        rows="3"
                        class="w-full rounded-lg border border-brand-border px-4 py-3 text-sm"
                        placeholder="Optional notes for this approval decision"
                    ></textarea>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button
                            type="button"
                            :disabled="submittingApprovalDecision"
                            class="rounded-lg bg-green-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                            @click="submitApprovalDecision('approved')"
                        >
                            {{
                                submittingApprovalDecision && approvalDecisionStatus === "approved"
                                    ? "Submitting..."
                                    : "Approve My Step"
                            }}
                        </button>
                        <button
                            type="button"
                            :disabled="submittingApprovalDecision"
                            class="rounded-lg bg-red-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                            @click="submitApprovalDecision('rejected')"
                        >
                            {{
                                submittingApprovalDecision && approvalDecisionStatus === "rejected"
                                    ? "Submitting..."
                                    : "Reject My Step"
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-show="activeTab === 'activity'"
            class="bg-white border border-brand-border rounded-2xl p-6 animate-fade-in"
        >
            <div class="flex items-start justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-brand-dark text-lg font-bold">Payroll Activity</h3>
                    <p class="text-brand-light text-sm font-normal mt-1">
                        Trace important payroll actions for this payroll period.
                    </p>
                </div>
                <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center">
                    <CalendarCheck class="w-6 h-6 text-amber-600" />
                </div>
            </div>

            <div v-if="loadingActivityLogs" class="text-sm text-brand-light">Loading payroll activity...</div>

            <div
                v-else-if="activityLogs.length === 0"
                data-testid="payroll-activity-empty"
                class="rounded-2xl border border-dashed border-brand-border px-4 py-6 text-sm text-brand-light"
            >
                No payroll activity has been recorded yet.
            </div>

            <div v-else class="space-y-4" data-testid="payroll-activity-list">
                <div v-for="log in activityLogs" :key="log.id" class="rounded-2xl border border-brand-border px-4 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-brand-dark text-sm font-semibold">{{ log.title }}</p>
                            <p v-if="log.description" class="text-brand-light text-sm mt-1">
                                {{ log.description }}
                            </p>
                            <p class="text-brand-light text-xs mt-2">
                                {{ log.actor?.name || "System" }} • {{ formatActivityTime(log.occurred_at) }}
                            </p>

                            <div
                                v-if="log.event_type === 'generated' && log.metadata?.settings_snapshot"
                                data-testid="payroll-activity-settings-snapshot"
                                class="mt-3 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2"
                            >
                                <p class="text-blue-700 text-xs font-semibold">
                                    {{ resolveSnapshotLabel(log) }}
                                </p>
                                <p class="text-blue-700 text-xs mt-1">
                                    Cut-off {{ log.metadata?.settings_snapshot?.attendance_cutoff_day }} • Working days
                                    {{ formatWorkingDaysMode(log.metadata?.settings_snapshot?.working_days_mode) }} •
                                    Rounding {{ log.metadata?.settings_snapshot?.rounding_mode }}
                                </p>
                            </div>
                        </div>
                        <span
                            :class="[
                                'px-2 py-1 rounded-full text-xs font-semibold uppercase',
                                log.event_type === 'reopened_for_correction'
                                    ? 'bg-amber-100 text-amber-700'
                                    : log.event_type === 'approved'
                                      ? 'bg-green-100 text-green-700'
                                      : log.event_type === 'marked_paid'
                                        ? 'bg-blue-100 text-blue-700'
                                        : 'bg-gray-100 text-gray-700',
                            ]"
                        >
                            {{ log.event_type.replaceAll("_", " ") }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-show="activeTab === 'settings'"
            data-testid="payroll-settings-used-section"
            class="bg-white border border-brand-border rounded-2xl p-6 animate-fade-in"
        >
            <div class="flex items-start justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-brand-dark text-lg font-bold">Settings Used</h3>
                    <p class="text-brand-light text-sm font-normal mt-1">
                        Immutable payroll settings reference used when this payroll draft was generated.
                    </p>
                    <p
                        v-if="payrollSettingsVersion?.effective_at"
                        data-testid="payroll-settings-version-effective-date"
                        class="text-brand-dark text-sm font-semibold mt-2"
                    >
                        Settings Version #{{ payrollSettingsVersion.version_number }}
                        <span class="text-brand-light font-normal">
                            (effective
                            {{
                                new Date(payrollSettingsVersion.effective_at).toLocaleDateString("id-ID", {
                                    year: "numeric",
                                    month: "long",
                                    day: "numeric",
                                })
                            }})
                        </span>
                    </p>
                </div>
                <span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                    {{ payrollSettingsVersionLabel }}
                </span>
            </div>

            <div
                v-if="payrollSettingsVersion"
                data-testid="payroll-settings-used-grid"
                class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3"
            >
                <div class="rounded-xl border border-brand-border px-3 py-3">
                    <p class="text-xs uppercase tracking-wide text-brand-light">Payday Day</p>
                    <p class="mt-1 text-sm font-semibold text-brand-dark">
                        {{ payrollSettingsVersion.payday_day }}
                    </p>
                </div>
                <div class="rounded-xl border border-brand-border px-3 py-3">
                    <p class="text-xs uppercase tracking-wide text-brand-light">Attendance Cut-off</p>
                    <p class="mt-1 text-sm font-semibold text-brand-dark">
                        {{ payrollSettingsVersion.attendance_cutoff_day }}
                    </p>
                </div>
                <div class="rounded-xl border border-brand-border px-3 py-3">
                    <p class="text-xs uppercase tracking-wide text-brand-light">Working Days Mode</p>
                    <p class="mt-1 text-sm font-semibold text-brand-dark">
                        {{ formatWorkingDaysMode(payrollSettingsVersion.working_days_mode) }}
                    </p>
                </div>
                <div class="rounded-xl border border-brand-border px-3 py-3">
                    <p class="text-xs uppercase tracking-wide text-brand-light">Default Working Days</p>
                    <p class="mt-1 text-sm font-semibold text-brand-dark">
                        {{ payrollSettingsVersion.default_working_days }}
                    </p>
                </div>
                <div class="rounded-xl border border-brand-border px-3 py-3">
                    <p class="text-xs uppercase tracking-wide text-brand-light">Absent Deduction</p>
                    <p class="mt-1 text-sm font-semibold text-brand-dark">
                        {{ Number(payrollSettingsVersion.absent_deduction_rate || 0).toFixed(2) }}
                    </p>
                </div>
                <div class="rounded-xl border border-brand-border px-3 py-3">
                    <p class="text-xs uppercase tracking-wide text-brand-light">Rounding</p>
                    <p class="mt-1 text-sm font-semibold text-brand-dark">
                        {{ payrollSettingsVersion.rounding_mode }} ({{ payrollSettingsVersion.rounding_unit }})
                    </p>
                </div>
            </div>

            <div
                v-else
                data-testid="payroll-settings-used-legacy"
                class="rounded-xl border border-dashed border-amber-300 bg-amber-50 px-4 py-4 text-sm text-amber-800"
            >
                <p class="font-semibold">Legacy payroll settings reference</p>
                <p class="mt-1">
                    This payroll was created before settings versioning was introduced. Use activity logs and period
                    context for historical verification.
                </p>
            </div>

            <div
                v-if="isLegacySettingsVersion"
                data-testid="payroll-settings-used-legacy-warning"
                class="mt-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-xs text-amber-800"
            >
                Legacy payroll detected: version reference was backfilled or unavailable when generated.
            </div>
        </div>

        <div
            v-show="activeTab === 'reconciliation'"
            class="bg-white border border-brand-border rounded-2xl p-6 animate-fade-in"
        >
            <div class="flex items-start justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-brand-dark text-lg font-bold">Reconciliation Check</h3>
                    <p class="text-brand-light text-sm font-normal mt-1">
                        Critical issues must be resolved before payroll can be marked as paid.
                    </p>
                </div>
                <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center">
                    <AlertTriangle class="w-6 h-6 text-red-600" />
                </div>
            </div>

            <div v-if="loadingReconciliation" class="text-sm text-brand-light">Loading reconciliation checks...</div>

            <div v-else-if="reconciliationSummary" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="rounded-xl border border-brand-border px-4 py-3">
                        <p class="text-xs uppercase tracking-wide text-brand-light">Employees</p>
                        <p class="text-brand-dark text-2xl font-extrabold mt-1">
                            {{ reconciliationSummary.total_employees || 0 }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border px-4 py-3"
                        :class="
                            (reconciliationSummary.critical_count || 0) > 0
                                ? 'border-red-200 bg-red-50'
                                : 'border-brand-border'
                        "
                    >
                        <p class="text-xs uppercase tracking-wide text-brand-light">Critical Issues</p>
                        <p
                            data-testid="payroll-reconciliation-critical-count"
                            class="text-2xl font-extrabold mt-1"
                            :class="
                                (reconciliationSummary.critical_count || 0) > 0 ? 'text-red-700' : 'text-brand-dark'
                            "
                        >
                            {{ reconciliationSummary.critical_count || 0 }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                        <p class="text-xs uppercase tracking-wide text-amber-700">Warnings</p>
                        <p
                            data-testid="payroll-reconciliation-warning-count"
                            class="text-amber-700 text-2xl font-extrabold mt-1"
                        >
                            {{ reconciliationSummary.warning_count || 0 }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="totalReconciliationIssueCount === 0"
                    data-testid="payroll-reconciliation-empty"
                    class="rounded-2xl border border-dashed border-brand-border px-4 py-6 text-sm text-brand-light"
                >
                    No reconciliation issues were found for this payroll.
                </div>

                <div v-else class="space-y-3">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <label class="flex flex-col gap-1 text-xs font-semibold text-brand-light">
                            Severity
                            <select
                                v-model="reconciliationSeverityFilter"
                                data-testid="payroll-reconciliation-filter-severity"
                                class="px-3 py-2 border border-brand-border rounded-xl hover:border-primary-500 focus:border-primary-500 focus:ring-2 focus:ring-blue-100 transition-all duration-300 text-sm font-normal text-brand-dark"
                            >
                                <option value="all">All severities</option>
                                <option value="critical">Critical</option>
                                <option value="warning">Warning</option>
                            </select>
                        </label>

                        <label class="flex flex-col gap-1 text-xs font-semibold text-brand-light">
                            Issue Type
                            <select
                                v-model="reconciliationTypeFilter"
                                data-testid="payroll-reconciliation-filter-type"
                                class="px-3 py-2 border border-brand-border rounded-xl hover:border-primary-500 focus:border-primary-500 focus:ring-2 focus:ring-blue-100 transition-all duration-300 text-sm font-normal text-brand-dark"
                            >
                                <option value="all">All issue types</option>
                                <option
                                    v-for="issueType in reconciliationIssueTypeOptions"
                                    :key="issueType"
                                    :value="issueType"
                                >
                                    {{ formatReconciliationType(issueType) }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <p class="text-xs text-brand-light" data-testid="payroll-reconciliation-filter-summary">
                        Showing {{ displayedReconciliationIssueCount }} of {{ totalReconciliationIssueCount }} issue(s).
                    </p>

                    <div
                        v-if="displayedReconciliationIssueCount === 0"
                        data-testid="payroll-reconciliation-filter-empty"
                        class="rounded-2xl border border-dashed border-brand-border px-4 py-6 text-sm text-brand-light"
                    >
                        No reconciliation issues match the selected filters.
                    </div>

                    <div v-else class="space-y-3" data-testid="payroll-reconciliation-list">
                        <div
                            v-for="(issue, index) in filteredReconciliationExceptions"
                            :key="`${issue.staff_member_id}-${issue.type}-${index}`"
                            class="rounded-xl border border-brand-border px-4 py-3"
                            :class="{ 'opacity-60': issue.resolution }"
                            :data-testid="`reconciliation-exception-${issue.staff_member_id}-${issue.type}`"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p
                                        class="text-sm font-semibold text-brand-dark"
                                        :class="{ 'line-through': issue.resolution }"
                                    >
                                        {{ issue.employee_name }}
                                        <span v-if="issue.employee_code" class="text-brand-light font-normal">
                                            ({{ issue.employee_code }})
                                        </span>
                                    </p>
                                    <p class="text-xs text-brand-light mt-1">
                                        {{ formatReconciliationType(issue.type) }}
                                    </p>
                                    <p
                                        class="text-sm text-brand-dark mt-2"
                                        :class="{ 'line-through': issue.resolution }"
                                    >
                                        {{ issue.message }}
                                    </p>

                                    <!-- Resolution badge -->
                                    <div
                                        v-if="issue.resolution"
                                        data-testid="reconciliation-resolution-badge"
                                        class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700"
                                    >
                                        <ShieldCheck class="w-3.5 h-3.5" />
                                        <span>{{ formatResolutionAction(issue.resolution.action) }}</span>
                                        <span class="font-normal">by {{ issue.resolution.resolved_by_name }}</span>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full px-2 py-1 text-xs font-semibold uppercase',
                                            getReconciliationSeverityClass(issue.severity),
                                        ]"
                                    >
                                        {{ issue.severity }}
                                    </span>
                                    <button
                                        v-if="!issue.resolution && hasPayrollProcess"
                                        type="button"
                                        @click="openResolveExceptionModal(issue)"
                                        data-testid="reconciliation-resolve-btn"
                                        class="inline-flex items-center gap-1 rounded-lg border border-brand-border px-2.5 py-1.5 text-xs font-semibold text-brand-dark hover:border-primary-500 hover:bg-gray-50 transition-all duration-300"
                                    >
                                        <ShieldCheck class="w-3.5 h-3.5" />
                                        Resolve
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white border border-brand-border rounded-2xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-brand-dark text-lg font-bold">Export & Actions</h3>
                    <p class="text-brand-light text-sm font-normal mt-1">Download reports and manage payroll data</p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        v-if="hasPayrollList"
                        @click="exportPdf"
                        :disabled="exportingPdf"
                        data-testid="payroll-export-pdf"
                        class="border border-brand-border rounded-xl hover:ring-2 hover:ring-primary-500/20 hover:bg-gray-50 transition-all duration-300 px-4 py-2 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <Download class="w-4 h-4 text-gray-600" />
                        <span class="text-brand-dark text-sm font-semibold">
                            {{ exportingPdf ? "Exporting ZIP..." : "Export Payslip ZIP" }}
                        </span>
                    </button>

                    <button
                        v-if="hasPayrollList"
                        @click="exportExcel"
                        data-testid="payroll-export-excel"
                        class="border border-brand-border rounded-xl hover:ring-2 hover:ring-primary-500/20 hover:bg-gray-50 transition-all duration-300 px-4 py-2 flex items-center gap-2"
                    >
                        <Download class="w-4 h-4 text-gray-600" />
                        <span class="text-brand-dark text-sm font-semibold">Export Excel</span>
                    </button>

                    <button
                        v-if="canApprovePayroll"
                        @click="openApprovePayrollModal"
                        data-testid="payroll-approve"
                        class="border border-blue-600 bg-blue-50 rounded-xl hover:bg-blue-100 hover:border-blue-700 transition-all duration-300 px-4 py-2 flex items-center gap-2"
                    >
                        <CheckCircle class="w-4 h-4 text-blue-600" />
                        <span class="text-blue-700 text-sm font-semibold">Approve Payroll</span>
                    </button>

                    <button
                        v-if="canMarkPayrollAsPaid"
                        @click="openMarkAsPaidModal"
                        :disabled="!canTriggerMarkAsPaid"
                        data-testid="payroll-mark-as-paid"
                        class="border border-green-600 bg-green-50 rounded-xl hover:bg-green-100 hover:border-green-700 transition-all duration-300 px-4 py-2 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-green-50 disabled:hover:border-green-600"
                    >
                        <CheckCircle class="w-4 h-4 text-green-600" />
                        <span class="text-green-700 text-sm font-semibold">Mark as Paid</span>
                    </button>

                    <button
                        v-if="canReopenPayroll"
                        type="button"
                        @click="openReopenPayrollModal"
                        data-testid="payroll-reopen"
                        class="border border-amber-600 bg-amber-50 rounded-xl hover:bg-amber-100 hover:border-amber-700 transition-all duration-300 px-4 py-2 flex items-center gap-2"
                    >
                        <RotateCcw class="w-4 h-4 text-amber-700" />
                        <span class="text-amber-700 text-sm font-semibold">Reopen for Correction</span>
                    </button>
                </div>
            </div>

            <div
                v-if="payroll?.status === 'approved'"
                class="mt-4 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 flex items-start gap-3"
            >
                <CheckCircle class="w-5 h-5 text-blue-600 mt-0.5" />
                <div>
                    <p class="text-blue-700 text-sm font-semibold">Payroll approved</p>
                    <p class="text-blue-700 text-sm">Review is complete. Finance can now mark this payroll as paid.</p>
                </div>
            </div>

            <div
                v-if="canMarkPayrollAsPaid && hasCriticalReconciliationIssue"
                data-testid="payroll-reconciliation-blocked"
                class="mt-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 flex items-start gap-3"
            >
                <AlertTriangle class="w-5 h-5 text-red-600 mt-0.5" />
                <div>
                    <p class="text-red-700 text-sm font-semibold">Mark as Paid is locked</p>
                    <p class="text-red-700 text-sm">
                        Resolve critical bank account issues in reconciliation, then regenerate payroll before
                        completing payment.
                    </p>
                </div>
            </div>

            <div
                v-if="showsAutoNotificationInfo"
                data-testid="payroll-auto-notification-info"
                class="mt-4 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 flex items-start gap-3"
            >
                <CheckCircle class="w-5 h-5 text-green-600 mt-0.5" />
                <div>
                    <p class="text-green-700 text-sm font-semibold">Notifications sent automatically</p>
                    <p class="text-green-700 text-sm">
                        Employee notifications were sent automatically after this payroll was marked as paid.
                    </p>
                </div>
            </div>

            <div
                v-if="showsAutoNotificationInfo"
                data-testid="payroll-notification-delivery-panel"
                class="mt-4 rounded-2xl border border-brand-border px-4 py-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-brand-dark text-sm font-semibold">Notification Delivery Summary</p>
                        <p class="text-brand-light text-xs mt-1">
                            Finance can verify whether payroll handoff notifications reached each employee inbox.
                        </p>
                    </div>
                    <span class="rounded-full bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">
                        Paid Payroll
                    </span>
                </div>

                <div v-if="loadingNotificationDeliveries" class="mt-4 text-sm text-brand-light">
                    Loading delivery summary...
                </div>

                <div
                    v-else-if="notificationDeliverySummary"
                    class="mt-4 space-y-4"
                    data-testid="payroll-notification-delivery-summary"
                >
                    <div
                        data-testid="notification-delivery-rate"
                        class="rounded-xl border border-brand-border px-4 py-3"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-semibold text-brand-dark">Delivery Rate</p>
                            <span
                                class="text-sm font-extrabold"
                                :class="
                                    notificationDeliveryRate >= 80
                                        ? 'text-green-700'
                                        : notificationDeliveryRate >= 50
                                          ? 'text-amber-700'
                                          : 'text-red-700'
                                "
                            >
                                {{ notificationDeliveryRate }}%
                            </span>
                        </div>
                        <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="
                                    notificationDeliveryRate >= 80
                                        ? 'bg-green-500'
                                        : notificationDeliveryRate >= 50
                                          ? 'bg-amber-500'
                                          : 'bg-red-500'
                                "
                                :style="{ width: `${notificationDeliveryRate}%` }"
                            />
                        </div>
                        <p class="mt-1 text-xs text-brand-light">
                            {{ notificationDeliverySummary.sent_count || 0 }} of
                            {{ notificationDeliverySummary.total_recipients || 0 }} employees notified
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-xl border border-green-200 bg-green-50 px-3 py-3">
                            <p class="text-xs uppercase tracking-wide text-green-700">Sent</p>
                            <p class="mt-1 text-xl font-extrabold text-green-700">
                                {{ notificationDeliverySummary.sent_count || 0 }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-red-200 bg-red-50 px-3 py-3">
                            <p class="text-xs uppercase tracking-wide text-red-700">Failed</p>
                            <p class="mt-1 text-xl font-extrabold text-red-700">
                                {{ notificationDeliverySummary.failed_count || 0 }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-3">
                            <p class="text-xs uppercase tracking-wide text-amber-700">Skipped</p>
                            <p class="mt-1 text-xl font-extrabold text-amber-700">
                                {{ notificationDeliverySummary.skipped_count || 0 }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-brand-border px-3 py-3">
                            <p class="text-xs uppercase tracking-wide text-brand-light">Attempts</p>
                            <p class="mt-1 text-xl font-extrabold text-brand-dark">
                                {{ notificationDeliverySummary.total_attempts || 0 }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-2 text-xs text-brand-light sm:grid-cols-2">
                        <p>
                            Auto attempts:
                            <span class="font-semibold text-brand-dark">
                                {{ notificationDeliverySummary.auto_attempt_count || 0 }}
                            </span>
                        </p>
                        <p>
                            Manual resends:
                            <span class="font-semibold text-brand-dark">
                                {{ notificationDeliverySummary.manual_attempt_count || 0 }}
                            </span>
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-2 text-xs text-brand-light sm:grid-cols-2">
                        <p data-testid="payroll-notification-last-attempt">
                            Last attempt:
                            <span class="font-semibold text-brand-dark">
                                {{ formatNotificationTime(notificationDeliverySummary.last_attempt_at) }}
                            </span>
                        </p>
                        <p data-testid="payroll-notification-last-sent">
                            Last sent:
                            <span class="font-semibold text-brand-dark">
                                {{ formatNotificationTime(notificationDeliverySummary.last_sent_at) }}
                            </span>
                        </p>
                    </div>

                    <div
                        v-if="hasFailedDeliveries"
                        data-testid="notification-resend-failed"
                        class="flex items-center justify-between rounded-xl border border-red-200 bg-red-50 px-4 py-3"
                    >
                        <div class="flex items-center gap-2">
                            <XCircle class="w-4 h-4 text-red-600" />
                            <p class="text-sm text-red-700">
                                {{ notificationDeliverySummary.failed_count }} notification(s) failed to deliver.
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="openResendNotificationsModal"
                            data-testid="notification-resend-failed-btn"
                            class="rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 transition-all duration-300"
                        >
                            Resend to Failed
                        </button>
                    </div>

                    <div
                        v-if="latestNotificationDeliveries.length > 0"
                        class="space-y-2"
                        data-testid="payroll-notification-delivery-list"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-brand-light">
                            Latest Employee Status
                        </p>
                        <div
                            v-for="delivery in latestNotificationDeliveries"
                            :key="delivery.payroll_detail_id"
                            class="rounded-xl border border-brand-border px-3 py-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5">
                                        <CheckCircle
                                            v-if="delivery.delivery_status === 'sent'"
                                            class="w-5 h-5 text-green-600"
                                            data-testid="notification-status-icon-sent"
                                        />
                                        <XCircle
                                            v-else-if="delivery.delivery_status === 'failed'"
                                            class="w-5 h-5 text-red-600"
                                            data-testid="notification-status-icon-failed"
                                        />
                                        <Minus
                                            v-else
                                            class="w-5 h-5 text-gray-400"
                                            data-testid="notification-status-icon-skipped"
                                        />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-brand-dark">
                                            {{ delivery.employee_name || "Unknown Employee" }}
                                            <span v-if="delivery.employee_code" class="text-brand-light font-normal">
                                                ({{ delivery.employee_code }})
                                            </span>
                                        </p>
                                        <p class="mt-1 text-xs text-brand-light">
                                            {{ delivery.recipient_email || "No recipient email" }}
                                        </p>
                                        <p class="mt-2 text-xs text-brand-light">
                                            Trigger {{ formatNotificationTrigger(delivery.trigger_type) }} • Attempts
                                            {{ delivery.attempt_count || 0 }}
                                            <span v-if="delivery.sent_at">
                                                • Sent {{ formatNotificationTime(delivery.sent_at) }}
                                            </span>
                                        </p>
                                        <p
                                            v-if="delivery.payslip_path"
                                            data-testid="payroll-notification-deeplink"
                                            class="mt-1 text-xs text-blue-700"
                                        >
                                            Payslip deep-link: {{ delivery.payslip_path }}
                                        </p>
                                    </div>
                                </div>
                                <span
                                    :class="[
                                        'inline-flex rounded-full px-2 py-1 text-xs font-semibold uppercase',
                                        getNotificationStatusClass(delivery.delivery_status),
                                    ]"
                                >
                                    {{ formatNotificationStatus(delivery.delivery_status) }}
                                </span>
                            </div>
                            <p v-if="delivery.failure_reason" class="mt-2 ml-8 text-xs text-red-600">
                                {{ delivery.failure_reason }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    data-testid="payroll-notification-delivery-empty"
                    class="mt-4 rounded-xl border border-dashed border-brand-border px-4 py-4 text-sm text-brand-light"
                >
                    No notification delivery logs are available yet for this payroll.
                </div>
            </div>

            <div v-if="canResendNotifications" class="mt-4 flex items-center justify-end">
                <button
                    type="button"
                    @click="openResendNotificationsModal"
                    data-testid="payroll-resend-notifications"
                    class="border border-brand-border rounded-xl hover:border-primary-500 hover:bg-gray-50 transition-all duration-300 px-4 py-2 flex items-center gap-2"
                >
                    <CheckCircle class="w-4 h-4 text-gray-600" />
                    <span class="text-brand-dark text-sm font-semibold">Resend Notifications</span>
                </button>
            </div>
        </div>

        <ModalWrapper
            :show="showAdjustmentDetailsModal"
            title="Adjustment Details"
            maxWidth="xl"
            @close="closeAdjustmentDetailsModal"
        >
            <div data-testid="payroll-adjustment-detail-modal" class="space-y-4">
                <p class="text-brand-light text-sm">
                    Detailed adjustment history for
                    <span class="font-semibold text-brand-dark">{{ selectedAdjustmentEmployeeName }}</span>
                    .
                </p>

                <div
                    v-if="selectedAdjustmentItems.length === 0"
                    class="rounded-xl border border-dashed border-brand-border px-4 py-4 text-sm text-brand-light"
                >
                    No adjustments available for this staff member.
                </div>

                <div v-else class="space-y-3 max-h-[360px] overflow-y-auto pr-1">
                    <div
                        v-for="adjustment in selectedAdjustmentItems"
                        :key="adjustment.id"
                        class="rounded-xl border border-brand-border px-4 py-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-brand-dark">
                                    {{ adjustment.reason || formatAdjustmentKind(adjustment.adjustment_kind) }}
                                </p>
                                <p class="text-xs text-brand-light mt-1">
                                    {{ formatAdjustmentKind(adjustment.adjustment_kind) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <span
                                    :class="[
                                        'text-sm font-semibold',
                                        Number(adjustment.amount_delta || 0) >= 0 ? 'text-green-600' : 'text-red-600',
                                    ]"
                                >
                                    {{ formatSignedRupiah(adjustment.amount_delta) }}
                                </span>
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
                        </div>

                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs text-brand-light">
                            <p>
                                Days Delta:
                                <span class="font-semibold text-brand-dark">
                                    {{ Number(adjustment.days_delta || 0) }}
                                </span>
                            </p>
                            <p>
                                Source Period:
                                <span class="font-semibold text-brand-dark">
                                    #{{ adjustment.source_period_id ?? "-" }}
                                </span>
                            </p>
                            <p>
                                Target Period:
                                <span class="font-semibold text-brand-dark">
                                    #{{ adjustment.target_period_id ?? "-" }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end">
                    <button
                        type="button"
                        @click="closeAdjustmentDetailsModal"
                        class="border border-brand-border rounded-xl hover:border-primary-500 hover:bg-gray-50 transition-all duration-300 px-4 py-2"
                    >
                        <span class="text-brand-dark text-sm font-semibold">Close</span>
                    </button>
                </div>
            </template>
        </ModalWrapper>

        <!-- Mark as Paid Modal -->
        <ModalWrapper
            :show="showApprovePayrollModal"
            title="Approve Payroll"
            maxWidth="md"
            @close="closeApprovePayrollModal"
        >
            <div class="mb-6">
                <p class="text-brand-light text-sm">
                    Confirm that payroll review is complete and this payroll is ready for the payment step.
                </p>
            </div>

            <template #footer>
                <div class="flex items-center gap-3">
                    <button
                        @click="handleApprovePayroll"
                        :disabled="approvingPayroll"
                        data-testid="payroll-confirm-approve"
                        class="flex-1 btn-primary rounded-xl border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-primary-500 transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <CheckCircle class="w-4 h-4 text-white" />
                        <span class="text-brand-white text-sm font-semibold">
                            {{ approvingPayroll ? "Approving..." : "Confirm Approval" }}
                        </span>
                    </button>
                    <button
                        @click="closeApprovePayrollModal"
                        :disabled="approvingPayroll"
                        class="flex-1 border border-brand-border rounded-xl hover:border-primary-500 hover:bg-gray-50 transition-all duration-300 px-4 py-3 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span class="text-brand-dark text-sm font-semibold">Cancel</span>
                    </button>
                </div>
            </template>
        </ModalWrapper>

        <ModalWrapper
            :show="showMarkAsPaidModal"
            title="Mark Payroll as Paid"
            maxWidth="md"
            @close="closeMarkAsPaidModal"
        >
            <div class="mb-6">
                <p class="text-brand-light text-sm mb-4">
                    Confirm that you want to mark this payroll as paid. This action will update the status and record
                    the payment date.
                </p>

                <div
                    data-testid="payroll-mark-paid-notification-info"
                    class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 flex items-start gap-3"
                >
                    <Bell class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" />
                    <p class="text-blue-700 text-sm">
                        Employee notifications will be sent automatically once payroll is marked as paid.
                    </p>
                </div>

                <div>
                    <label class="block text-brand-dark text-sm font-semibold mb-2">Payment Date *</label>
                    <input
                        type="date"
                        v-model="paymentDate"
                        required
                        data-testid="payroll-payment-date"
                        class="w-full px-4 py-3 border border-brand-border rounded-xl hover:border-primary-500 focus:border-primary-500 focus:ring-2 focus:ring-blue-100 transition-all duration-300"
                    />
                </div>
            </div>

            <template #footer>
                <div class="flex items-center gap-3">
                    <button
                        @click="handleMarkAsPaid"
                        :disabled="markingAsPaid"
                        data-testid="payroll-confirm-mark-as-paid"
                        class="flex-1 btn-primary rounded-xl border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-primary-500 transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <CheckCircle class="w-4 h-4 text-white" />
                        <span class="text-brand-white text-sm font-semibold">
                            {{ markingAsPaid ? "Processing..." : "Confirm Payment" }}
                        </span>
                    </button>
                    <button
                        @click="closeMarkAsPaidModal"
                        :disabled="markingAsPaid"
                        class="flex-1 border border-brand-border rounded-xl hover:border-primary-500 hover:bg-gray-50 transition-all duration-300 px-4 py-3 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span class="text-brand-dark text-sm font-semibold">Cancel</span>
                    </button>
                </div>
            </template>
        </ModalWrapper>

        <ModalWrapper
            :show="showResendNotificationsModal"
            title="Resend Payroll Notifications"
            maxWidth="md"
            @close="closeResendNotificationsModal"
        >
            <div class="mb-6">
                <p class="text-brand-light text-sm">
                    Resend payroll paid notifications to all employees included in this payroll period. This will not
                    change the payroll status or payment date.
                </p>
            </div>

            <template #footer>
                <div class="flex items-center gap-3">
                    <button
                        @click="handleResendNotifications"
                        :disabled="resendingNotifications"
                        data-testid="payroll-confirm-resend-notifications"
                        class="flex-1 btn-primary rounded-xl border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-primary-500 transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <CheckCircle class="w-4 h-4 text-white" />
                        <span class="text-brand-white text-sm font-semibold">
                            {{ resendingNotifications ? "Resending..." : "Confirm Resend" }}
                        </span>
                    </button>
                    <button
                        @click="closeResendNotificationsModal"
                        :disabled="resendingNotifications"
                        class="flex-1 border border-brand-border rounded-xl hover:border-primary-500 hover:bg-gray-50 transition-all duration-300 px-4 py-3 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span class="text-brand-dark text-sm font-semibold">Cancel</span>
                    </button>
                </div>
            </template>
        </ModalWrapper>

        <ModalWrapper
            :show="showReopenPayrollModal"
            title="Reopen Payroll for Correction"
            maxWidth="md"
            @close="closeReopenPayrollModal"
        >
            <div class="mb-6">
                <p class="text-brand-light text-sm mb-3">
                    Reopening will move this payroll back to pending status and clear payment date so Finance can apply
                    corrections safely.
                </p>

                <div class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 mb-4">
                    <svg class="w-4 h-4 text-amber-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" /></svg>
                    <div class="text-xs text-amber-800">
                        <span class="font-semibold">Re-approval required.</span>
                        After correction, payroll must go through the approval process again before payment.
                        <span v-if="correctionCount > 0" class="block mt-0.5 text-amber-700">
                            This payroll has been corrected {{ correctionCount }} time{{ correctionCount === 1 ? '' : 's' }} previously.
                        </span>
                    </div>
                </div>

                <div>
                    <label class="block text-brand-dark text-sm font-semibold mb-2">Reopen reason *</label>
                    <textarea
                        v-model="reopenReason"
                        rows="4"
                        maxlength="500"
                        data-testid="payroll-reopen-reason"
                        class="w-full px-4 py-3 border border-brand-border rounded-xl hover:border-primary-500 focus:border-primary-500 focus:ring-2 focus:ring-blue-100 transition-all duration-300 resize-none"
                        placeholder="Explain why this payroll needs to be reopened for correction."
                    />
                    <p class="text-brand-light text-xs mt-2">
                        {{ reopenReason.trim().length }}/500 characters (minimum 10)
                    </p>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center gap-3">
                    <button
                        @click="handleReopenPayroll"
                        :disabled="reopeningPayroll || reopenReason.trim().length < 10"
                        data-testid="payroll-confirm-reopen"
                        class="flex-1 btn-primary rounded-xl border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-primary-500 transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <RotateCcw class="w-4 h-4 text-white" />
                        <span class="text-brand-white text-sm font-semibold">
                            {{ reopeningPayroll ? "Reopening..." : "Confirm Reopen" }}
                        </span>
                    </button>
                    <button
                        @click="closeReopenPayrollModal"
                        :disabled="reopeningPayroll"
                        class="flex-1 border border-brand-border rounded-xl hover:border-primary-500 hover:bg-gray-50 transition-all duration-300 px-4 py-3 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span class="text-brand-dark text-sm font-semibold">Cancel</span>
                    </button>
                </div>
            </template>
        </ModalWrapper>

        <!-- Resolve Reconciliation Exception Modal -->
        <ModalWrapper
            :show="showResolveExceptionModal"
            title="Resolve Reconciliation Exception"
            maxWidth="md"
            @close="closeResolveExceptionModal"
        >
            <div data-testid="reconciliation-resolve-modal" class="space-y-4">
                <p class="text-brand-light text-sm">
                    Resolve the exception for
                    <span class="font-semibold text-brand-dark">{{ resolveExceptionTarget?.employee_name }}</span>
                    ({{ formatReconciliationType(resolveExceptionTarget?.type) }}).
                </p>

                <div>
                    <label class="block text-brand-dark text-sm font-semibold mb-2">Resolution Action *</label>
                    <select
                        v-model="resolveExceptionForm.resolution_action"
                        data-testid="reconciliation-resolve-action"
                        class="w-full px-4 py-3 border border-brand-border rounded-xl hover:border-primary-500 focus:border-primary-500 focus:ring-2 focus:ring-blue-100 transition-all duration-300"
                    >
                        <option value="acknowledged">Acknowledged</option>
                        <option value="resolved">Resolved</option>
                        <option value="waived">Waived</option>
                    </select>
                </div>

                <div>
                    <label class="block text-brand-dark text-sm font-semibold mb-2">Reason *</label>
                    <textarea
                        v-model="resolveExceptionForm.reason"
                        rows="4"
                        maxlength="500"
                        data-testid="reconciliation-resolve-reason"
                        class="w-full px-4 py-3 border border-brand-border rounded-xl hover:border-primary-500 focus:border-primary-500 focus:ring-2 focus:ring-blue-100 transition-all duration-300 resize-none"
                        placeholder="Explain why this exception is being resolved (minimum 10 characters)."
                    />
                    <p class="text-brand-light text-xs mt-2">
                        {{ resolveExceptionForm.reason.trim().length }}/500 characters (minimum 10)
                    </p>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center gap-3">
                    <button
                        @click="handleResolveException"
                        :disabled="resolvingException || resolveExceptionForm.reason.trim().length < 10"
                        data-testid="reconciliation-resolve-confirm"
                        class="flex-1 btn-primary rounded-xl border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-primary-500 transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <ShieldCheck class="w-4 h-4 text-white" />
                        <span class="text-brand-white text-sm font-semibold">
                            {{ resolvingException ? "Resolving..." : "Confirm Resolution" }}
                        </span>
                    </button>
                    <button
                        @click="closeResolveExceptionModal"
                        :disabled="resolvingException"
                        class="flex-1 border border-brand-border rounded-xl hover:border-primary-500 hover:bg-gray-50 transition-all duration-300 px-4 py-3 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span class="text-brand-dark text-sm font-semibold">Cancel</span>
                    </button>
                </div>
            </template>
        </ModalWrapper>
    </div>
</template>
