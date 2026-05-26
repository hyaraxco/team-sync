<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { usePayrollStore } from "@/stores/payroll";
import { useRouter } from "vue-router";
import { useToast } from "@/composables/useToast";
import {
    Calendar,
    RefreshCw,
    Download,
    ArrowRight,
    Search,
    ChevronDown,
    ChevronRight,
    Users,
    CheckCircle,
    AlertTriangle,
    XCircle,
    ExternalLink,
} from "lucide-vue-next";

const router = useRouter();
const toast = useToast();
const payrollStore = usePayrollStore();

const loading = ref(false);
const readinessDashboard = ref(null);
const teamSummary = ref(null);
const readinessStatusFilter = ref("all");
const readinessSearchQuery = ref("");
const expandedTeams = ref({});
const expandedRows = ref({});
const sortColumn = ref("status");
const sortDirection = ref("asc");
const activeBlockerFilter = ref(null);
const activeWarningFilter = ref(null);

const formatDateToMonthKey = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    return `${year}-${month}`;
};

const salaryMonth = ref(formatDateToMonthKey(new Date()));

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

const readinessStatusFilters = [
    { value: "all", label: "All" },
    { value: "blocked", label: "Blocked" },
    { value: "warning", label: "Warning" },
    { value: "ready", label: "Ready" },
];

const summary = computed(() => readinessDashboard.value?.summary ?? {});
const employees = computed(() => readinessDashboard.value?.employees ?? []);
const blockedReasons = computed(() => readinessDashboard.value?.blocked_reasons ?? {});
const warningFlags = computed(() => readinessDashboard.value?.warning_flags ?? {});

const totalEmployees = computed(() => summary.value.total_employees ?? 0);
const readyEmployees = computed(() => summary.value.ready_employees ?? 0);
const warningEmployees = computed(() => summary.value.warning_employees ?? 0);
const blockedEmployees = computed(() => summary.value.blocked_employees ?? 0);

const overallReadinessPct = computed(() => {
    if (totalEmployees.value === 0) return 0;
    return Math.round((readyEmployees.value / totalEmployees.value) * 100);
});

const overallCoveragePct = computed(() => {
    const emps = employees.value;
    if (emps.length === 0) return 0;
    let totalCovered = 0;
    let totalScheduled = 0;
    emps.forEach((emp) => {
        totalCovered += emp.metrics?.covered_days ?? 0;
        totalScheduled += emp.metrics?.scheduled_working_days ?? 0;
    });
    if (totalScheduled === 0) return 0;
    return Math.round((totalCovered / totalScheduled) * 100);
});

const blockedReasonAggregates = computed(() => {
    const reasons = blockedReasons.value;
    const result = [];
    for (const [key, employeeIds] of Object.entries(reasons)) {
        if (employeeIds && employeeIds.length > 0) {
            result.push({
                key,
                label: readinessReasonLabels[key] || key,
                count: employeeIds.length,
                employeeIds,
            });
        }
    }
    return result.sort((a, b) => b.count - a.count);
});

const warningFlagAggregates = computed(() => {
    const flags = warningFlags.value;
    const result = [];
    for (const [key, employeeIds] of Object.entries(flags)) {
        if (employeeIds && employeeIds.length > 0) {
            result.push({
                key,
                label: readinessWarningLabels[key] || key,
                count: employeeIds.length,
                employeeIds,
            });
        }
    }
    return result.sort((a, b) => b.count - a.count);
});

const filteredEmployees = computed(() => {
    const query = readinessSearchQuery.value.trim().toLowerCase();

    return employees.value.filter((row) => {
        if (readinessStatusFilter.value !== "all" && row.status !== readinessStatusFilter.value) {
            return false;
        }

        if (activeBlockerFilter.value) {
            const ids = activeBlockerFilter.value.employeeIds ?? [];
            if (!ids.includes(row.staff_member_id)) return false;
        }

        if (activeWarningFilter.value) {
            const ids = activeWarningFilter.value.employeeIds ?? [];
            if (!ids.includes(row.staff_member_id)) return false;
        }

        if (query) {
            const searchable = `${row.employee_name ?? ""} ${row.employee_code ?? ""}`.toLowerCase();
            if (!searchable.includes(query)) return false;
        }

        return true;
    });
});

const sortedEmployees = computed(() => {
    const list = [...filteredEmployees.value];
    const col = sortColumn.value;
    const dir = sortDirection.value === "asc" ? 1 : -1;

    list.sort((a, b) => {
        let aVal, bVal;

        if (col === "status") {
            const priority = { blocked: 0, warning: 1, ready: 2 };
            aVal = priority[a.status] ?? 3;
            bVal = priority[b.status] ?? 3;
        } else if (col === "name") {
            aVal = (a.employee_name ?? "").toLowerCase();
            bVal = (b.employee_name ?? "").toLowerCase();
            return aVal.localeCompare(bVal) * dir;
        } else if (col === "code") {
            aVal = (a.employee_code ?? "").toLowerCase();
            bVal = (b.employee_code ?? "").toLowerCase();
            return aVal.localeCompare(bVal) * dir;
        } else if (col === "team") {
            aVal = (a.team_name ?? "").toLowerCase();
            bVal = (b.team_name ?? "").toLowerCase();
            return aVal.localeCompare(bVal) * dir;
        } else if (col === "coverage") {
            const aCov =
                a.metrics?.scheduled_working_days > 0
                    ? (a.metrics.covered_days / a.metrics.scheduled_working_days) * 100
                    : 0;
            const bCov =
                b.metrics?.scheduled_working_days > 0
                    ? (b.metrics.covered_days / b.metrics.scheduled_working_days) * 100
                    : 0;
            aVal = aCov;
            bVal = bCov;
        } else {
            aVal = 0;
            bVal = 0;
        }

        if (aVal < bVal) return -1 * dir;
        if (aVal > bVal) return 1 * dir;
        return 0;
    });

    return list;
});

const statusCounts = computed(() => ({
    all: employees.value.length,
    blocked: employees.value.filter((e) => e.status === "blocked").length,
    warning: employees.value.filter((e) => e.status === "warning").length,
    ready: employees.value.filter((e) => e.status === "ready").length,
}));

const teams = computed(() => teamSummary.value?.teams ?? []);

const getCoveragePct = (row) => {
    const scheduled = row.metrics?.scheduled_working_days ?? 0;
    if (scheduled === 0) return 0;
    return Math.round((row.metrics.covered_days / scheduled) * 100);
};

const toggleSort = (col) => {
    if (sortColumn.value === col) {
        sortDirection.value = sortDirection.value === "asc" ? "desc" : "asc";
    } else {
        sortColumn.value = col;
        sortDirection.value = "asc";
    }
};

const toggleTeam = (teamName) => {
    expandedTeams.value[teamName] = !expandedTeams.value[teamName];
};

const toggleRow = (staffMemberId) => {
    expandedRows.value[staffMemberId] = !expandedRows.value[staffMemberId];
};

const setStatusFilter = (status) => {
    readinessStatusFilter.value = status;
    activeBlockerFilter.value = null;
    activeWarningFilter.value = null;
};

const filterByBlocker = (blocker) => {
    activeBlockerFilter.value = blocker;
    activeWarningFilter.value = null;
    readinessStatusFilter.value = "all";
};

const filterByWarning = (warning) => {
    activeWarningFilter.value = warning;
    activeBlockerFilter.value = null;
    readinessStatusFilter.value = "all";
};

const clearAggregateFilter = () => {
    activeBlockerFilter.value = null;
    activeWarningFilter.value = null;
};

const openAttendanceWorkspace = (row) => {
    const target = row?.attendance_workspace_url || "/admin/attendances";
    router.push(target);
};

const goToGeneratePayroll = () => {
    router.push({ name: "admin.payroll.create" });
};

const formatMonth = (month) => {
    if (!month) return "-";
    const [year, monthNum] = month.split("-");
    const date = new Date(year, monthNum - 1);
    return date.toLocaleDateString("id-ID", { year: "numeric", month: "long" });
};

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

const exportCsv = () => {
    const rows = sortedEmployees.value;
    if (rows.length === 0) {
        toast.warning("No data available to export");
        return;
    }

    const headers = [
        "Name",
        "Code",
        "Team",
        "Status",
        "Coverage %",
        "Scheduled Days",
        "Covered Days",
        "Present Days",
        "Late Days",
        "Half Day Count",
        "Paid Leave Days",
        "Unpaid Leave Days",
        "Absent Days",
        "Blockers",
        "Warnings",
    ];

    const csvRows = rows.map((row) => [
        row.employee_name ?? "",
        row.employee_code ?? "",
        row.team_name ?? "",
        row.status ?? "",
        getCoveragePct(row),
        row.metrics?.scheduled_working_days ?? 0,
        row.metrics?.covered_days ?? 0,
        row.metrics?.present_days ?? 0,
        row.metrics?.late_days ?? 0,
        row.metrics?.half_day_count ?? 0,
        row.metrics?.paid_leave_days ?? 0,
        row.metrics?.unpaid_leave_days ?? 0,
        row.metrics?.absent_days ?? 0,
        (row.blocker_reasons ?? []).map((r) => mapReadinessLabel(r, readinessReasonLabels)).join("; "),
        (row.warning_flags ?? []).map((f) => mapReadinessLabel(f, readinessWarningLabels)).join("; "),
    ]);

    const csvContent = [
        headers.join(","),
        ...csvRows.map((r) => r.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(",")),
    ].join("\n");

    const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", `Payroll_Readiness_${salaryMonth.value}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);

    toast.success("Export complete", "Readiness report downloaded.");
};

const fetchData = async () => {
    loading.value = true;
    try {
        const [dashboard, teamData] = await Promise.all([
            payrollStore.fetchReadinessDashboard(salaryMonth.value),
            payrollStore.fetchReadinessTeamSummary(salaryMonth.value),
        ]);
        readinessDashboard.value = dashboard;
        teamSummary.value = teamData;
    } catch (_error) {
        toast.error("Failed to load readiness data", payrollStore.error || "Please try again.");
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchData();
});

watch(salaryMonth, () => {
    fetchData();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Header Section -->
        <div class="bg-white border border-brand-border rounded-2xl p-6">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                        <Users class="w-6 h-6 text-blue-600" />
                    </div>
                    <div>
                        <h1 class="text-brand-dark text-xl font-bold">Attendance-to-Payroll Readiness</h1>
                        <p class="text-brand-light text-sm font-normal">
                            Identify and resolve blockers before payroll generation
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <Calendar class="h-4 w-4 text-gray-400" />
                        </div>
                        <input
                            type="month"
                            v-model="salaryMonth"
                            data-testid="readiness-month-selector"
                            class="pl-10 pr-4 py-2 border border-brand-border rounded-xl text-sm font-semibold hover:border-brand-primary focus:border-brand-primary transition-all duration-300"
                        />
                    </div>
                    <button
                        type="button"
                        data-testid="readiness-refresh-btn"
                        @click="fetchData"
                        :disabled="loading"
                        class="border border-brand-border rounded-xl px-4 py-2 text-sm font-semibold text-slate-700 hover:border-brand-primary hover:text-brand-primary transition-all duration-300 flex items-center gap-2 disabled:opacity-50"
                    >
                        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" />
                        Refresh
                    </button>
                </div>
            </div>

            <!-- Overall Progress Bar -->
            <div v-if="readinessDashboard" class="mt-5">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-semibold text-slate-700">Overall Readiness</span>
                    <span class="text-sm font-bold text-slate-900" data-testid="readiness-overall-pct">
                        {{ overallReadinessPct }}%
                    </span>
                </div>
                <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all duration-500"
                        :class="
                            overallReadinessPct === 100
                                ? 'bg-green-500'
                                : overallReadinessPct >= 70
                                  ? 'bg-blue-500'
                                  : 'bg-amber-500'
                        "
                        :style="{ width: `${overallReadinessPct}%` }"
                        data-testid="readiness-progress-bar"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Summary Stat Cards -->
        <div
            v-if="readinessDashboard"
            class="grid grid-cols-2 md:grid-cols-5 gap-4"
            data-testid="readiness-summary-cards"
        >
            <div class="bg-white border border-brand-border rounded-2xl p-4">
                <p class="text-slate-500 text-xs font-medium">Total</p>
                <p class="text-slate-900 text-2xl font-bold mt-1" data-testid="readiness-total">{{ totalEmployees }}</p>
            </div>
            <div class="bg-white border border-green-200 rounded-2xl p-4">
                <p class="text-green-600 text-xs font-medium">Ready</p>
                <p class="text-green-700 text-2xl font-bold mt-1" data-testid="readiness-ready">{{ readyEmployees }}</p>
            </div>
            <div class="bg-white border border-amber-200 rounded-2xl p-4">
                <p class="text-amber-600 text-xs font-medium">Warning</p>
                <p class="text-amber-700 text-2xl font-bold mt-1" data-testid="readiness-warning">
                    {{ warningEmployees }}
                </p>
            </div>
            <div class="bg-white border border-red-200 rounded-2xl p-4">
                <p class="text-red-600 text-xs font-medium">Blocked</p>
                <p class="text-red-700 text-2xl font-bold mt-1" data-testid="readiness-blocked">
                    {{ blockedEmployees }}
                </p>
            </div>
            <div class="bg-white border border-brand-border rounded-2xl p-4">
                <p class="text-slate-500 text-xs font-medium">Coverage</p>
                <p class="text-slate-900 text-2xl font-bold mt-1" data-testid="readiness-coverage">
                    {{ overallCoveragePct }}%
                </p>
            </div>
        </div>

        <!-- Main Content: Two Column Layout -->
        <div class="flex gap-5 items-start" v-if="readinessDashboard">
            <!-- Left: Table + Team Breakdown -->
            <div class="flex-1 space-y-5">
                <!-- Team Breakdown Section -->
                <div
                    v-if="teams.length > 0"
                    class="bg-white border border-brand-border rounded-2xl p-6"
                    data-testid="readiness-team-breakdown"
                >
                    <h2 class="text-brand-dark text-lg font-bold mb-4">Team Breakdown</h2>
                    <div class="space-y-3">
                        <div
                            v-for="team in teams"
                            :key="team.team_name"
                            class="border border-slate-200 rounded-xl overflow-hidden"
                        >
                            <button
                                type="button"
                                @click="toggleTeam(team.team_name)"
                                class="w-full flex items-center justify-between px-4 py-3 hover:bg-slate-50 transition-colors"
                            >
                                <div class="flex items-center gap-3">
                                    <component
                                        :is="expandedTeams[team.team_name] ? ChevronDown : ChevronRight"
                                        class="w-4 h-4 text-slate-400"
                                    />
                                    <span class="text-sm font-semibold text-slate-800">{{ team.team_name }}</span>
                                    <span class="text-xs text-slate-500">({{ team.total }} members)</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-32 h-2 bg-slate-100 rounded-full overflow-hidden">
                                        <div
                                            class="h-full bg-green-500 rounded-full"
                                            :style="{
                                                width: `${team.total > 0 ? (team.ready / team.total) * 100 : 0}%`,
                                            }"
                                        ></div>
                                    </div>
                                    <span class="text-xs font-semibold text-slate-600">
                                        {{ team.ready }}/{{ team.total }}
                                    </span>
                                </div>
                            </button>
                            <div v-if="expandedTeams[team.team_name]" class="px-4 pb-3 border-t border-slate-100">
                                <div class="grid grid-cols-4 gap-2 mt-3 text-xs">
                                    <div class="text-center">
                                        <p class="text-green-600 font-semibold">{{ team.ready }}</p>
                                        <p class="text-slate-500">Ready</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-amber-600 font-semibold">{{ team.warning }}</p>
                                        <p class="text-slate-500">Warning</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-red-600 font-semibold">{{ team.blocked }}</p>
                                        <p class="text-slate-500">Blocked</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-slate-800 font-semibold">{{ team.coverage_pct }}%</p>
                                        <p class="text-slate-500">Coverage</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Employee Table Section -->
                <div class="bg-white border border-brand-border rounded-2xl p-6" data-testid="readiness-employee-table">
                    <div class="flex items-center justify-between gap-4 mb-4 flex-wrap">
                        <h2 class="text-brand-dark text-lg font-bold">Employee Readiness</h2>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                data-testid="readiness-export-btn"
                                @click="exportCsv"
                                class="border border-brand-border rounded-lg px-3 py-2 text-xs font-semibold text-slate-700 hover:border-brand-primary hover:text-brand-primary transition-all duration-300 flex items-center gap-1.5"
                            >
                                <Download class="w-3.5 h-3.5" />
                                Export CSV
                            </button>
                        </div>
                    </div>

                    <!-- Filter Tabs -->
                    <div class="flex flex-wrap gap-2 mb-4">
                        <button
                            v-for="filter in readinessStatusFilters"
                            :key="filter.value"
                            type="button"
                            :data-testid="`readiness-filter-${filter.value}`"
                            class="text-xs px-3 py-1.5 rounded-full border transition-all duration-300"
                            :class="
                                readinessStatusFilter === filter.value && !activeBlockerFilter && !activeWarningFilter
                                    ? 'border-brand-primary bg-blue-50 text-brand-primary'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-brand-primary hover:text-brand-primary'
                            "
                            @click="setStatusFilter(filter.value)"
                        >
                            {{ filter.label }}
                            <span class="font-semibold">({{ statusCounts[filter.value] ?? 0 }})</span>
                        </button>
                        <button
                            v-if="activeBlockerFilter || activeWarningFilter"
                            type="button"
                            data-testid="readiness-clear-aggregate-filter"
                            @click="clearAggregateFilter"
                            class="text-xs px-3 py-1.5 rounded-full border border-red-200 bg-red-50 text-red-600 hover:bg-red-100 transition-all duration-300"
                        >
                            Clear filter: {{ activeBlockerFilter?.label || activeWarningFilter?.label }}
                            &times;
                        </button>
                    </div>

                    <!-- Search -->
                    <div class="relative mb-4">
                        <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            v-model="readinessSearchQuery"
                            data-testid="readiness-search"
                            type="text"
                            placeholder="Search by name or employee code..."
                            class="w-full pl-10 pr-4 py-2.5 text-sm border border-slate-200 rounded-xl bg-white focus:border-brand-primary outline-none transition-all duration-300"
                        />
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm" data-testid="readiness-table">
                            <thead>
                                <tr class="border-b border-slate-200">
                                    <th
                                        class="text-left py-3 px-3 text-xs font-semibold text-slate-500 uppercase tracking-wide cursor-pointer hover:text-slate-800"
                                        @click="toggleSort('name')"
                                    >
                                        Name
                                    </th>
                                    <th
                                        class="text-left py-3 px-3 text-xs font-semibold text-slate-500 uppercase tracking-wide cursor-pointer hover:text-slate-800"
                                        @click="toggleSort('code')"
                                    >
                                        Code
                                    </th>
                                    <th
                                        class="text-left py-3 px-3 text-xs font-semibold text-slate-500 uppercase tracking-wide cursor-pointer hover:text-slate-800"
                                        @click="toggleSort('team')"
                                    >
                                        Team
                                    </th>
                                    <th
                                        class="text-left py-3 px-3 text-xs font-semibold text-slate-500 uppercase tracking-wide cursor-pointer hover:text-slate-800"
                                        @click="toggleSort('status')"
                                    >
                                        Status
                                    </th>
                                    <th
                                        class="text-left py-3 px-3 text-xs font-semibold text-slate-500 uppercase tracking-wide cursor-pointer hover:text-slate-800"
                                        @click="toggleSort('coverage')"
                                    >
                                        Coverage %
                                    </th>
                                    <th
                                        class="text-left py-3 px-3 text-xs font-semibold text-slate-500 uppercase tracking-wide"
                                    >
                                        Blockers
                                    </th>
                                    <th
                                        class="text-left py-3 px-3 text-xs font-semibold text-slate-500 uppercase tracking-wide"
                                    >
                                        Warnings
                                    </th>
                                    <th
                                        class="text-right py-3 px-3 text-xs font-semibold text-slate-500 uppercase tracking-wide"
                                    >
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="row in sortedEmployees" :key="row.staff_member_id">
                                    <tr
                                        class="border-b border-slate-100 hover:bg-slate-50 transition-colors cursor-pointer"
                                        :data-testid="`readiness-row-${row.staff_member_id}`"
                                        @click="toggleRow(row.staff_member_id)"
                                    >
                                        <td class="py-3 px-3 font-semibold text-slate-800">{{ row.employee_name }}</td>
                                        <td class="py-3 px-3 text-slate-600">{{ row.employee_code || "-" }}</td>
                                        <td class="py-3 px-3 text-slate-600">{{ row.team_name || "-" }}</td>
                                        <td class="py-3 px-3">
                                            <span
                                                class="text-[11px] font-semibold px-2 py-1 rounded-full"
                                                :class="
                                                    row.status === 'blocked'
                                                        ? 'bg-red-100 text-red-700'
                                                        : row.status === 'warning'
                                                          ? 'bg-amber-100 text-amber-700'
                                                          : 'bg-green-100 text-green-700'
                                                "
                                            >
                                                {{
                                                    row.status === "blocked"
                                                        ? "Blocked"
                                                        : row.status === "warning"
                                                          ? "Warning"
                                                          : "Ready"
                                                }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                                    <div
                                                        class="h-full rounded-full"
                                                        :class="
                                                            getCoveragePct(row) === 100
                                                                ? 'bg-green-500'
                                                                : getCoveragePct(row) >= 80
                                                                  ? 'bg-blue-500'
                                                                  : 'bg-amber-500'
                                                        "
                                                        :style="{ width: `${getCoveragePct(row)}%` }"
                                                    ></div>
                                                </div>
                                                <span class="text-xs font-semibold text-slate-700">
                                                    {{ getCoveragePct(row) }}%
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3">
                                            <div class="flex flex-wrap gap-1">
                                                <span
                                                    v-for="reason in (row.blocker_reasons ?? []).slice(0, 2)"
                                                    :key="reason"
                                                    class="text-[10px] px-2 py-0.5 rounded-full bg-red-100 text-red-700"
                                                >
                                                    {{ mapReadinessLabel(reason, readinessReasonLabels) }}
                                                </span>
                                                <span
                                                    v-if="(row.blocker_reasons ?? []).length > 2"
                                                    class="text-[10px] px-2 py-0.5 rounded-full bg-red-50 text-red-600"
                                                >
                                                    +{{ row.blocker_reasons.length - 2 }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3">
                                            <div class="flex flex-wrap gap-1">
                                                <span
                                                    v-for="flag in (row.warning_flags ?? []).slice(0, 2)"
                                                    :key="flag"
                                                    class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-700"
                                                >
                                                    {{ mapReadinessLabel(flag, readinessWarningLabels) }}
                                                </span>
                                                <span
                                                    v-if="(row.warning_flags ?? []).length > 2"
                                                    class="text-[10px] px-2 py-0.5 rounded-full bg-amber-50 text-amber-600"
                                                >
                                                    +{{ row.warning_flags.length - 2 }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 text-right">
                                            <button
                                                type="button"
                                                :data-testid="`readiness-open-attendance-${row.staff_member_id}`"
                                                @click.stop="openAttendanceWorkspace(row)"
                                                class="text-xs font-semibold text-brand-primary hover:underline flex items-center gap-1 ml-auto"
                                            >
                                                <ExternalLink class="w-3 h-3" />
                                                Attendance
                                            </button>
                                        </td>
                                    </tr>
                                    <!-- Expanded Row -->
                                    <tr v-if="expandedRows[row.staff_member_id]" class="bg-slate-50">
                                        <td colspan="8" class="px-6 py-4">
                                            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 text-xs">
                                                <div class="bg-white rounded-lg border border-slate-200 px-3 py-2">
                                                    <p class="text-slate-500">Scheduled Days</p>
                                                    <p class="text-slate-900 font-bold">
                                                        {{ row.metrics?.scheduled_working_days ?? 0 }}
                                                    </p>
                                                </div>
                                                <div class="bg-white rounded-lg border border-slate-200 px-3 py-2">
                                                    <p class="text-slate-500">Covered Days</p>
                                                    <p class="text-slate-900 font-bold">
                                                        {{ row.metrics?.covered_days ?? 0 }}
                                                    </p>
                                                </div>
                                                <div class="bg-white rounded-lg border border-slate-200 px-3 py-2">
                                                    <p class="text-slate-500">Present Days</p>
                                                    <p class="text-slate-900 font-bold">
                                                        {{ row.metrics?.present_days ?? 0 }}
                                                    </p>
                                                </div>
                                                <div class="bg-white rounded-lg border border-slate-200 px-3 py-2">
                                                    <p class="text-slate-500">Late Days</p>
                                                    <p class="text-slate-900 font-bold">
                                                        {{ row.metrics?.late_days ?? 0 }}
                                                    </p>
                                                </div>
                                                <div class="bg-white rounded-lg border border-slate-200 px-3 py-2">
                                                    <p class="text-slate-500">Half Days</p>
                                                    <p class="text-slate-900 font-bold">
                                                        {{ row.metrics?.half_day_count ?? 0 }}
                                                    </p>
                                                </div>
                                                <div class="bg-white rounded-lg border border-slate-200 px-3 py-2">
                                                    <p class="text-slate-500">Paid Leave</p>
                                                    <p class="text-slate-900 font-bold">
                                                        {{ row.metrics?.paid_leave_days ?? 0 }}
                                                    </p>
                                                </div>
                                                <div class="bg-white rounded-lg border border-slate-200 px-3 py-2">
                                                    <p class="text-slate-500">Absent Days</p>
                                                    <p class="text-slate-900 font-bold">
                                                        {{ row.metrics?.absent_days ?? 0 }}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="sortedEmployees.length === 0">
                                    <td colspan="8" class="py-8 text-center text-slate-500 text-sm">
                                        No employees match the current filters.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar: Blocker Aggregates + Actions -->
            <div class="w-80 flex-shrink-0 space-y-5">
                <!-- Blocker Aggregate Panel -->
                <div
                    v-if="blockedReasonAggregates.length > 0"
                    class="bg-white border border-brand-border rounded-2xl p-5"
                    data-testid="readiness-blocker-panel"
                >
                    <h3 class="text-brand-dark text-sm font-bold mb-3 flex items-center gap-2">
                        <XCircle class="w-4 h-4 text-red-500" />
                        Blocker Reasons
                    </h3>
                    <div class="space-y-2">
                        <button
                            v-for="item in blockedReasonAggregates"
                            :key="item.key"
                            type="button"
                            @click="filterByBlocker(item)"
                            class="w-full text-left flex items-center justify-between px-3 py-2 rounded-lg border border-red-100 hover:border-red-300 hover:bg-red-50 transition-all duration-300"
                            :class="activeBlockerFilter?.key === item.key ? 'bg-red-50 border-red-300' : ''"
                        >
                            <span class="text-xs text-red-700">{{ item.label }}</span>
                            <span class="text-xs font-bold text-red-700 bg-red-100 px-2 py-0.5 rounded-full">
                                {{ item.count }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Warning Aggregate Panel -->
                <div
                    v-if="warningFlagAggregates.length > 0"
                    class="bg-white border border-brand-border rounded-2xl p-5"
                    data-testid="readiness-warning-panel"
                >
                    <h3 class="text-brand-dark text-sm font-bold mb-3 flex items-center gap-2">
                        <AlertTriangle class="w-4 h-4 text-amber-500" />
                        Warning Flags
                    </h3>
                    <div class="space-y-2">
                        <button
                            v-for="item in warningFlagAggregates"
                            :key="item.key"
                            type="button"
                            @click="filterByWarning(item)"
                            class="w-full text-left flex items-center justify-between px-3 py-2 rounded-lg border border-amber-100 hover:border-amber-300 hover:bg-amber-50 transition-all duration-300"
                            :class="activeWarningFilter?.key === item.key ? 'bg-amber-50 border-amber-300' : ''"
                        >
                            <span class="text-xs text-amber-700">{{ item.label }}</span>
                            <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">
                                {{ item.count }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="bg-white border border-brand-border rounded-2xl p-5 space-y-3">
                    <h3 class="text-brand-dark text-sm font-bold mb-3 flex items-center gap-2">
                        <CheckCircle class="w-4 h-4 text-green-500" />
                        Actions
                    </h3>
                    <button
                        type="button"
                        data-testid="readiness-go-generate"
                        @click="goToGeneratePayroll"
                        class="w-full rounded-xl border border-primary-700 hover:brightness-110 focus:ring-2 focus:ring-brand-primary transition-all duration-300 blue-gradient blue-btn-shadow px-4 py-3 flex items-center justify-center gap-2"
                    >
                        <span class="text-brand-white text-sm font-semibold">Go to Generate Payroll</span>
                        <ArrowRight class="w-4 h-4 text-white" />
                    </button>
                    <button
                        type="button"
                        data-testid="readiness-export-report-btn"
                        @click="exportCsv"
                        class="w-full border border-brand-border rounded-xl hover:border-brand-primary hover:text-brand-primary transition-all duration-300 px-4 py-3 flex items-center justify-center gap-2 text-sm font-semibold text-slate-700"
                    >
                        <Download class="w-4 h-4" />
                        Export Readiness Report
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div
            v-if="loading && !readinessDashboard"
            class="bg-white border border-brand-border rounded-2xl p-12 text-center"
        >
            <RefreshCw class="w-8 h-8 text-slate-400 animate-spin mx-auto mb-3" />
            <p class="text-slate-600 text-sm">Loading readiness data for {{ formatMonth(salaryMonth) }}...</p>
        </div>
    </div>
</template>
