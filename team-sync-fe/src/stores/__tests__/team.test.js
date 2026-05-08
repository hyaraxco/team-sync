import { setActivePinia, createPinia } from "pinia";
import { describe, it, expect, beforeEach, vi } from "vitest";
import { useTeamStore } from "@/stores/team";
import { axiosInstance } from "@/plugins/axios";

vi.mock("@/plugins/axios", () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

describe("Team Store", () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useTeamStore();
        vi.clearAllMocks();
    });

    it("fetchTeams populates teams state", async () => {
        const params = { search: "engineering" };
        const mockResponse = {
            data: {
                data: [
                    { id: 1, name: "Engineering" },
                    { id: 2, name: "Product" },
                ],
            },
        };
        axiosInstance.get.mockResolvedValueOnce(mockResponse);

        await store.fetchTeams(params);

        expect(axiosInstance.get).toHaveBeenCalledWith("teams", { params });
        expect(store.teams).toEqual(mockResponse.data.data);
        expect(store.loading).toBe(false);
    });

    it("fetchTeam returns a single team by id", async () => {
        const mockTeam = { id: 10, name: "QA Team" };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: mockTeam } });

        const result = await store.fetchTeam(10);

        expect(axiosInstance.get).toHaveBeenCalledWith("teams/10");
        expect(result).toEqual(mockTeam);
    });

    it("createTeam calls POST with FormData and sets success message", async () => {
        const payload = { name: "Platform Team", responsibilities: ["Build", "Ship", "Maintain"] };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: "Team created",
                data: { id: 20, name: "Platform Team" },
            },
        });

        await store.createTeam(payload);

        expect(axiosInstance.post).toHaveBeenCalledWith("teams", expect.any(FormData));
        const formData = axiosInstance.post.mock.calls[0][1];
        expect(formData.get("name")).toBe("Platform Team");
        expect(formData.getAll("responsibilities[]")).toEqual(["Build", "Ship", "Maintain"]);
        expect(store.success).toBe("Team created");
        expect(store.loading).toBe(false);
    });

    it("updateTeam calls POST with FormData including PUT method override and sets success", async () => {
        const payload = { name: "Updated Team", responsibilities: ["Lead", "Deliver", "Review"] };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: "Team updated",
                data: { id: 21, name: "Updated Team" },
            },
        });

        await store.updateTeam(21, payload);

        expect(axiosInstance.post).toHaveBeenCalledWith("teams/21", expect.any(FormData));
        const formData = axiosInstance.post.mock.calls[0][1];
        expect(formData.get("name")).toBe("Updated Team");
        expect(formData.get("_method")).toBe("PUT");
        expect(store.success).toBe("Team updated");
    });

    it("deleteTeam calls DELETE and sets success message", async () => {
        axiosInstance.delete.mockResolvedValueOnce({
            data: {
                message: "Team deleted",
            },
        });

        await store.deleteTeam(30);

        expect(axiosInstance.delete).toHaveBeenCalledWith("teams/30");
        expect(store.success).toBe("Team deleted");
        expect(store.loading).toBe(false);
    });

    it("addMember calls POST and returns data", async () => {
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: "Member added",
                data: { team_id: 1, staff_member_id: 99 },
            },
        });

        const result = await store.addMember(1, 99);

        expect(axiosInstance.post).toHaveBeenCalledWith("/teams/1/add-member", {
            staff_member_id: 99,
        });
        expect(result).toEqual({ team_id: 1, staff_member_id: 99 });
        expect(store.success).toBe("Member added");
    });

    it("removeMember calls POST and returns data", async () => {
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: "Member removed",
                data: { team_id: 1, staff_member_id: 99 },
            },
        });

        const result = await store.removeMember(1, 99);

        expect(axiosInstance.post).toHaveBeenCalledWith("/teams/1/remove-member", {
            staff_member_id: 99,
        });
        expect(result).toEqual({ team_id: 1, staff_member_id: 99 });
        expect(store.success).toBe("Member removed");
    });

    it("sets error on failure", async () => {
        const mockError = {
            response: {
                status: 400,
                data: { message: "Invalid team request" },
            },
        };
        axiosInstance.get.mockRejectedValueOnce(mockError);

        await store.fetchTeams({});

        expect(store.error).toBe("Invalid team request");
        expect(store.loading).toBe(false);
    });
});
