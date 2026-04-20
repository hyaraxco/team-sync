import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";

export const usePerformanceFeedbackStore = defineStore("performanceFeedback", {
  state: () => ({
    // Feedback
    receivedFeedback: [],
    givenFeedback: [],
    currentFeedback: null,
    feedbackLoading: false,

    // Pagination
    pagination: {
      current_page: 1,
      per_page: 15,
      total: 0,
      last_page: 1,
    },

    error: null,
    success: false,
  }),

  actions: {
    async fetchReceivedFeedback(filters = {}) {
      this.feedbackLoading = true;
      this.error = null;
      try {
        const response = await axiosInstance.get(
          "/performance/feedback/received",
          { params: filters },
        );
        this.receivedFeedback = response.data.data.data || [];
        this.pagination = {
          current_page: response.data.data.current_page,
          per_page: response.data.data.per_page,
          total: response.data.data.total,
          last_page: response.data.data.last_page,
        };
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.feedbackLoading = false;
      }
    },

    async fetchGivenFeedback(filters = {}) {
      this.feedbackLoading = true;
      this.error = null;
      try {
        const response = await axiosInstance.get(
          "/performance/feedback/given",
          { params: filters },
        );
        this.givenFeedback = response.data.data.data || [];
        this.pagination = {
          current_page: response.data.data.current_page,
          per_page: response.data.data.per_page,
          total: response.data.data.total,
          last_page: response.data.data.last_page,
        };
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.feedbackLoading = false;
      }
    },

    async fetchFeedbackById(id) {
      this.feedbackLoading = true;
      this.error = null;
      try {
        const response = await axiosInstance.get(`/performance/feedback/${id}`);
        this.currentFeedback = response.data.data;
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.feedbackLoading = false;
      }
    },

    async createFeedback(data) {
      this.feedbackLoading = true;
      this.error = null;
      this.success = false;
      try {
        const response = await axiosInstance.post(
          "/performance/feedback",
          data,
        );
        this.givenFeedback.unshift(response.data.data);
        this.success = true;
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.feedbackLoading = false;
      }
    },

    async acknowledgeFeedback(id) {
      this.feedbackLoading = true;
      this.error = null;
      this.success = false;
      try {
        const response = await axiosInstance.post(
          `/performance/feedback/${id}/acknowledge`,
        );
        const index = this.receivedFeedback.findIndex((f) => f.id === id);
        if (index !== -1) {
          this.receivedFeedback[index] = response.data.data;
        }
        if (this.currentFeedback?.id === id) {
          this.currentFeedback = response.data.data;
        }
        this.success = true;
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.feedbackLoading = false;
      }
    },

    resetState() {
      this.error = null;
      this.success = false;
    },
  },
});
