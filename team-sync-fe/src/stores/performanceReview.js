import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";

export const usePerformanceReviewStore = defineStore("performanceReview", {
  state: () => ({
    // Review Cycles
    cycles: [],
    currentCycle: null,
    cyclesLoading: false,

    // Reviews
    myReviews: [],
    teamReviews: [],
    currentReview: null,
    reviewsLoading: false,

    // Review Sections
    sections: [],
    sectionsLoading: false,

    // Pending Calibration
    pendingCalibrationReviews: [],
    pendingCalibrationLoading: false,

    // Calibration Context
    calibrationContext: null,
    calibrationContextLoading: false,

    // Readiness Validation
    readinessResult: null,
    readinessLoading: false,

    // TOPSIS Ranking
    topsisResult: null,
    topsisLoading: false,

    // Outcome Rules
    outcomeRules: [],
    outcomeRulesLoading: false,

    // Templates
    templates: [],
    templatesLoading: false,

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
    // Review Cycles
    async fetchCycles(filters = {}) {
      this.cyclesLoading = true;
      this.error = null;
      try {
        const response = await axiosInstance.get("/performance/cycles", {
          params: filters,
        });
        this.cycles = response.data.data.data || [];
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
        this.cyclesLoading = false;
      }
    },

    async fetchCycleById(id) {
      this.cyclesLoading = true;
      this.error = null;
      try {
        const response = await axiosInstance.get(`/performance/cycles/${id}`);
        this.currentCycle = response.data.data;
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.cyclesLoading = false;
      }
    },

    async createCycle(data) {
      this.cyclesLoading = true;
      this.error = null;
      this.success = false;
      try {
        const response = await axiosInstance.post("/performance/cycles", data);
        this.cycles.unshift(response.data.data);
        this.success = true;
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.cyclesLoading = false;
      }
    },

    async updateCycle(id, data) {
      this.cyclesLoading = true;
      this.error = null;
      this.success = false;
      try {
        const response = await axiosInstance.put(
          `/performance/cycles/${id}`,
          data,
        );
        const index = this.cycles.findIndex((c) => c.id === id);
        if (index !== -1) {
          this.cycles[index] = response.data.data;
        }
        this.success = true;
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.cyclesLoading = false;
      }
    },

    async deleteCycle(id) {
      this.cyclesLoading = true;
      this.error = null;
      try {
        await axiosInstance.delete(`/performance/cycles/${id}`);
        this.cycles = this.cycles.filter((c) => c.id !== id);
        return true;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.cyclesLoading = false;
      }
    },

    async generateReviews(cycleId) {
      this.cyclesLoading = true;
      this.error = null;
      try {
        const response = await axiosInstance.post(`/performance/cycles/${cycleId}/generate-reviews`);
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.cyclesLoading = false;
      }
    },

    async assignReviewer(reviewId, reviewerId) {
      this.error = null;
      try {
        const response = await axiosInstance.put(`/performance/reviews/${reviewId}/assign-reviewer`, {
          reviewer_id: reviewerId
        });
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      }
    },

    // Reviews
    async fetchMyReviews(filters = {}) {
      this.reviewsLoading = true;
      this.error = null;
      try {
        const response = await axiosInstance.get(
          "/performance/reviews/my-reviews",
          { params: filters },
        );
        this.myReviews = response.data.data.data || [];
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
        this.reviewsLoading = false;
      }
    },

    async fetchTeamReviews(filters = {}) {
      this.reviewsLoading = true;
      this.error = null;
      try {
        const response = await axiosInstance.get(
          "/performance/reviews/team-reviews",
          { params: filters },
        );
        this.teamReviews = response.data.data.data || [];
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
        this.reviewsLoading = false;
      }
    },

    async fetchReviewById(id) {
      this.reviewsLoading = true;
      this.error = null;
      try {
        const response = await axiosInstance.get(`/performance/reviews/${id}`);
        this.currentReview = response.data.data;
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.reviewsLoading = false;
      }
    },

    async fetchActiveSections() {
      this.sectionsLoading = true;
      this.error = null;
      try {
        const response = await axiosInstance.get(
          "/performance/reviews/sections",
        );
        this.sections = response.data.data;
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.sectionsLoading = false;
      }
    },

    async submitSelfAssessment(reviewId, responses) {
      this.reviewsLoading = true;
      this.error = null;
      this.success = false;
      try {
        const response = await axiosInstance.post(
          `/performance/reviews/${reviewId}/self-assessment`,
          {
            responses,
          },
        );
        this.currentReview = response.data.data;
        this.success = true;
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.reviewsLoading = false;
      }
    },

    async submitManagerAssessment(reviewId, responses, finalRating) {
      this.reviewsLoading = true;
      this.error = null;
      this.success = false;
      try {
        const response = await axiosInstance.post(
          `/performance/reviews/${reviewId}/manager-assessment`,
          {
            responses,
            final_rating: finalRating,
          },
        );
        this.currentReview = response.data.data;
        this.success = true;
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.reviewsLoading = false;
      }
    },

    async calibrateReview(reviewId, responses) {
      this.reviewsLoading = true;
      this.error = null;
      this.success = false;
      try {
        const response = await axiosInstance.post(
          `/performance/reviews/${reviewId}/calibrate`,
          { responses },
        );
        this.currentReview = response.data.data;
        this.success = true;
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.reviewsLoading = false;
      }
    },

    async fetchPendingCalibration(filters = {}) {
      this.pendingCalibrationLoading = true;
      this.error = null;
      try {
        const response = await axiosInstance.get(
          "/performance/reviews/pending-calibration",
          { params: filters },
        );
        this.pendingCalibrationReviews = response.data.data.data || [];
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
        this.pendingCalibrationLoading = false;
      }
    },

    async fetchCalibrationContext(reviewId) {
      this.calibrationContextLoading = true;
      this.error = null;
      try {
        const response = await axiosInstance.get(
          `/performance/reviews/${reviewId}/calibration-context`,
        );
        this.calibrationContext = response.data.data;
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.calibrationContextLoading = false;
      }
    },

    async fetchValidateReadiness(reviewId) {
      this.readinessLoading = true;
      this.readinessResult = null;
      try {
        const response = await axiosInstance.get(
          `/performance/reviews/${reviewId}/validate-readiness`,
        );
        this.readinessResult = response.data.data;
        return response.data.data;
      } catch (error) {
        // Best-effort fetch — don't pollute store error state (e.g., 403 for non-HR users)
        console.error("fetchValidateReadiness failed:", error?.response?.status);
        throw error;
      } finally {
        this.readinessLoading = false;
      }
    },

    async fetchTopsisRanking(cycleId, weights = null) {
      this.topsisLoading = true;
      this.topsisResult = null;
      this.error = null;
      try {
        const params = {};
        if (weights) {
          params.w_avg_manager_rating      = weights.avg_manager_rating;
          params.w_final_rating            = weights.final_rating;
          params.w_avg_goal_completion     = weights.avg_goal_completion;
          params.w_goal_completion_ratio   = weights.goal_completion_ratio;
          params.w_positive_feedback_count = weights.positive_feedback_count;
        }
        const response = await axiosInstance.get(
          `/performance/cycles/${cycleId}/topsis-ranking`,
          { params }
        );
        this.topsisResult = response.data.data;
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.topsisLoading = false;
      }
    },

    async fetchOutcomeRules() {
      this.outcomeRulesLoading = true;
      this.error = null;
      try {
        const response = await axiosInstance.get('/performance/outcome-rules');
        this.outcomeRules = response.data.data;
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.outcomeRulesLoading = false;
      }
    },

    async createOutcomeRule(data) {
      this.error = null;
      try {
        const response = await axiosInstance.post('/performance/outcome-rules', data);
        this.outcomeRules.push(response.data.data);
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      }
    },

    async updateOutcomeRule(id, data) {
      this.error = null;
      try {
        const response = await axiosInstance.put(`/performance/outcome-rules/${id}`, data);
        const index = this.outcomeRules.findIndex(r => r.id === id);
        if (index !== -1) this.outcomeRules[index] = response.data.data;
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      }
    },

    async deleteOutcomeRule(id) {
      this.error = null;
      try {
        await axiosInstance.delete(`/performance/outcome-rules/${id}`);
        this.outcomeRules = this.outcomeRules.filter(r => r.id !== id);
        return true;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      }
    },

    // Templates
    async fetchTemplates() {
      this.templatesLoading = true;
      this.error = null;
      try {
        const response = await axiosInstance.get('/performance/templates');
        this.templates = response.data.data;
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.templatesLoading = false;
      }
    },

    async fetchTemplateById(id) {
      this.templatesLoading = true;
      try {
        const response = await axiosInstance.get(`/performance/templates/${id}`);
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.templatesLoading = false;
      }
    },

    async createTemplate(data) {
      this.templatesLoading = true;
      try {
        const response = await axiosInstance.post('/performance/templates', data);
        this.templates.push(response.data.data);
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.templatesLoading = false;
      }
    },

    async updateTemplate(id, data) {
      this.templatesLoading = true;
      try {
        const response = await axiosInstance.put(`/performance/templates/${id}`, data);
        const index = this.templates.findIndex(t => t.id === id);
        if (index !== -1) this.templates[index] = response.data.data;
        return response.data.data;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.templatesLoading = false;
      }
    },

    async deleteTemplate(id) {
      this.templatesLoading = true;
      try {
        await axiosInstance.delete(`/performance/templates/${id}`);
        this.templates = this.templates.filter(t => t.id !== id);
        return true;
      } catch (error) {
        this.error = handleError(error);
        throw error;
      } finally {
        this.templatesLoading = false;
      }
    },

    resetState() {
      this.error = null;
      this.success = false;
      this.readinessResult = null;
      this.readinessLoading = false;
      this.calibrationContext = null;
      this.calibrationContextLoading = false;
    },
  },
});
