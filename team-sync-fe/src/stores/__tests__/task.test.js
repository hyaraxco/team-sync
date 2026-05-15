import { setActivePinia, createPinia } from "pinia";
import { describe, it, expect, beforeEach, vi } from "vitest";
import { useTaskStore } from "@/stores/task";
import { axiosInstance } from "@/plugins/axios";

vi.mock("@/plugins/axios", () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

describe("Task Store", () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useTaskStore();
        vi.clearAllMocks();
    });

    it("fetchProjectTasks populates tasks state", async () => {
        const mockTasks = [
            { id: 1, name: "Task A" },
            { id: 2, name: "Task B" },
        ];
        axiosInstance.get.mockResolvedValueOnce({
            data: {
                data: mockTasks,
            },
        });

        await store.fetchProjectTasks(7);

        expect(axiosInstance.get).toHaveBeenCalledWith("project-tasks", {
            params: {
                project_id: 7,
            },
        });
        expect(store.tasks).toEqual(mockTasks);
        expect(store.loading).toBe(false);
    });

    it("fetchProjectTasks sets error on failure", async () => {
        axiosInstance.get.mockRejectedValueOnce({
            response: {
                data: {
                    message: "Failed to load tasks",
                },
            },
        });

        await store.fetchProjectTasks(7);

        expect(store.error).toBe("Failed to load tasks");
        expect(store.loading).toBe(false);
    });

    it("createTask posts data and returns created task", async () => {
        const payload = { title: "Write tests" };
        const createdTask = { id: 3, title: "Write tests" };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: "Task created",
                data: createdTask,
            },
        });

        const result = await store.createTask(payload);

        expect(axiosInstance.post).toHaveBeenCalledWith("project-tasks", payload);
        expect(result).toEqual(createdTask);
        expect(store.success).toBe("Task created");
        expect(store.loading).toBe(false);
    });

    it("createTask throws and sets error on failure", async () => {
        const mockError = {
            response: {
                data: {
                    message: "Task creation failed",
                },
            },
        };
        axiosInstance.post.mockRejectedValueOnce(mockError);

        await expect(store.createTask({ title: "Invalid task" })).rejects.toEqual(mockError);
        expect(store.error).toBe("Task creation failed");
        expect(store.loading).toBe(false);
    });

    it("updateTask posts method override, updates local task, and returns updated task", async () => {
        store.tasks = [{ id: 10, title: "Old title" }];
        const payload = { title: "New title" };
        const updatedTask = { id: 10, title: "New title" };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: "Task updated",
                data: updatedTask,
            },
        });

        const result = await store.updateTask(10, payload);

        expect(axiosInstance.post).toHaveBeenCalledWith("project-tasks/10", {
            ...payload,
            _method: "PUT",
        });
        expect(result).toEqual(updatedTask);
        expect(store.tasks[0]).toEqual(updatedTask);
        expect(store.success).toBe("Task updated");
        expect(store.loading).toBe(false);
    });

    it("updateTask throws and sets error on failure", async () => {
        const mockError = {
            response: {
                data: {
                    message: "Task update failed",
                },
            },
        };
        axiosInstance.post.mockRejectedValueOnce(mockError);

        await expect(store.updateTask(10, { title: "x" })).rejects.toEqual(mockError);
        expect(store.error).toBe("Task update failed");
        expect(store.loading).toBe(false);
    });

    it("deleteTask calls DELETE and sets success message", async () => {
        axiosInstance.delete.mockResolvedValueOnce({
            data: {
                message: "Task deleted",
            },
        });

        await store.deleteTask(15);

        expect(axiosInstance.delete).toHaveBeenCalledWith("project-tasks/15");
        expect(store.success).toBe("Task deleted");
        expect(store.loading).toBe(false);
    });

    it("deleteTask throws and sets error on failure", async () => {
        const mockError = {
            response: {
                data: {
                    message: "Task delete failed",
                },
            },
        };
        axiosInstance.delete.mockRejectedValueOnce(mockError);

        await expect(store.deleteTask(15)).rejects.toEqual(mockError);
        expect(store.error).toBe("Task delete failed");
        expect(store.loading).toBe(false);
    });

    it("updateTaskStatus updates task status and returns response data", async () => {
        store.tasks = [{ id: 44, status: "todo" }];
        const updatedTask = { id: 44, status: "done" };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                data: updatedTask,
            },
        });

        const result = await store.updateTaskStatus(44, "done");

        expect(axiosInstance.post).toHaveBeenCalledWith("project-tasks/44", {
            status: "done",
            _method: "PUT",
        });
        expect(store.tasks[0]).toEqual(updatedTask);
        expect(result).toEqual({ data: updatedTask });
    });

    it("updateTaskStatus throws and sets error on failure", async () => {
        const mockError = {
            response: {
                data: {
                    message: "Status update failed",
                },
            },
        };
        axiosInstance.post.mockRejectedValueOnce(mockError);

        await expect(store.updateTaskStatus(44, "done")).rejects.toEqual(mockError);
        expect(store.error).toBe("Status update failed");
    });

    it("fetchTaskComments returns task comments", async () => {
        const comments = [{ id: 1, comment: "Looks good" }];
        axiosInstance.get.mockResolvedValueOnce({
            data: {
                data: comments,
            },
        });

        const result = await store.fetchTaskComments(99);

        expect(axiosInstance.get).toHaveBeenCalledWith("project-tasks/99/comments");
        expect(result).toEqual(comments);
    });

    it("fetchTaskComments throws and sets error on failure", async () => {
        const mockError = {
            response: {
                data: {
                    message: "Comments fetch failed",
                },
            },
        };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await expect(store.fetchTaskComments(99)).rejects.toEqual(mockError);
        expect(store.error).toBe("Comments fetch failed");
    });

    it("createTaskComment posts comment and returns created comment", async () => {
        const payload = { comment: "Please update spec" };
        const comment = { id: 5, comment: "Please update spec" };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                data: comment,
            },
        });

        const result = await store.createTaskComment(31, payload);

        expect(axiosInstance.post).toHaveBeenCalledWith("project-tasks/31/comments", payload);
        expect(result).toEqual(comment);
    });

    it("createTaskComment throws and sets error on failure", async () => {
        const mockError = {
            response: {
                data: {
                    message: "Comment create failed",
                },
            },
        };
        axiosInstance.post.mockRejectedValueOnce(mockError);

        await expect(store.createTaskComment(31, { comment: "x" })).rejects.toEqual(mockError);
        expect(store.error).toBe("Comment create failed");
    });
});
