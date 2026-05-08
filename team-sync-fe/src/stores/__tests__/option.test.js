import { setActivePinia, createPinia } from "pinia";
import { describe, it, expect, beforeEach, vi, afterEach } from "vitest";
import { useOptionStore } from "@/stores/option";
import { axiosInstance } from "@/plugins/axios";

vi.mock("@/plugins/axios", () => ({
    axiosInstance: {
        get: vi.fn(),
    },
}));

describe("Option Store", () => {
    let store;
    let consoleErrorSpy;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useOptionStore();
        vi.clearAllMocks();
        consoleErrorSpy = vi.spyOn(console, "error").mockImplementation(() => {});
    });

    afterEach(() => {
        consoleErrorSpy.mockRestore();
    });

    it("fetchDepartments populates departments", async () => {
        const payload = [{ id: 1, name: "Engineering" }];
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payload } });

        await store.fetchDepartments();

        expect(axiosInstance.get).toHaveBeenCalledWith("/options/departments");
        expect(store.departments).toEqual(payload);
        expect(store.loading).toBe(false);
    });

    it("fetchDepartments sets error on failure", async () => {
        const mockError = { response: { status: 400, data: { message: "Departments failed" } } };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await store.fetchDepartments();

        expect(store.error).toBe("Departments failed");
        expect(consoleErrorSpy).toHaveBeenCalled();
        expect(store.loading).toBe(false);
    });

    it("fetchEmploymentTypes populates employmentTypes", async () => {
        const payload = [{ id: 1, label: "Permanent" }];
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payload } });

        await store.fetchEmploymentTypes();

        expect(axiosInstance.get).toHaveBeenCalledWith("/options/employment-types");
        expect(store.employmentTypes).toEqual(payload);
        expect(store.loading).toBe(false);
    });

    it("fetchEmploymentTypes sets error on failure", async () => {
        const mockError = { response: { status: 500, data: { message: "Employment types failed" } } };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await store.fetchEmploymentTypes();

        expect(store.error).toBe("Employment types failed");
        expect(consoleErrorSpy).toHaveBeenCalled();
        expect(store.loading).toBe(false);
    });

    it("fetchJobStatuses populates jobStatuses", async () => {
        const payload = [{ id: 1, label: "Active" }];
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payload } });

        await store.fetchJobStatuses();

        expect(axiosInstance.get).toHaveBeenCalledWith("/options/job-statuses");
        expect(store.jobStatuses).toEqual(payload);
        expect(store.loading).toBe(false);
    });

    it("fetchJobStatuses sets error on failure", async () => {
        const mockError = { response: { status: 404, data: { message: "Job statuses failed" } } };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await store.fetchJobStatuses();

        expect(store.error).toBe("Job statuses failed");
        expect(consoleErrorSpy).toHaveBeenCalled();
        expect(store.loading).toBe(false);
    });

    it("fetchLeaveTypes populates leaveTypes", async () => {
        const payload = [{ id: 1, name: "Annual Leave" }];
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payload } });

        await store.fetchLeaveTypes();

        expect(axiosInstance.get).toHaveBeenCalledWith("/options/leave-types");
        expect(store.leaveTypes).toEqual(payload);
        expect(store.loading).toBe(false);
    });

    it("fetchLeaveTypes sets error on failure", async () => {
        const mockError = { response: { status: 400, data: { message: "Leave types failed" } } };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await store.fetchLeaveTypes();

        expect(store.error).toBe("Leave types failed");
        expect(consoleErrorSpy).toHaveBeenCalled();
        expect(store.loading).toBe(false);
    });

    it("fetchWorkLocations populates workLocations", async () => {
        const payload = [{ id: 1, name: "Jakarta HQ" }];
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payload } });

        await store.fetchWorkLocations();

        expect(axiosInstance.get).toHaveBeenCalledWith("/options/work-locations");
        expect(store.workLocations).toEqual(payload);
        expect(store.loading).toBe(false);
    });

    it("fetchWorkLocations sets error on failure", async () => {
        const mockError = { response: { status: 500, data: { message: "Work locations failed" } } };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await store.fetchWorkLocations();

        expect(store.error).toBe("Work locations failed");
        expect(consoleErrorSpy).toHaveBeenCalled();
        expect(store.loading).toBe(false);
    });

    it("fetchReligions populates religions", async () => {
        const payload = [{ id: 1, name: "Islam" }];
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payload } });

        await store.fetchReligions();

        expect(axiosInstance.get).toHaveBeenCalledWith("/options/religions");
        expect(store.religions).toEqual(payload);
        expect(store.loading).toBe(false);
    });

    it("fetchReligions sets error on failure", async () => {
        const mockError = { response: { status: 400, data: { message: "Religions failed" } } };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await store.fetchReligions();

        expect(store.error).toBe("Religions failed");
        expect(consoleErrorSpy).toHaveBeenCalled();
        expect(store.loading).toBe(false);
    });

    it("fetchMaritalStatuses populates maritalStatuses", async () => {
        const payload = [{ id: 1, label: "Married" }];
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payload } });

        await store.fetchMaritalStatuses();

        expect(axiosInstance.get).toHaveBeenCalledWith("/options/marital-statuses");
        expect(store.maritalStatuses).toEqual(payload);
        expect(store.loading).toBe(false);
    });

    it("fetchMaritalStatuses sets error on failure", async () => {
        const mockError = { response: { status: 404, data: { message: "Marital statuses failed" } } };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await store.fetchMaritalStatuses();

        expect(store.error).toBe("Marital statuses failed");
        expect(consoleErrorSpy).toHaveBeenCalled();
        expect(store.loading).toBe(false);
    });

    it("fetchBloodTypes populates bloodTypes", async () => {
        const payload = [{ id: 1, label: "A" }];
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payload } });

        await store.fetchBloodTypes();

        expect(axiosInstance.get).toHaveBeenCalledWith("/options/blood-types");
        expect(store.bloodTypes).toEqual(payload);
        expect(store.loading).toBe(false);
    });

    it("fetchBloodTypes sets error on failure", async () => {
        const mockError = { response: { status: 500, data: { message: "Blood types failed" } } };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await store.fetchBloodTypes();

        expect(store.error).toBe("Blood types failed");
        expect(consoleErrorSpy).toHaveBeenCalled();
        expect(store.loading).toBe(false);
    });

    it("fetchPtkpStatuses populates ptkpStatuses", async () => {
        const payload = [{ id: 1, code: "TK/0" }];
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payload } });

        await store.fetchPtkpStatuses();

        expect(axiosInstance.get).toHaveBeenCalledWith("/options/ptkp-statuses");
        expect(store.ptkpStatuses).toEqual(payload);
        expect(store.loading).toBe(false);
    });

    it("fetchPtkpStatuses sets error on failure", async () => {
        const mockError = { response: { status: 400, data: { message: "PTKP statuses failed" } } };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await store.fetchPtkpStatuses();

        expect(store.error).toBe("PTKP statuses failed");
        expect(consoleErrorSpy).toHaveBeenCalled();
        expect(store.loading).toBe(false);
    });
});
