import { setActivePinia, createPinia } from "pinia";
import { describe, it, expect, beforeEach, vi } from "vitest";
import { usePerformanceFeedbackStore } from "@/stores/performanceFeedback";
import { axiosInstance } from "@/plugins/axios";

vi.mock("@/plugins/axios", () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

describe("Performance Feedback Store", () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = usePerformanceFeedbackStore();
        vi.clearAllMocks();
    });

    it("fetchReceivedFeedback populates receivedFeedback and pagination", async () => {
        const filters = { category: "peer" };
        const paginatedData = {
            data: [{ id: 1, comment: "Great collaboration" }],
            current_page: 1,
            per_page: 15,
            total: 1,
            last_page: 1,
        };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: paginatedData } });

        const result = await store.fetchReceivedFeedback(filters);

        expect(axiosInstance.get).toHaveBeenCalledWith("/performance/feedback/received", { params: filters });
        expect(store.receivedFeedback).toEqual(paginatedData.data);
        expect(store.pagination).toEqual({
            current_page: 1,
            per_page: 15,
            total: 1,
            last_page: 1,
        });
        expect(result).toEqual(paginatedData);
        expect(store.feedbackLoading).toBe(false);
    });

    it("fetchReceivedFeedback throws and sets error on failure", async () => {
        const mockError = { response: { data: { message: "Failed to fetch received feedback" } } };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await expect(store.fetchReceivedFeedback()).rejects.toEqual(mockError);
        expect(store.error).toBe("Failed to fetch received feedback");
        expect(store.feedbackLoading).toBe(false);
    });

    it("fetchGivenFeedback populates givenFeedback and pagination", async () => {
        const filters = { category: "manager" };
        const paginatedData = {
            data: [{ id: 2, comment: "Needs improvement on estimations" }],
            current_page: 2,
            per_page: 10,
            total: 12,
            last_page: 2,
        };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: paginatedData } });

        const result = await store.fetchGivenFeedback(filters);

        expect(axiosInstance.get).toHaveBeenCalledWith("/performance/feedback/given", { params: filters });
        expect(store.givenFeedback).toEqual(paginatedData.data);
        expect(result).toEqual(paginatedData);
        expect(store.feedbackLoading).toBe(false);
    });

    it("fetchGivenFeedback throws and sets error on failure", async () => {
        const mockError = { response: { data: { message: "Failed to fetch given feedback" } } };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await expect(store.fetchGivenFeedback()).rejects.toEqual(mockError);
        expect(store.error).toBe("Failed to fetch given feedback");
        expect(store.feedbackLoading).toBe(false);
    });

    it("createFeedback prepends new feedback into givenFeedback and marks success", async () => {
        const payload = { recipient_id: 8, comment: "Strong ownership" };
        const createdFeedback = { id: 5, recipient_id: 8, comment: "Strong ownership" };
        store.givenFeedback = [{ id: 4, comment: "Existing feedback" }];
        axiosInstance.post.mockResolvedValueOnce({ data: { data: createdFeedback } });

        const result = await store.createFeedback(payload);

        expect(axiosInstance.post).toHaveBeenCalledWith("/performance/feedback", payload);
        expect(result).toEqual(createdFeedback);
        expect(store.givenFeedback[0]).toEqual(createdFeedback);
        expect(store.success).toBe(true);
        expect(store.feedbackLoading).toBe(false);
    });

    it("createFeedback throws and sets error on failure", async () => {
        const mockError = { response: { data: { message: "Create feedback failed" } } };
        axiosInstance.post.mockRejectedValueOnce(mockError);

        await expect(store.createFeedback({ recipient_id: 1, comment: "x" })).rejects.toEqual(mockError);
        expect(store.error).toBe("Create feedback failed");
        expect(store.success).toBe(false);
        expect(store.feedbackLoading).toBe(false);
    });

    it("acknowledgeFeedback updates receivedFeedback and currentFeedback and marks success", async () => {
        const acknowledged = { id: 12, acknowledged_at: "2026-04-29T08:00:00Z" };
        store.receivedFeedback = [
            { id: 12, acknowledged_at: null },
            { id: 13, acknowledged_at: null },
        ];
        store.currentFeedback = { id: 12, acknowledged_at: null };
        axiosInstance.post.mockResolvedValueOnce({ data: { data: acknowledged } });

        const result = await store.acknowledgeFeedback(12);

        expect(axiosInstance.post).toHaveBeenCalledWith("/performance/feedback/12/acknowledge");
        expect(result).toEqual(acknowledged);
        expect(store.receivedFeedback[0]).toEqual(acknowledged);
        expect(store.currentFeedback).toEqual(acknowledged);
        expect(store.success).toBe(true);
        expect(store.feedbackLoading).toBe(false);
    });

    it("acknowledgeFeedback throws and sets error on failure", async () => {
        const mockError = { response: { data: { message: "Acknowledge failed" } } };
        axiosInstance.post.mockRejectedValueOnce(mockError);

        await expect(store.acknowledgeFeedback(12)).rejects.toEqual(mockError);
        expect(store.error).toBe("Acknowledge failed");
        expect(store.success).toBe(false);
        expect(store.feedbackLoading).toBe(false);
    });
});
