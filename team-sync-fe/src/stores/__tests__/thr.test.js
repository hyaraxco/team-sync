import { setActivePinia, createPinia } from "pinia";
import { describe, it, expect, beforeEach, vi } from "vitest";
import { useThrStore } from "@/stores/thr";
import { axiosInstance } from "@/plugins/axios";

vi.mock("@/plugins/axios", () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

describe("THR Store", () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useThrStore();
        vi.clearAllMocks();
    });

    it("fetchThrPayrolls stores paginated THR payrolls", async () => {
        const paginator = {
            data: [{ id: 1, status: "pending", event_name: "Eid 2026" }],
            meta: { current_page: 1, last_page: 2, per_page: 15, total: 20 },
        };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: paginator } });

        await store.fetchThrPayrolls({ year: 2026 });

        expect(axiosInstance.get).toHaveBeenCalledWith("/thr", { params: { year: 2026 } });
        expect(store.thrPayrolls).toEqual(paginator.data);
        expect(store.meta).toEqual(paginator.meta);
        expect(store.loading).toBe(false);
    });

    it("fetchThrPayroll stores single THR payroll", async () => {
        const payroll = { id: 1, status: "approved", event_name: "Eid 2026" };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payroll } });

        const result = await store.fetchThrPayroll(1);

        expect(axiosInstance.get).toHaveBeenCalledWith("/thr/1");
        expect(store.thrPayroll).toEqual(payroll);
        expect(result).toEqual(payroll);
    });

    it("fetchThrDetails stores THR details", async () => {
        const details = {
            data: [{ id: 1, employee_name: "John", total_net_amount: 5000000 }],
            meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
        };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: details } });

        await store.fetchThrDetails(1);

        expect(store.thrDetails).toEqual(details.data);
        expect(store.detailsMeta).toEqual(details.meta);
    });

    it("fetchYearSummary stores year summary", async () => {
        const summary = { total_thr_amount: 100000000, total_employees: 20 };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: summary } });

        const result = await store.fetchYearSummary(2026);

        expect(axiosInstance.get).toHaveBeenCalledWith("/thr/year-summary", { params: { year: 2026 } });
        expect(store.yearSummary).toEqual(summary);
        expect(result).toEqual(summary);
    });

    it("simulate stores simulation result", async () => {
        const simulation = { total_net_amount: 5000000, total_tax: 500000 };
        axiosInstance.post.mockResolvedValueOnce({ data: { data: simulation } });

        const result = await store.simulate({ event_name: "Eid", religion: "islam" });

        expect(axiosInstance.post).toHaveBeenCalledWith("/thr/simulate", { event_name: "Eid", religion: "islam" });
        expect(store.simulation).toEqual(simulation);
        expect(result).toEqual(simulation);
    });

    it("generate calls POST endpoint", async () => {
        const response = { success: true, message: "THR generated" };
        axiosInstance.post.mockResolvedValueOnce({ data: response });

        const result = await store.generate({ event_name: "Eid", religion: "islam" });

        expect(axiosInstance.post).toHaveBeenCalledWith("/thr/generate", { event_name: "Eid", religion: "islam" });
        expect(result).toEqual(response);
    });

    it("approve calls POST endpoint", async () => {
        const response = { success: true };
        axiosInstance.post.mockResolvedValueOnce({ data: response });

        await store.approve(1);

        expect(axiosInstance.post).toHaveBeenCalledWith("/thr/1/approve");
    });

    it("markAsPaid calls POST endpoint with payment date", async () => {
        const response = { success: true };
        axiosInstance.post.mockResolvedValueOnce({ data: response });

        await store.markAsPaid(1, "2026-05-15");

        expect(axiosInstance.post).toHaveBeenCalledWith("/thr/1/mark-as-paid", { payment_date: "2026-05-15" });
    });

    it("sets error on failure", async () => {
        const error = { response: { data: { message: "THR generation failed" } } };
        axiosInstance.get.mockRejectedValueOnce(error);

        await expect(store.fetchThrPayrolls()).rejects.toThrow();
        expect(store.error).toBe("THR generation failed");
        expect(store.loading).toBe(false);
    });
});
