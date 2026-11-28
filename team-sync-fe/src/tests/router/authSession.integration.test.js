import { beforeEach, describe, expect, it, vi } from "vitest";
import { createPinia, setActivePinia } from "pinia";
import { createMemoryHistory, createRouter } from "vue-router";
import Cookies from "js-cookie";

const { get, post } = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));

vi.mock("@/plugins/axios", () => ({ axiosInstance: { get, post } }));

import appRouter, { appRoutes, registerAuthGuard } from "@/router";
import { useAuthStore } from "@/stores/auth";

const push = vi.spyOn(appRouter, "push");

const user = { id: 1, permissions: ["dashboard-menu"] };
const credentials = { email: "employee@example.test", password: "test-password" };
const createTestRouter = () => {
    const router = createRouter({ history: createMemoryHistory(), routes: appRoutes });
    registerAuthGuard(router);
    push.mockImplementation((location) => router.push(location));
    return router;
};

beforeEach(() => {
    Cookies.remove("token");
    setActivePinia(createPinia());
    vi.resetAllMocks();
    post.mockResolvedValue({ data: { data: { token: "session-token" }, message: "Signed in" } });
    get.mockResolvedValue({ data: { data: user } });
});

describe("authentication session navigation", () => {
    it("reaches dashboard with one login after a protected-route redirect in the same document", async () => {
        const router = createTestRouter();
        const store = useAuthStore();
        await router.push("/");
        expect(router.currentRoute.value.name).toBe("login");
        expect(store.token).toBeUndefined();

        await store.login(credentials);

        expect(store.error).toBeNull();
        expect(router.currentRoute.value.name).toBe("admin.dashboard");
        expect(store.user).toEqual(user);
        expect(store.token).toBe("session-token");
        expect(Cookies.get("token")).toBe("session-token");
        expect(get).toHaveBeenCalledTimes(1);
    });

    it.each(["/admin/dashboard", "/auth/login"])("restores the session from its cookie on a fresh visit to %s", async (path) => {
        Cookies.set("token", "restored-token");
        const router = createTestRouter();
        const store = useAuthStore();

        await router.push(path);

        expect(router.currentRoute.value.name).toBe("admin.dashboard");
        expect(store.user).toEqual(user);
        expect(store.token).toBe("restored-token");
        expect(get).toHaveBeenCalledTimes(1);
    });

    it.each([
        { response: { status: 500, data: { message: "Profile unavailable" } } },
        new Error("Network unavailable"),
    ])("does not navigate after login when profile loading fails: %s", async (failure) => {
        const router = createTestRouter();
        const store = useAuthStore();
        await router.push("/");
        get.mockRejectedValue(failure);

        await store.login(credentials);

        expect(router.currentRoute.value.name).toBe("login");
        expect(push).not.toHaveBeenCalled();
        expect(store.user).toBeNull();
        expect(store.error).toBe(failure.response?.data.message || "Network error. Please check your connection.");
        expect(store.success).toBeNull();
        expect(store.loading).toBe(false);
        expect(get).toHaveBeenCalledTimes(1);
    });

    it("stops at login rather than looping when a restored profile request fails", async () => {
        Cookies.set("token", "restored-token");
        const router = createTestRouter();
        get.mockRejectedValue({ response: { status: 503, data: { message: "Profile unavailable" } } });

        await router.push("/admin/dashboard");

        expect(router.currentRoute.value.name).toBe("login");
        expect(get).toHaveBeenCalledTimes(2);
        expect(useAuthStore().error).toBe("Profile unavailable");
    });

    it("clears an expired session and leaves login available", async () => {
        Cookies.set("token", "expired-token");
        const router = createTestRouter();
        get.mockRejectedValue({ response: { status: 401, data: { message: "Unauthorized" } } });

        await router.push("/auth/login");

        expect(router.currentRoute.value.name).toBe("login");
        expect(useAuthStore().token).toBeUndefined();
        expect(useAuthStore().user).toBeNull();
        expect(Cookies.get("token")).toBeUndefined();
        expect(get).toHaveBeenCalledTimes(1);
    });
});
