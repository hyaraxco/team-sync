import { setActivePinia, createPinia } from "pinia";
import { describe, it, expect, beforeEach, vi } from "vitest";
import { useAttendanceStore } from "@/stores/attendance";
import { axiosInstance } from "@/plugins/axios";

vi.mock("@/plugins/axios", () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

describe("Attendance Store", () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useAttendanceStore();
        vi.clearAllMocks();
    });

    it("fetchAttendances populates attendances state", async () => {
        const params = { month: "2026-04", page: 1 };
        const mockResponse = {
            data: {
                data: [
                    { id: 1, status: "present" },
                    { id: 2, status: "sick" },
                ],
            },
        };
        axiosInstance.get.mockResolvedValueOnce(mockResponse);

        await store.fetchAttendances(params);

        expect(axiosInstance.get).toHaveBeenCalledWith("my-attendances", { params });
        expect(store.attendances).toEqual(mockResponse.data.data);
        expect(store.loading).toBe(false);
        expect(store.error).toBe(null);
    });

    it("checkIn calls POST and returns data", async () => {
        const payload = { check_in_lat: -6.2, check_in_long: 106.8, actual_work_mode: "remote", notes: "On time" };
        const mockData = { id: 10, check_in_time: "08:00:00", actual_work_mode: "remote" };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: "Checked in successfully",
                data: mockData,
            },
        });

        const result = await store.checkIn(payload);

        expect(axiosInstance.post).toHaveBeenCalledWith("attendances/check-in", payload);
        expect(result).toEqual(mockData);
        expect(store.todayAttendance).toEqual(mockData);
        expect(store.success).toBe("Checked in successfully");
    });

    it("checkOut calls POST and returns data", async () => {
        const payload = { check_out_lat: -6.3, check_out_long: 106.7, notes: "Done" };
        const mockData = { id: 10, check_out_time: "17:00:00" };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: "Checked out successfully",
                data: mockData,
            },
        });

        const result = await store.checkOut(payload);

        expect(axiosInstance.post).toHaveBeenCalledWith("attendances/check-out", payload);
        expect(result).toEqual(mockData);
        expect(store.todayAttendance).toEqual(mockData);
        expect(store.success).toBe("Checked out successfully");
    });

    it("fetchPolicyMismatches returns paginated response payload", async () => {
        const params = { page: 2, search: "late", status: "open", row_per_page: 5 };
        const mockResponse = {
            data: {
                data: [{ id: 1, status: "open" }],
                meta: { current_page: 2, total: 1 },
            },
        };
        axiosInstance.get.mockResolvedValueOnce(mockResponse);

        const result = await store.fetchPolicyMismatches(params);

        expect(axiosInstance.get).toHaveBeenCalledWith("attendance-policy-mismatches", { params });
        expect(result).toEqual(mockResponse.data);
    });

    it("acknowledgePolicyMismatch calls POST and returns data", async () => {
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: "Mismatch acknowledged",
                data: { id: 5, status: "acknowledged" },
            },
        });

        const result = await store.acknowledgePolicyMismatch(5, "Acknowledged by HR");

        expect(axiosInstance.post).toHaveBeenCalledWith("attendance-policy-mismatches/5/acknowledge", {
            resolution_notes: "Acknowledged by HR",
        });
        expect(result).toEqual({ id: 5, status: "acknowledged" });
        expect(store.success).toBe("Mismatch acknowledged");
    });

    it("resolvePolicyMismatch calls POST and returns data", async () => {
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: "Mismatch resolved",
                data: { id: 5, status: "resolved" },
            },
        });

        const result = await store.resolvePolicyMismatch(5, "Issue corrected");

        expect(axiosInstance.post).toHaveBeenCalledWith("attendance-policy-mismatches/5/resolve", {
            resolution_notes: "Issue corrected",
        });
        expect(result).toEqual({ id: 5, status: "resolved" });
        expect(store.success).toBe("Mismatch resolved");
    });

    it("sets error on failure", async () => {
        const mockError = {
            response: {
                status: 400,
                data: { message: "Invalid attendance request" },
            },
        };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await store.fetchAttendances({});

        expect(store.error).toBe("Invalid attendance request");
        expect(store.loading).toBe(false);
    });
});
