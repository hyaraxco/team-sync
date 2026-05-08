import { setActivePinia, createPinia } from "pinia";
import { describe, it, expect, beforeEach, vi } from "vitest";
import { useOvertimeStore } from "@/stores/overtime";
import { axiosInstance } from "@/plugins/axios";

vi.mock("@/plugins/axios", () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

describe("Overtime Store", () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useOvertimeStore();
        vi.clearAllMocks();
    });

    it("fetchMyOvertime stores paginated employee overtime records", async () => {
        const paginator = {
            data: [{ id: 1, status: "approved", hours: 2.5 }],
            meta: {
                current_page: 2,
                last_page: 4,
                per_page: 12,
                total: 37,
                from: 13,
                to: 24,
            },
        };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: paginator } });

        await store.fetchMyOvertime({ page: 2, per_page: 12, status: "approved" });

        expect(axiosInstance.get).toHaveBeenCalledWith("overtime/my-overtime", {
            params: {
                page: 2,
                per_page: 12,
                status: "approved",
            },
        });
        expect(store.myRecords).toEqual(paginator.data);
        expect(store.meta).toEqual(paginator.meta);
        expect(store.loading).toBe(false);
        expect(store.error).toBe(null);
    });

    it("fetchMyOvertime sets error on failure", async () => {
        axiosInstance.get.mockRejectedValueOnce({
            response: {
                data: {
                    message: "Overtime feature unavailable",
                },
            },
        });

        await store.fetchMyOvertime();

        expect(store.error).toBe("Overtime feature unavailable");
        expect(store.loading).toBe(false);
    });
});
