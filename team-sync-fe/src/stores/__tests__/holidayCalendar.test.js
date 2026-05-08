import { setActivePinia, createPinia } from "pinia";
import { describe, it, expect, beforeEach, vi } from "vitest";
import { useHolidayCalendarStore } from "@/stores/holidayCalendar";
import { axiosInstance } from "@/plugins/axios";

vi.mock("@/plugins/axios", () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

describe("Holiday Calendar Store", () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useHolidayCalendarStore();
        vi.clearAllMocks();
    });

    it("fetchAllPaginated populates paginatedHolidays and meta", async () => {
        const paginator = {
            data: [{ id: 1, name: "New Year" }],
            current_page: 2,
            last_page: 4,
            per_page: 25,
            total: 90,
        };
        const params = { page: 2, search: "year", row_per_page: 25 };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: paginator, message: "ok" } });

        const result = await store.fetchAllPaginated(params);

        expect(axiosInstance.get).toHaveBeenCalledWith("holiday-calendars", {
            params,
        });
        expect(result).toEqual({ data: paginator, message: "ok" });
        expect(store.paginatedHolidays).toEqual(paginator.data);
        expect(store.meta).toEqual({
            current_page: 2,
            last_page: 4,
            per_page: 25,
            total: 90,
        });
        expect(store.loading).toBe(false);
    });

    it("fetchAllPaginated sets error and rethrows on failure", async () => {
        const mockError = {
            response: {
                status: 500,
                data: { message: "Failed to fetch holidays" },
            },
        };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await expect(store.fetchAllPaginated()).rejects.toEqual(mockError);

        expect(store.error).toBe("Failed to fetch holidays");
        expect(store.loading).toBe(false);
    });

    it("createHoliday posts payload and sets success message", async () => {
        const payload = { name: "Independence Day", date: "2026-08-17" };
        const created = { id: 20, ...payload };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: "Holiday created",
                data: created,
            },
        });

        const result = await store.createHoliday(payload);

        expect(axiosInstance.post).toHaveBeenCalledWith("holiday-calendars", payload);
        expect(result).toEqual(created);
        expect(store.success).toBe("Holiday created");
        expect(store.loading).toBe(false);
    });

    it("createHoliday sets error and rethrows on failure", async () => {
        const mockError = {
            response: {
                status: 422,
                data: { errors: { date: ["Date is required"] } },
            },
        };
        axiosInstance.post.mockRejectedValueOnce(mockError);

        await expect(store.createHoliday({})).rejects.toEqual(mockError);
        expect(store.error).toEqual({ date: ["Date is required"] });
        expect(store.loading).toBe(false);
    });

    it("updateHoliday sends PUT, sets success, and returns data", async () => {
        const payload = { name: "Updated Holiday" };
        const updated = { id: 5, name: "Updated Holiday" };
        axiosInstance.put.mockResolvedValueOnce({
            data: {
                message: "Holiday updated",
                data: updated,
            },
        });

        const result = await store.updateHoliday(5, payload);

        expect(axiosInstance.put).toHaveBeenCalledWith("holiday-calendars/5", payload);
        expect(result).toEqual(updated);
        expect(store.success).toBe("Holiday updated");
        expect(store.loading).toBe(false);
    });

    it("updateHoliday sets error and rethrows on failure", async () => {
        const mockError = {
            response: {
                status: 404,
                data: { message: "Holiday not found" },
            },
        };
        axiosInstance.put.mockRejectedValueOnce(mockError);

        await expect(store.updateHoliday(999, {})).rejects.toEqual(mockError);
        expect(store.error).toBe("Holiday not found");
        expect(store.loading).toBe(false);
    });

    it("deleteHoliday sends DELETE, sets success, and returns response", async () => {
        const responseData = { message: "Holiday deleted" };
        axiosInstance.delete.mockResolvedValueOnce({ data: responseData });

        const result = await store.deleteHoliday(8);

        expect(axiosInstance.delete).toHaveBeenCalledWith("holiday-calendars/8");
        expect(result).toEqual(responseData);
        expect(store.success).toBe("Holiday deleted");
        expect(store.loading).toBe(false);
    });

    it("deleteHoliday sets error and rethrows on failure", async () => {
        const mockError = {
            response: {
                status: 403,
                data: { message: "Forbidden" },
            },
        };
        axiosInstance.delete.mockRejectedValueOnce(mockError);

        await expect(store.deleteHoliday(1)).rejects.toEqual(mockError);
        expect(store.error).toBe("Forbidden");
        expect(store.loading).toBe(false);
    });
});
