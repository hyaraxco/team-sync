<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { usePerformanceReviewStore } from "@/stores/performanceReview";
import {
    ArrowLeft,
    Calendar,
    Users,
    TrendingUp,
    Award,
    AlertCircle,
    AlertTriangle,
    CheckCircle2,
    RefreshCw,
    ChevronDown,
    ChevronUp,
    Info,
} from "lucide-vue-next";
import MainCard from "@/components/common/MainCard.vue";
import StatusBadge from "@/components/common/StatusBadge.vue";
import GeneratedReviewsList from "@/components/admin/performance/GeneratedReviewsList.vue";

const route = useRoute();
const router = useRouter();
const cycleId = Number(route.params.id);

const reviewStore = usePerformanceReviewStore();
const { currentCycle, cyclesLoading, topsisResult, topsisLoading } = storeToRefs(reviewStore);

// ── Weight configuration ──────────────────────────────────────────────────────
const weights = ref({
    performance_score: 0.30,
    attendance_rate: 0.20,
    goal_completion: 0.25,
    feedback_score: 0.15,
    tenure_factor: 0.10,
});

const weightLabels = {
    performance_score: "Performance Score",
    attendance_rate: "Attendance Rate",
    goal_completion: "Goal Completion",
    feedback_score: "Feedback Score",
    tenure_factor: "Tenure Factor",
};

const totalWeight = computed(() => Object.values(weights.value).reduce((s, v) => s + Number(v), 0));
const weightValid = computed(() => Math.abs(totalWeight.value - 1.0) < 0.001);

// ── UI state ─────────────────────────────────────────────────────────────────
const expandedRows = ref(new Set());
const showWeightPanel = ref(false);
const topsisError = ref(null);

// ── Cycle display helpers ─────────────────────────────────────────────────────
const cycleStatusConfig = {
    draft: { label: "Draft", color: "secondary" },
    active: { label: "Active", color: "success" },
    completed: { label: "Completed", color: "info" },
    cancelled: { label: "Cancelled", color: "danger" },
};

const cycleTypeLabel = {
    quarterly: "Quarterly",
    semi_annual: "Semi-Annual",
    annual: "Annual",
    probation: "Probation Review",
};

const cycleProgress = computed(() => {
    if (!currentCycle.value) return 0;
    const start = new Date(currentCycle.value.start_date).getTime();
    const end = new Date(currentCycle.value.end_date).getTime();
    const now = Date.now();
    return Math.min(100, Math.max(0, Math.round(((now - start) / (end - start)) * 100)));
});

// ── TOPSIS helpers ────────────────────────────────────────────────────────────
const rankingList = computed(() => topsisResult.value?.ranking ?? []);

const rankMedalColor = (rank) => {
    if (rank === 1) return "text-yellow-500";
    if (rank === 2) return "text-gray-400";
    if (rank === 3) return "text-amber-600";
    return "text-brand-light";
};

const labelColor = (label) => {
    const map = {
        Outstanding: "bg-emerald-100 text-emerald-700 border-emerald-200",
        "Exceeds Expectations": "bg-blue-100   text-blue-700   border-blue-200",
        "Meets Expectations": "bg-yellow-100 text-yellow-700 border-yellow-200",
        "Needs Improvement": "bg-orange-100 text-orange-700 border-orange-200",
        Unsatisfactory: "bg-red-100    text-red-700    border-red-200",
    };
    return map[label] ?? "bg-gray-100 text-gray-700 border-gray-200";
};

const scoreBarWidth = (score) => `${Math.round(score * 100)}%`;

/** Check if an employee's TOPSIS data is incomplete (goals/feedback = 0) */
const getIncompleteWarnings = (item) => {
    const warnings = [];
    if (item.raw_scores.avg_goal_completion === 0 && item.raw_scores.goal_completion_ratio === 0) {
        warnings.push("Goals data unavailable");
    }
    if (item.raw_scores.positive_feedback_count === 0) {
        warnings.push("No feedback received");
    }
    if (item.raw_scores.attendance_quality === 0) {
        warnings.push("Attendance data unavailable");
    }
    if (item.raw_scores.task_completion_quality === 0) {
        warnings.push("Task completion data unavailable");
    }
    return warnings;
};

const toggleRow = (employeeId) => {
    if (expandedRows.value.has(employeeId)) {
        expandedRows.value.delete(employeeId);
    } else {
        expandedRows.value.add(employeeId);
    }
};

// ── Actions ───────────────────────────────────────────────────────────────────
const calculateRanking = async () => {
    topsisError.value = null;
    try {
        await reviewStore.fetchTopsisRanking(cycleId, weights.value);
    } catch (e) {
        topsisError.value = e?.response?.data?.message ?? "Failed to calculate ranking. Ensure the cycle is completed.";
    }
};

const resetWeights = () => {
    weights.value = {
        avg_manager_rating: 0.3,
        final_rating: 0.3,
        avg_goal_completion: 0.2,
        goal_completion_ratio: 0.05,
        positive_feedback_count: 0.05,
        attendance_quality: 0.05,
        task_completion_quality: 0.05,
    };
};

const goBack = () => router.push({ name: "admin.performance.cycles" });

const fetchCycleData = async () => {
    await reviewStore.fetchCycleById(cycleId);
};

// ── Init ──────────────────────────────────────────────────────────────────────
onMounted(async () => {
    await reviewStore.fetchCycleById(cycleId);
    // Auto-load ranking jika cycle sudah completed
    if (currentCycle.value?.status === "completed") {
        await calculateRanking();
    }
});
</script>

<template>
    <div class="space-y-6">
        <!-- Back button + Header -->
        <div class="flex items-center gap-4">
            <button class="p-2 hover:bg-gray-100 rounded-lg transition-colors" @click="goBack">
                <ArrowLeft class="w-5 h-5 text-brand-dark" />
            </button>
            <div class="flex-1">
                <div v-if="cyclesLoading" class="h-8 w-64 bg-gray-200 animate-pulse rounded" />
                <template v-else-if="currentCycle">
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-3xl font-bold text-brand-dark">
                            {{ currentCycle.name }}
                        </h1>
                        <StatusBadge
                            :value="currentCycle.status"
                            :label="cycleStatusConfig[currentCycle.status]?.label"
                        />
                        <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm font-medium">
                            {{ cycleTypeLabel[currentCycle.cycle_type] }}
                        </span>
                    </div>
                    <p class="text-brand-light mt-1 text-sm">
                        Review period:
                        {{
                            new Date(currentCycle.review_period_start).toLocaleDateString("id-ID", {
                                day: "numeric",
                                month: "long",
                                year: "numeric",
                            })
                        }}
                        –
                        {{
                            new Date(currentCycle.review_period_end).toLocaleDateString("id-ID", {
                                day: "numeric",
                                month: "long",
                                year: "numeric",
                            })
                        }}
                    </p>
                </template>
            </div>
        </div>

        <!-- ── Cycle Info Cards ─────────────────────────────────────────────────── -->
        <div v-if="currentCycle && !cyclesLoading" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Cycle Duration -->
            <MainCard>
                <div class="flex items-center gap-3 mb-3">
                    <Calendar class="w-5 h-5 text-brand-primary" />
                    <p class="text-sm font-semibold text-brand-dark">Cycle Duration</p>
                </div>
                <p class="text-sm text-brand-light">
                    {{ new Date(currentCycle.start_date).toLocaleDateString("id-ID") }}
                    —
                    {{ new Date(currentCycle.end_date).toLocaleDateString("id-ID") }}
                </p>
                <!-- Progress bar (hanya jika active) -->
                <div v-if="currentCycle.status === 'active'" class="mt-3">
                    <div class="flex justify-between text-xs text-brand-light mb-1">
                        <span>Progress</span>
                        <span>{{ cycleProgress }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                        <div
                            class="bg-brand-primary h-full rounded-full transition-all duration-500"
                            :style="{ width: cycleProgress + '%' }"
                        />
                    </div>
                </div>
            </MainCard>

            <!-- Self-Assessment Deadline -->
            <MainCard>
                <div class="flex items-center gap-3 mb-3">
                    <Users class="w-5 h-5 text-orange-500" />
                    <p class="text-sm font-semibold text-brand-dark">Self-Assessment Deadline</p>
                </div>
                <p class="text-sm text-brand-light">
                    {{
                        currentCycle.self_assessment_deadline
                            ? new Date(currentCycle.self_assessment_deadline).toLocaleDateString("id-ID", {
                                  day: "numeric",
                                  month: "long",
                                  year: "numeric",
                              })
                            : "Not set"
                    }}
                </p>
            </MainCard>

            <!-- Manager Assessment Deadline -->
            <MainCard>
                <div class="flex items-center gap-3 mb-3">
                    <TrendingUp class="w-5 h-5 text-blue-500" />
                    <p class="text-sm font-semibold text-brand-dark">Manager Assessment Deadline</p>
                </div>
                <p class="text-sm text-brand-light">
                    {{
                        currentCycle.manager_assessment_deadline
                            ? new Date(currentCycle.manager_assessment_deadline).toLocaleDateString("id-ID", {
                                  day: "numeric",
                                  month: "long",
                                  year: "numeric",
                              })
                            : "Not set"
                    }}
                </p>
            </MainCard>
        </div>

        <!-- ── Generated Reviews List ───────────────────────────────────────────── -->
        <template v-if="currentCycle">
            <GeneratedReviewsList :cycle="currentCycle" @refresh="fetchCycleData" />
        </template>

        <!-- ── TOPSIS Section (hanya jika completed) ─────────────────────────── -->
        <template v-if="currentCycle">
            <!-- Badge info jika belum completed -->
            <div
                v-if="currentCycle.status !== 'completed'"
                class="flex items-center gap-3 p-4 bg-yellow-50 border border-yellow-200 rounded-xl"
            >
                <AlertCircle class="w-5 h-5 text-yellow-600 flex-shrink-0" />
                <p class="text-sm text-yellow-800">
                    TOPSIS Ranking is only available after the cycle is
                    <strong>Completed</strong>
                    and all reviews are calibrated. Current status:
                    <strong>{{ cycleStatusConfig[currentCycle.status]?.label }}</strong>
                </p>
            </div>

            <!-- TOPSIS Panel (visible jika completed) -->
            <div v-else class="space-y-4">
                <!-- Header section TOPSIS -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 bg-gradient-to-br from-brand-primary to-blue-600 rounded-xl flex items-center justify-center"
                        >
                            <Award class="w-5 h-5 text-white" />
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-brand-dark">TOPSIS Performance Ranking</h2>
                            <p class="text-sm text-brand-light">
                                Employee rankings based on 5 comprehensive performance criteria
                            </p>
                        </div>
                    </div>
                    <button
                        class="flex items-center gap-2 px-4 py-2 bg-brand-primary text-white rounded-lg hover:bg-brand-primary-dark transition-colors disabled:opacity-50"
                        :disabled="topsisLoading || !weightValid"
                        @click="calculateRanking"
                    >
                        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': topsisLoading }" />
                        {{ topsisLoading ? "Calculating..." : "Recalculate" }}
                    </button>
                </div>

                <!-- Weight Configuration Panel -->
                <MainCard>
                    <button
                        class="w-full flex items-center justify-between"
                        @click="showWeightPanel = !showWeightPanel"
                    >
                        <div class="flex items-center gap-2">
                            <Info class="w-4 h-4 text-brand-primary" />
                            <span class="text-sm font-semibold text-brand-dark">Criteria Weights Configuration</span>
                            <span
                                class="text-xs px-2 py-0.5 rounded-full font-medium"
                                :class="weightValid ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                            >
                                Total: {{ (totalWeight * 100).toFixed(0) }}%
                            </span>
                        </div>
                        <component :is="showWeightPanel ? ChevronUp : ChevronDown" class="w-4 h-4 text-brand-light" />
                    </button>

                    <div v-if="showWeightPanel" class="mt-4 space-y-3">
                        <div v-for="(label, key) in weightLabels" :key="key" class="flex items-center gap-4">
                            <label class="w-48 text-sm text-brand-dark shrink-0">{{ label }}</label>
                            <input
                                v-model.number="weights[key]"
                                type="range"
                                min="0"
                                max="1"
                                step="0.05"
                                class="flex-1 accent-brand-primary"
                            />
                            <span class="w-12 text-sm font-semibold text-brand-dark text-right">
                                {{ (weights[key] * 100).toFixed(0) }}%
                            </span>
                        </div>
                        <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                            <p v-if="!weightValid" class="text-xs text-red-600 flex-1">
                                Total weight must be 100%. Currently: {{ (totalWeight * 100).toFixed(0) }}%
                            </p>
                            <p v-else class="text-xs text-green-600 flex-1">✓ Weights valid</p>
                            <button class="text-xs text-brand-primary hover:underline" @click="resetWeights">
                                Reset to default
                            </button>
                        </div>
                    </div>
                </MainCard>

                <!-- Error state -->
                <div v-if="topsisError" class="flex items-center gap-3 p-4 bg-red-50 border border-red-200 rounded-xl">
                    <AlertCircle class="w-5 h-5 text-red-600 flex-shrink-0" />
                    <p class="text-sm text-red-800">{{ topsisError }}</p>
                </div>

                <!-- Loading skeleton -->
                <div v-if="topsisLoading" class="space-y-3">
                    <div v-for="i in 4" :key="i" class="h-16 bg-gray-100 animate-pulse rounded-xl" />
                </div>

                <!-- Ranking Table -->
                <MainCard v-else-if="rankingList.length > 0">
                    <!-- Legend criteria -->
                    <div class="grid grid-cols-7 gap-2 mb-4 p-3 bg-gray-50 rounded-lg">
                        <div v-for="(label, key) in weightLabels" :key="key" class="text-center">
                            <p class="text-xs font-semibold text-brand-dark">{{ label }}</p>
                            <p class="text-xs text-brand-light">
                                weight {{ (topsisResult.weights[key] * 100).toFixed(0) }}%
                            </p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th
                                        class="text-left py-3 px-3 text-xs font-semibold text-brand-light uppercase tracking-wide w-12"
                                    >
                                        Rank
                                    </th>
                                    <th
                                        class="text-left py-3 px-3 text-xs font-semibold text-brand-light uppercase tracking-wide"
                                    >
                                        Employee
                                    </th>
                                    <th
                                        class="text-center py-3 px-2 text-xs font-semibold text-brand-light uppercase tracking-wide"
                                    >
                                        C1
                                    </th>
                                    <th
                                        class="text-center py-3 px-2 text-xs font-semibold text-brand-light uppercase tracking-wide"
                                    >
                                        C2
                                    </th>
                                    <th
                                        class="text-center py-3 px-2 text-xs font-semibold text-brand-light uppercase tracking-wide"
                                    >
                                        C3
                                    </th>
                                    <th
                                        class="text-center py-3 px-2 text-xs font-semibold text-brand-light uppercase tracking-wide"
                                    >
                                        C4
                                    </th>
                                    <th
                                        class="text-center py-3 px-2 text-xs font-semibold text-brand-light uppercase tracking-wide"
                                    >
                                        C5
                                    </th>
                                    <th
                                        class="text-center py-3 px-2 text-xs font-semibold text-brand-light uppercase tracking-wide"
                                    >
                                        C6
                                    </th>
                                    <th
                                        class="text-center py-3 px-2 text-xs font-semibold text-brand-light uppercase tracking-wide"
                                    >
                                        C7
                                    </th>
                                    <th
                                        class="text-center py-3 px-3 text-xs font-semibold text-brand-light uppercase tracking-wide w-32"
                                    >
                                        Score (Ci)
                                    </th>
                                    <th
                                        class="text-center py-3 px-3 text-xs font-semibold text-brand-light uppercase tracking-wide"
                                    >
                                        Label
                                    </th>
                                    <th class="w-8"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="item in rankingList" :key="item.staff_member_id">
                                    <!-- Main row -->
                                    <tr
                                        class="border-b border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors"
                                        @click="toggleRow(item.staff_member_id)"
                                    >
                                        <!-- Rank -->
                                        <td class="py-4 px-3">
                                            <span class="text-2xl font-bold" :class="rankMedalColor(item.rank)">
                                                {{
                                                    item.rank <= 3 ? ["🥇", "🥈", "🥉"][item.rank - 1] : "#" + item.rank
                                                }}
                                            </span>
                                        </td>
                                        <!-- Name -->
                                        <td class="py-4 px-3">
                                            <p class="font-semibold text-brand-dark">{{ item.employee_name }}</p>
                                            <p v-if="item.department" class="text-xs text-brand-light">
                                                {{ item.department }}
                                            </p>
                                            <!-- Incomplete Data Badge -->
                                            <div
                                                v-if="getIncompleteWarnings(item).length > 0"
                                                class="flex items-center gap-1 mt-1"
                                            >
                                                <AlertTriangle class="w-3 h-3 text-amber-500 flex-shrink-0" />
                                                <span class="text-[10px] text-amber-600 leading-tight">
                                                    {{ getIncompleteWarnings(item).join(" · ") }}
                                                </span>
                                            </div>
                                        </td>
                                        <!-- C1-C5 raw scores -->
                                        <td class="py-4 px-2 text-center text-sm text-brand-dark">
                                            {{ item.raw_scores.avg_manager_rating.toFixed(2) }}
                                        </td>
                                        <td class="py-4 px-2 text-center text-sm text-brand-dark">
                                            {{ item.raw_scores.final_rating.toFixed(2) }}
                                        </td>
                                        <td class="py-4 px-2 text-center text-sm text-brand-dark">
                                            {{ item.raw_scores.avg_goal_completion.toFixed(1) }}%
                                        </td>
                                        <td class="py-4 px-2 text-center text-sm text-brand-dark">
                                            {{ (item.raw_scores.goal_completion_ratio * 100).toFixed(0) }}%
                                        </td>
                                        <td class="py-4 px-2 text-center text-sm text-brand-dark">
                                            {{ item.raw_scores.positive_feedback_count }}
                                        </td>
                                        <td class="py-4 px-2 text-center text-sm text-brand-dark">
                                            {{ item.raw_scores.attendance_quality.toFixed(1) }}%
                                        </td>
                                        <td class="py-4 px-2 text-center text-sm text-brand-dark">
                                            {{ item.raw_scores.task_completion_quality.toFixed(1) }}%
                                        </td>
                                        <!-- Closeness coefficient + bar -->
                                        <td class="py-4 px-3">
                                            <div class="flex flex-col gap-1">
                                                <span class="text-center text-sm font-bold text-brand-dark">
                                                    {{ (item.closeness_coefficient * 100).toFixed(2) }}%
                                                </span>
                                                <div class="w-full bg-gray-200 rounded-full h-1.5">
                                                    <div
                                                        class="bg-brand-primary h-full rounded-full transition-all"
                                                        :style="{ width: scoreBarWidth(item.closeness_coefficient) }"
                                                    />
                                                </div>
                                            </div>
                                        </td>
                                        <!-- Label -->
                                        <td class="py-4 px-3 text-center">
                                            <span
                                                class="px-2 py-1 text-xs font-semibold rounded-full border"
                                                :class="labelColor(item.label)"
                                            >
                                                {{ item.label }}
                                            </span>
                                        </td>
                                        <!-- Expand icon -->
                                        <td class="py-4 px-2 text-center">
                                            <component
                                                :is="expandedRows.has(item.staff_member_id) ? ChevronUp : ChevronDown"
                                                class="w-4 h-4 text-brand-light"
                                            />
                                        </td>
                                    </tr>

                                    <!-- Detail row (expandable) -->
                                    <tr v-if="expandedRows.has(item.staff_member_id)" class="bg-blue-50/40">
                                        <td colspan="12" class="px-6 py-4">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                                <!-- Normalisasi steps -->
                                                <div>
                                                    <p class="font-semibold text-brand-dark mb-2">
                                                        📐 TOPSIS Calculation Detail
                                                    </p>
                                                    <table class="w-full text-xs">
                                                        <thead>
                                                            <tr class="text-brand-light">
                                                                <th class="text-left pb-1">Criteria</th>
                                                                <th class="text-right pb-1">Raw</th>
                                                                <th class="text-right pb-1">Normalized</th>
                                                                <th class="text-right pb-1">Weighted</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr
                                                                v-for="(label, key) in weightLabels"
                                                                :key="key"
                                                                class="border-t border-blue-100"
                                                            >
                                                                <td class="py-1 text-brand-light">{{ label }}</td>
                                                                <td class="py-1 text-right font-mono text-brand-dark">
                                                                    {{
                                                                        typeof item.raw_scores[key] === "number"
                                                                            ? item.raw_scores[key].toFixed(4)
                                                                            : item.raw_scores[key]
                                                                    }}
                                                                </td>
                                                                <td class="py-1 text-right font-mono text-blue-700">
                                                                    {{ item.normalized_scores[key]?.toFixed(6) }}
                                                                </td>
                                                                <td class="py-1 text-right font-mono text-emerald-700">
                                                                    {{ item.weighted_scores[key]?.toFixed(6) }}
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>

                                                <!-- Jarak & Skor akhir -->
                                                <div>
                                                    <p class="font-semibold text-brand-dark mb-2">
                                                        📏 Distance & Closeness Coefficient
                                                    </p>
                                                    <div class="space-y-2 text-xs">
                                                        <div
                                                            class="flex justify-between p-2 bg-white rounded border border-blue-100"
                                                        >
                                                            <span class="text-brand-light">
                                                                D⁺ (distance to positive ideal)
                                                            </span>
                                                            <span class="font-mono text-red-600">
                                                                {{ item.distance_positive.toFixed(6) }}
                                                            </span>
                                                        </div>
                                                        <div
                                                            class="flex justify-between p-2 bg-white rounded border border-blue-100"
                                                        >
                                                            <span class="text-brand-light">
                                                                D⁻ (distance to negative ideal)
                                                            </span>
                                                            <span class="font-mono text-green-600">
                                                                {{ item.distance_negative.toFixed(6) }}
                                                            </span>
                                                        </div>
                                                        <div
                                                            class="flex justify-between p-2 bg-brand-primary/10 rounded border border-brand-primary/20"
                                                        >
                                                            <span class="font-semibold text-brand-dark">
                                                                Ci = D⁻ / (D⁺ + D⁻)
                                                            </span>
                                                            <span class="font-mono font-bold text-brand-primary">
                                                                {{ item.closeness_coefficient.toFixed(6) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Ideal Solutions Footer -->
                    <div v-if="topsisResult" class="mt-4 pt-4 border-t border-gray-100">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-3 bg-green-50 rounded-lg border border-green-100">
                                <p class="text-xs font-semibold text-green-800 mb-2">✅ Positive Ideal Solution (A⁺)</p>
                                <div class="space-y-1">
                                    <div
                                        v-for="(label, key) in weightLabels"
                                        :key="key"
                                        class="flex justify-between text-xs text-green-700"
                                    >
                                        <span>{{ label }}</span>
                                        <span class="font-mono">
                                            {{ topsisResult.ideal_positive[key]?.toFixed(6) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="p-3 bg-red-50 rounded-lg border border-red-100">
                                <p class="text-xs font-semibold text-red-800 mb-2">❌ Negative Ideal Solution (A⁻)</p>
                                <div class="space-y-1">
                                    <div
                                        v-for="(label, key) in weightLabels"
                                        :key="key"
                                        class="flex justify-between text-xs text-red-700"
                                    >
                                        <span>{{ label }}</span>
                                        <span class="font-mono">
                                            {{ topsisResult.ideal_negative[key]?.toFixed(6) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </MainCard>

                <!-- Empty state jika tidak ada data -->
                <MainCard v-else-if="!topsisLoading && !topsisError">
                    <div class="text-center py-8">
                        <CheckCircle2 class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                        <p class="text-brand-dark font-semibold">No ranking data available</p>
                        <p class="text-brand-light text-sm mt-1">
                            Click "Recalculate" to run the TOPSIS algorithm.
                            <br />
                            Make sure there are reviews with
                            <strong>completed</strong>
                            status in this cycle.
                        </p>
                    </div>
                </MainCard>
            </div>
        </template>
    </div>
</template>
