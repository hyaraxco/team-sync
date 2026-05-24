import { setActivePinia, createPinia } from "pinia";
import { describe, it, expect, beforeEach, vi } from "vitest";
import { useHybridScheduleStore } from "@/stores/hybridSchedule";
import { axiosInstance } from "@/plugins/axios";

vi.mock("@/plugins/axios", () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

describe("Hybrid Schedule Store", () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useHybridScheduleStore();
        vi.clearAllMocks();
    });

    it("fetchAllPaginated populates paginatedSchedules and meta", async () => {
        const paginator = {
            data: [{ id: 1, team_id: 2 }],
            current_page: 3,
            last_page: 8,
            per_page: 20,
            total: 150,
        };
        const params = { page: 3, search: "eng", row_per_page: 20 };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: paginator, message: "ok" } });

        const result = await store.fetchAllPaginated(params);

        expect(axiosInstance.get).toHaveBeenCalledWith("hybrid-schedules", {
            params: {
                page: 3,
                search: "eng",
                per_page: 20,
            },
        });
        expect(result).toEqual({ data: paginator, message: "ok" });
        expect(store.paginatedSchedules).toEqual(paginator.data);
        expect(store.meta).toEqual({
            current_page: 3,
            last_page: 8,
            per_page: 20,
            total: 150,
        });
        expect(store.loading).toBe(false);
        expect(store.error).toBe(null);
    });

    it("fetchAllPaginated sets error and rethrows on failure", async () => {
        const mockError = {
            response: {
                status: 500,
                data: { message: "Failed to fetch schedules" },
            },
        };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await expect(store.fetchAllPaginated()).rejects.toEqual(mockError);

        expect(store.error).toBe("Failed to fetch schedules");
        expect(store.loading).toBe(false);
    });

    it("fetchMySchedule stores schedule and returns response", async () => {
        const payload = { id: 7, days: ["monday", "wednesday"] };
        const responseData = { data: payload, message: "ok" };
        axiosInstance.get.mockResolvedValueOnce({ data: responseData });

        const result = await store.fetchMySchedule();

        expect(axiosInstance.get).toHaveBeenCalledWith("my-hybrid-schedule");
        expect(result).toEqual(responseData);
        expect(store.mySchedule).toEqual(payload);
        expect(store.loading).toBe(false);
    });

    it("fetchMySchedule sets error and rethrows on failure", async () => {
        const mockError = {
            response: {
                status: 404,
                data: { message: "Schedule not found" },
            },
        };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await expect(store.fetchMySchedule()).rejects.toEqual(mockError);
        expect(store.error).toBe("Schedule not found");
        expect(store.loading).toBe(false);
    });

    it("fetchMyOverrides populates myOverrides and returns payload", async () => {
        const overrides = [{ id: 1, date: "2026-05-01" }];
        axiosInstance.get.mockResolvedValueOnce({ data: { data: overrides } });

        const result = await store.fetchMyOverrides();

        expect(axiosInstance.get).toHaveBeenCalledWith("my-hybrid-overrides");
        expect(result).toEqual(overrides);
        expect(store.myOverrides).toEqual(overrides);
        expect(store.loading).toBe(false);
    });

    it("fetchMyOverrides sets error and does not throw on failure", async () => {
        const mockError = {
            response: {
                status: 403,
                data: { message: "Forbidden overrides access" },
            },
        };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await expect(store.fetchMyOverrides()).resolves.toBeUndefined();

        expect(store.error).toBe("Forbidden overrides access");
        expect(store.loading).toBe(false);
    });

    it("createOverride posts payload, sets success, and returns data", async () => {
        const payload = { date: "2026-05-03", type: "wfo" };
        const created = { id: 40, ...payload };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: "Override created",
                data: created,
            },
        });

        const result = await store.createOverride(payload);

        expect(axiosInstance.post).toHaveBeenCalledWith("hybrid-schedule-overrides", payload);
        expect(result).toEqual(created);
        expect(store.success).toBe("Override created");
        expect(store.loading).toBe(false);
    });

    it("createOverride sets error and rethrows on failure", async () => {
        const mockError = {
            response: {
                status: 422,
                data: { errors: { date: ["Date is invalid"] } },
            },
        };
        axiosInstance.post.mockRejectedValueOnce(mockError);

        await expect(store.createOverride({})).rejects.toEqual(mockError);
        expect(store.error).toEqual({ date: ["Date is invalid"] });
        expect(store.loading).toBe(false);
    });

    it("approveOverride posts approve endpoint, sets success, and returns data", async () => {
        const approved = { id: 9, status: "approved" };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: "Override approved",
                data: approved,
            },
        });

        const result = await store.approveOverride(9);

        expect(axiosInstance.post).toHaveBeenCalledWith("hybrid-schedule-overrides/9/approve");
        expect(result).toEqual(approved);
        expect(store.success).toBe("Override approved");
        expect(store.loading).toBe(false);
    });

    it("approveOverride sets error and rethrows on failure", async () => {
        const mockError = {
            response: {
                status: 400,
                data: { message: "Cannot approve override" },
            },
        };
        axiosInstance.post.mockRejectedValueOnce(mockError);

        await expect(store.approveOverride(1)).rejects.toEqual(mockError);
        expect(store.error).toBe("Cannot approve override");
        expect(store.loading).toBe(false);
    });

    it("rejectOverride posts review notes, sets success, and returns data", async () => {
        const rejected = { id: 6, status: "rejected" };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: "Override rejected",
                data: rejected,
            },
        });

        const result = await store.rejectOverride(6, "Insufficient reason");

        expect(axiosInstance.post).toHaveBeenCalledWith("hybrid-schedule-overrides/6/reject", {
            review_notes: "Insufficient reason",
        });
        expect(result).toEqual(rejected);
        expect(store.success).toBe("Override rejected");
        expect(store.loading).toBe(false);
    });

    it("rejectOverride sets error and rethrows on failure", async () => {
        const mockError = {
            response: {
                status: 404,
                data: { message: "Override not found" },
            },
        };
        axiosInstance.post.mockRejectedValueOnce(mockError);

        await expect(store.rejectOverride(999, "note")).rejects.toEqual(mockError);
        expect(store.error).toBe("Override not found");
        expect(store.loading).toBe(false);
    });

    it("fetchOverridesPaginated populates paginatedOverrides and overridesMeta", async () => {
        const meta = { current_page: 2, from: 11, last_page: 5, path: "/", per_page: 10, to: 20, total: 50 };
        const overrides = [{ id: 1, status: "pending" }];
        const params = { page: 2, search: "ani", row_per_page: 10, status: "pending" };

        axiosInstance.get.mockResolvedValueOnce({
            data: { data: { data: overrides, meta } },
        });

        const result = await store.fetchOverridesPaginated(params);

        expect(axiosInstance.get).toHaveBeenCalledWith("hybrid-schedule-overrides", {
            params: {
                page: 2,
                per_page: 10,
                search: "ani",
                status: "pending",
            },
        });
        expect(store.paginatedOverrides).toEqual(overrides);
        expect(store.overridesMeta).toEqual({
            current_page: 2,
            last_page: 5,
            per_page: 10,
            total: 50,
            from: 11,
            to: 20,
        });
        expect(store.overridesLoading).toBe(false);
        expect(store.error).toBe(null);
    });

    it("fetchOverridesPaginated sets error and rethrows on failure", async () => {
        const mockError = {
            response: {
                status: 403,
                data: { message: "Forbidden" },
            },
        };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await expect(store.fetchOverridesPaginated()).rejects.toEqual(mockError);
        expect(store.error).toBe("Forbidden");
        expect(store.overridesLoading).toBe(false);
    });
});
