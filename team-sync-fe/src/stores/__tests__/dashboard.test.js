import { setActivePinia, createPinia } from "pinia";
import { describe, it, expect, beforeEach, vi } from "vitest";
import { useDashboardStore } from "@/stores/dashboard";
import { axiosInstance } from "@/plugins/axios";

vi.mock("@/plugins/axios", () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

describe("Dashboard Store", () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useDashboardStore();
        vi.clearAllMocks();
    });

    it("fetchMyStatistics populates myStatistics and returns payload", async () => {
        const payload = { tasks_completed: 8, attendance_rate: 96 };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payload } });

        const result = await store.fetchMyStatistics();

        expect(axiosInstance.get).toHaveBeenCalledWith("/dashboard/my-statistics");
        expect(result).toEqual(payload);
        expect(store.myStatistics).toEqual(payload);
        expect(store.myStatisticsLoading).toBe(false);
        expect(store.error).toBe(null);
    });

    it("fetchMyStatistics sets error and rethrows on failure", async () => {
        const mockError = {
            response: {
                status: 400,
                data: { message: "Invalid request" },
            },
        };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await expect(store.fetchMyStatistics()).rejects.toEqual(mockError);

        expect(store.error).toBe("Invalid request");
        expect(store.myStatisticsLoading).toBe(false);
    });

    it("fetchStatistics populates statistics state", async () => {
        const payload = {
            employees: { total: 100, added_this_month: 5 },
            teams: { total: 10, new_teams: 1 },
            attendance: { rate: 92, change: 2 },
            tasks: { completed: 45, change: 3 },
            projects: { active: 12, new_projects: 2 },
            performance: { promotion_eligible: 7, pip_required: 1 },
        };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payload } });

        await store.fetchStatistics();

        expect(axiosInstance.get).toHaveBeenCalledWith("/dashboard/statistics");
        expect(store.statistics).toEqual(payload);
        expect(store.loading).toBe(false);
        expect(store.error).toBe(null);
    });

    it("fetchStatistics sets error on failure", async () => {
        const mockError = {
            response: {
                status: 500,
                data: { message: "Server exploded" },
            },
        };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await store.fetchStatistics();

        expect(store.error).toBe("Server exploded");
        expect(store.loading).toBe(false);
    });

    it("fetchTodayAttendance populates todayAttendance state", async () => {
        const payload = { present: 76, absent: 4, late: 6 };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payload } });

        await store.fetchTodayAttendance();

        expect(axiosInstance.get).toHaveBeenCalledWith("/dashboard/today-attendance-overview");
        expect(store.todayAttendance).toEqual(payload);
        expect(store.todayAttendanceLoading).toBe(false);
    });

    it("fetchTodayAttendance sets error on failure", async () => {
        const mockError = {
            response: {
                status: 404,
                data: { message: "Overview not found" },
            },
        };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await store.fetchTodayAttendance();

        expect(store.error).toBe("Overview not found");
        expect(store.todayAttendanceLoading).toBe(false);
    });

    it("fetchTeamPulse populates teamPulse state", async () => {
        const payload = {
            summary: { red: 1, yellow: 1, green: 1, total: 3 },
            staff_members: [],
        };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payload } });

        const result = await store.fetchTeamPulse();

        expect(axiosInstance.get).toHaveBeenCalledWith("/dashboard/team-pulse");
        expect(result).toEqual(payload);
        expect(store.teamPulse).toEqual(payload);
        expect(store.teamPulseLoading).toBe(false);
    });

    it("sendTeamPulseNudge updates matching member nudge metadata", async () => {
        store.teamPulse = {
            staff_members: [
                {
                    id: 7,
                    nudge: {
                        status: "idle",
                        last_sent_at: null,
                    },
                },
            ],
        };

        axiosInstance.post.mockResolvedValueOnce({
            data: {
                data: {
                    staff_member_id: 7,
                    sent_at: "2026-05-03T10:00:00Z",
                },
            },
        });

        await store.sendTeamPulseNudge(7, "Ping");

        expect(axiosInstance.post).toHaveBeenCalledWith("/dashboard/team-pulse/7/nudge", {
            message: "Ping",
        });
        expect(store.teamPulse.staff_members[0].nudge.status).toBe("sent");
        expect(store.teamPulse.staff_members[0].nudge.last_sent_at).toBe("2026-05-03T10:00:00Z");
        expect(store.teamPulseNudgingIds).toEqual([]);
    });
});
