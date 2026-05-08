import { beforeEach, describe, expect, it, vi } from "vitest";
import { createPinia, setActivePinia } from "pinia";

const {
    axiosPostMock,
    cookieState,
    getCookieMock,
    setCookieMock,
    removeCookieMock,
    routerPushMock,
    routerReplaceMock,
} = vi.hoisted(() => {
    const cookieState = { token: "seed-token" };

    return {
        axiosPostMock: vi.fn(),
        cookieState,
        getCookieMock: vi.fn((key) => (key === "token" ? cookieState.token : undefined)),
        setCookieMock: vi.fn((key, value) => {
            if (key === "token") {
                cookieState.token = value;
            }
        }),
        removeCookieMock: vi.fn((key) => {
            if (key === "token") {
                cookieState.token = undefined;
            }
        }),
        routerPushMock: vi.fn().mockResolvedValue(undefined),
        routerReplaceMock: vi.fn().mockResolvedValue(undefined),
    };
});

vi.mock("@/plugins/axios", () => ({
    axiosInstance: {
        post: axiosPostMock,
        defaults: {
            headers: {
                common: {},
            },
        },
    },
}));

vi.mock("js-cookie", () => ({
    default: {
        get: getCookieMock,
        set: setCookieMock,
        remove: removeCookieMock,
    },
}));

vi.mock("@/router", () => ({
    default: {
        push: routerPushMock,
        replace: routerReplaceMock,
    },
}));

import { useAuthStore } from "@/stores/auth";

describe("auth store logout", () => {
    beforeEach(() => {
        setActivePinia(createPinia());

        cookieState.token = "seed-token";

        axiosPostMock.mockReset();
        getCookieMock.mockClear();
        setCookieMock.mockClear();
        removeCookieMock.mockClear();
        routerPushMock.mockReset().mockResolvedValue(undefined);
        routerReplaceMock.mockReset().mockResolvedValue(undefined);
    });

    it("redirects to login and clears local session even when API logout is stuck", async () => {
        let resolveRequest;
        const pendingRequest = new Promise((resolve) => {
            resolveRequest = resolve;
        });

        axiosPostMock.mockReturnValue(pendingRequest);

        const store = useAuthStore();
        store.user = { id: 7, name: "Agung" };

        const logoutPromise = store.logout();

        const completion = Promise.race([
            logoutPromise.then(() => "resolved"),
            new Promise((resolve) => setTimeout(() => resolve("timeout"), 0)),
        ]);

        await expect(completion).resolves.toBe("resolved");

        expect(removeCookieMock).toHaveBeenCalledWith("token");
        expect(store.user).toBeNull();
        expect(store.error).toBeNull();
        expect(store.loading).toBe(false);
        expect(routerReplaceMock).toHaveBeenCalledWith({ name: "login" });
        expect(axiosPostMock).toHaveBeenCalledWith(
            "/logout",
            null,
            expect.objectContaining({
                timeout: 5000,
                headers: expect.objectContaining({
                    Authorization: "Bearer seed-token",
                }),
            }),
        );

        resolveRequest({ data: {} });
        await pendingRequest;
    });

    it("does not block logout when router navigation promise hangs", async () => {
        routerReplaceMock.mockReturnValue(new Promise(() => {}));
        axiosPostMock.mockResolvedValue({ data: {} });

        const store = useAuthStore();
        store.user = { id: 8, name: "Ayu" };

        const logoutPromise = store.logout();

        const completion = Promise.race([
            logoutPromise.then(() => "resolved"),
            new Promise((resolve) => setTimeout(() => resolve("timeout"), 0)),
        ]);

        await expect(completion).resolves.toBe("resolved");
        expect(removeCookieMock).toHaveBeenCalledWith("token");
        expect(store.user).toBeNull();
        expect(store.loading).toBe(false);
        expect(routerReplaceMock).toHaveBeenCalledWith({ name: "login" });
    });
});
