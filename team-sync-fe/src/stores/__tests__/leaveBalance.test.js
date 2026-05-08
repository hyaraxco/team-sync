import { setActivePinia, createPinia } from "pinia";
import { describe, it, expect, beforeEach, vi } from "vitest";
import { useLeaveRequestStore } from "@/stores/leaveRequest";
import { axiosInstance } from "@/plugins/axios";

vi.mock("@/plugins/axios", () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

describe("Leave Request Store - Leave Balances", () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useLeaveRequestStore();
        vi.clearAllMocks();
    });

    it("fetchMyLeaveBalances populates myLeaveBalances state", async () => {
        const mockBalances = [
            { leave_type: "annual_leave", quota_days: 12, used_days: 3, remaining_days: 9 },
            { leave_type: "sick_leave", quota_days: 14, used_days: 1, remaining_days: 13 },
        ];
        axiosInstance.get.mockResolvedValueOnce({
            data: { data: mockBalances },
        });

        const result = await store.fetchMyLeaveBalances();

        expect(axiosInstance.get).toHaveBeenCalledWith("my-leave-balances");
        expect(store.myLeaveBalances).toEqual(mockBalances);
        expect(result).toEqual(mockBalances);
        expect(store.loading).toBe(false);
    });

    it("fetchMyLeaveBalances sets error on failure", async () => {
        const mockError = {
            response: {
                status: 404,
                data: { message: "Employee profile not found." },
            },
        };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await expect(store.fetchMyLeaveBalances()).rejects.toEqual(mockError);

        expect(store.error).toBe("Employee profile not found.");
        expect(store.myLeaveBalances).toEqual([]);
        expect(store.loading).toBe(false);
    });

    it("fetchUpcomingCutiBersama returns upcoming collective leave", async () => {
        const mockData = [
            { id: 1, name: "Hari Raya Idul Fitri", date: "2026-03-31" },
            { id: 2, name: "Cuti Bersama Natal", date: "2026-12-24" },
        ];
        axiosInstance.get.mockResolvedValueOnce({
            data: { data: mockData },
        });

        const result = await store.fetchUpcomingCutiBersama();

        expect(axiosInstance.get).toHaveBeenCalledWith("my-upcoming-cuti-bersama");
        expect(result).toEqual(mockData);
        expect(store.loading).toBe(false);
    });
});
