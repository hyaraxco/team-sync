import { beforeEach, expect, it, vi } from "vitest";
import { createPinia, setActivePinia } from "pinia";
import { useAnalyticsStore } from "@/stores/analytics";
import { useAuthStore } from "@/stores/auth";
import { axiosInstance } from "@/plugins/axios";

vi.mock("@/plugins/axios", () => ({ axiosInstance: { get: vi.fn() } }));

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    axiosInstance.get.mockImplementation(async (url) => ({
        data: { data: url === "teams" ? [{ id: 17, name: "Led team" }] : { total: 3 } },
    }));
});

it("loads the Manager's led team without requesting company ratings", async () => {
    useAuthStore().user = { id: 7, permissions: ["analytics-performance-view"] };
    const store = useAnalyticsStore();
    store.companyPerformanceSummary = { average_rating: 5 };
    store.ratingDistribution = { average: 5 };

    await store.fetchPerformanceAnalytics();

    expect(axiosInstance.get).toHaveBeenCalledWith("teams", { params: { leader_id: 7 } });
    expect(axiosInstance.get.mock.calls.slice(1)).toEqual([
        ["/analytics/performance/team-summary", { params: { period: "6m", team_id: 17 } }],
        ["/analytics/performance/goal-completion-rate", { params: { period: "6m", team_id: 17 } }],
        ["/analytics/performance/feedback-metrics", { params: { period: "6m", team_id: 17 } }],
    ]);
    expect(store.teamPerformanceSummary).toEqual({ total: 3 });
    expect(store.companyPerformanceSummary).toBeNull();
    expect(store.ratingDistribution).toBeNull();
    expect(store.error).toBeNull();
});

it("retains HR company metrics", async () => {
    useAuthStore().user = { id: 8, permissions: ["analytics-performance-view", "analytics-hr-view"] };
    const store = useAnalyticsStore();

    await store.fetchPerformanceAnalytics();

    expect(store.companyPerformanceSummary).toEqual({ total: 3 });
    expect(store.ratingDistribution).toEqual({ total: 3 });
    expect(store.goalCompletionRate).toEqual({ total: 3 });
    expect(store.error).toBeNull();
});

it("does not request unfiltered analytics when a Manager has no led team", async () => {
    useAuthStore().user = { id: 7, permissions: ["analytics-performance-view"] };
    axiosInstance.get.mockResolvedValue({ data: { data: [] } });
    const store = useAnalyticsStore();

    await store.fetchPerformanceAnalytics();

    expect(axiosInstance.get.mock.calls).toEqual([["teams", { params: { leader_id: 7 } }]]);
    expect(store.teamPerformanceSummary).toBeNull();
    expect(store.error).toBe("No led team is available for performance analytics.");
    expect(store.enhancedMetricsLoading).toBe(false);
});
