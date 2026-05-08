import { defineStore } from "pinia";
import { axiosInstance } from "@/plugins/axios";
import { handleError } from "@/helpers/errorHelper";

export const useTaskStore = defineStore("task", {
    state: () => ({
        tasks: [],
        loading: false,
        error: null,
        success: null,
    }),

    actions: {
        async fetchProjectTasks(projectId) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.get("project-tasks", {
                    params: {
                        project_id: projectId,
                    },
                });

                this.tasks = response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async createTask(payload) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.post("project-tasks", payload);

                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async updateTask(id, payload) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.post(`project-tasks/${id}`, {
                    ...payload,
                    _method: "PUT",
                });

                // Update task in local state
                const taskIndex = this.tasks.findIndex((t) => t.id === id);
                if (taskIndex !== -1 && response.data.data) {
                    this.tasks[taskIndex] = response.data.data;
                }

                this.success = response.data.message;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async deleteTask(id) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axiosInstance.delete(`project-tasks/${id}`);

                this.success = response.data.message;
            } catch (error) {
                this.error = handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async updateTaskStatus(taskId, newStatus) {
            this.error = null;

            try {
                const response = await axiosInstance.post(`project-tasks/${taskId}`, {
                    status: newStatus,
                    _method: "PUT",
                });

                // Update task status in local state
                const taskIndex = this.tasks.findIndex((t) => t.id === taskId);
                if (taskIndex !== -1) {
                    if (response.data?.data) {
                        this.tasks[taskIndex] = response.data.data;
                    } else {
                        this.tasks[taskIndex].status = newStatus;
                    }
                }

                return response.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async fetchTaskComments(taskId) {
            this.error = null;

            try {
                const response = await axiosInstance.get(`project-tasks/${taskId}/comments`);
                return response.data.data || [];
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async createTaskComment(taskId, payload) {
            this.error = null;

            try {
                const response = await axiosInstance.post(`project-tasks/${taskId}/comments`, payload);
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async updateTaskComment(taskId, commentId, payload) {
            this.error = null;

            try {
                const response = await axiosInstance.put(`project-tasks/${taskId}/comments/${commentId}`, payload);
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async deleteTaskComment(taskId, commentId) {
            this.error = null;

            try {
                await axiosInstance.delete(`project-tasks/${taskId}/comments/${commentId}`);
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async fetchTaskAttachments(taskId) {
            this.error = null;

            try {
                const response = await axiosInstance.get(`project-tasks/${taskId}/attachments`);
                return response.data.data || [];
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async fetchTaskStatusLogs(taskId) {
            this.error = null;

            try {
                const response = await axiosInstance.get(`project-tasks/${taskId}/status-logs`);
                return response.data.data || [];
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async uploadTaskAttachment(taskId, file) {
            this.error = null;

            try {
                const formData = new FormData();
                formData.append("file", file);

                const response = await axiosInstance.post(`project-tasks/${taskId}/attachments`, formData);
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },

        async deleteTaskAttachment(taskId, attachmentId) {
            this.error = null;

            try {
                await axiosInstance.delete(`project-tasks/${taskId}/attachments/${attachmentId}`);
            } catch (error) {
                this.error = handleError(error);
                throw error;
            }
        },
    },
});
