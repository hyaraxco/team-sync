import { setActivePinia, createPinia } from "pinia";
import { describe, it, expect, beforeEach, vi } from "vitest";
import { useAttendancePolicyStore } from "../attendancePolicy";
import { axiosInstance } from "@/plugins/axios";

vi.mock("@/plugins/axios", () => ({
    axiosInstance: {
        get: vi.fn(),
        put: vi.fn(),
    },
}));

describe("AttendancePolicy Store", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it("should fetch policies correctly", async () => {
        const store = useAttendancePolicyStore();
        const mockResponse = { data: { data: [{ id: 1, employment_type: "full_time" }] } };
        axiosInstance.get.mockResolvedValueOnce(mockResponse);

        await store.fetchPolicies();

        expect(axiosInstance.get).toHaveBeenCalledWith("attendance-policies");
        expect(store.policies).toEqual(mockResponse.data.data);
        expect(store.loading).toBe(false);
        expect(store.error).toBe(null);
    });

    it("should handle fetch errors gracefully", async () => {
        const store = useAttendancePolicyStore();
        const mockError = new Error("Network Error");
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await store.fetchPolicies();

        expect(axiosInstance.get).toHaveBeenCalledWith("attendance-policies");
        expect(store.policies).toEqual([]);
        expect(store.loading).toBe(false);
        expect(store.error).toBe("Network Error");
    });

    it("should update policy correctly", async () => {
        const store = useAttendancePolicyStore();
        const mockResponse = { data: { data: { id: 1, employment_type: "full_time", work_days_per_week: 4 } } };
        axiosInstance.put.mockResolvedValueOnce(mockResponse);

        const payload = { work_days_per_week: 4 };
        const result = await store.updatePolicy(1, payload);

        expect(axiosInstance.put).toHaveBeenCalledWith("attendance-policies/1", payload);
        expect(result).toEqual(mockResponse.data.data);
        expect(store.loading).toBe(false);
        expect(store.error).toBe(null);
    });

    it("should handle update error gracefully", async () => {
        const store = useAttendancePolicyStore();
        const mockError = new Error("Network Error");
        axiosInstance.put.mockRejectedValueOnce(mockError);

        try {
            await store.updatePolicy(1, { work_days_per_week: 4 });
        } catch (e) {
            expect(e).toBe(mockError);
        }

        expect(axiosInstance.put).toHaveBeenCalledWith("attendance-policies/1", { work_days_per_week: 4 });
        expect(store.loading).toBe(false);
        expect(store.error).toBe("Network Error");
    });
});
