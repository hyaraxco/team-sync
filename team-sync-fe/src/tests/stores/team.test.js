import { beforeEach, describe, expect, it, vi } from "vitest";
import { createPinia, setActivePinia } from "pinia";

const { axiosPostMock } = vi.hoisted(() => ({
    axiosPostMock: vi.fn(),
}));

vi.mock("@/plugins/axios", () => ({
    axiosInstance: {
        post: axiosPostMock,
        get: vi.fn(),
        delete: vi.fn(),
        defaults: {
            headers: {
                common: {},
            },
        },
    },
}));

import { useTeamStore } from "@/stores/team";

describe("team store createTeam and updateTeam state resets", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        axiosPostMock.mockReset();
    });

    it("resets stale success and error on createTeam failure to prevent phantom navigation", async () => {
        const store = useTeamStore();
        store.success = "Stale previous success";
        store.error = { name: ["Stale previous error"] };

        axiosPostMock.mockRejectedValueOnce({
            response: {
                status: 422,
                data: {
                    errors: { name: ["The name field is required."] },
                },
            },
        });

        await store.createTeam({ name: "" });

        expect(store.success).toBeNull();
        expect(store.error).toEqual({ name: ["The name field is required."] });
        expect(store.loading).toBe(false);
    });

    it("resets stale error and sets success on successful createTeam", async () => {
        const store = useTeamStore();
        store.error = { name: ["Stale previous error"] };
        store.success = null;

        axiosPostMock.mockResolvedValueOnce({
            data: {
                message: "Team created successfully",
                data: { id: 10, name: "Platform Engineering" },
            },
        });

        await store.createTeam({ name: "Platform Engineering" });

        expect(store.success).toBe("Team created successfully");
        expect(store.error).toBeNull();
        expect(store.loading).toBe(false);
    });

    it("resets stale success and error on updateTeam failure to prevent phantom navigation", async () => {
        const store = useTeamStore();
        store.success = "Stale previous success";
        store.error = { name: ["Stale previous error"] };

        axiosPostMock.mockRejectedValueOnce({
            response: {
                status: 422,
                data: {
                    errors: { name: ["The name has already been taken."] },
                },
            },
        });

        await store.updateTeam(5, { name: "Duplicate Team" });

        expect(store.success).toBeNull();
        expect(store.error).toEqual({ name: ["The name has already been taken."] });
        expect(store.loading).toBe(false);
    });

    it("resets stale error and sets success on successful updateTeam", async () => {
        const store = useTeamStore();
        store.error = { name: ["Stale previous error"] };
        store.success = null;

        axiosPostMock.mockResolvedValueOnce({
            data: {
                message: "Team updated successfully",
                data: { id: 5, name: "Updated Team" },
            },
        });

        await store.updateTeam(5, { name: "Updated Team" });

        expect(store.success).toBe("Team updated successfully");
        expect(store.error).toBeNull();
        expect(store.loading).toBe(false);
    });
});
