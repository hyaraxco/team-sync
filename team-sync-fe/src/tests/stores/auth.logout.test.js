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

const settle = () => new Promise((resolve) => setTimeout(resolve, 0));

describe("auth store logout", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        window.history.replaceState(null, "", "/auth/login");

        cookieState.token = "seed-token";

        axiosPostMock.mockReset();
        getCookieMock.mockClear();
        setCookieMock.mockClear();
        removeCookieMock.mockClear();
        routerPushMock.mockReset().mockResolvedValue(undefined);
        routerReplaceMock.mockReset().mockResolvedValue(undefined);
    });

    it("revokes the server token before clearing the cookie, navigating or resetting state", async () => {
        // window.location.replace is read-only in jsdom; redefine the whole
        // location object so hard navigation can be observed and cannot escape.
        const windowReplaceMock = vi.fn();
        vi.spyOn(window, "location", "get").mockReturnValue({
            ...window.location,
            pathname: "/auth/admin",
            replace: windowReplaceMock,
        });

        const callOrder = [];
        let releaseRevoke;
        const pendingRevoke = new Promise((resolve) => {
            releaseRevoke = resolve;
        });

        axiosPostMock.mockImplementation(() => {
            callOrder.push("revoke");
            return pendingRevoke;
        });
        removeCookieMock.mockImplementation((key) => {
            callOrder.push("removeCookie");
            if (key === "token") {
                cookieState.token = undefined;
            }
        });
        routerReplaceMock.mockImplementation(() => {
            callOrder.push("spaNavigation");
            return Promise.resolve();
        });
        windowReplaceMock.mockImplementation(() => {
            callOrder.push("hardNavigation");
        });

        const store = useAuthStore();
        store.user = { id: 7, name: "Agung" };

        const logoutPromise = store.logout();
        await settle();

        // Revoke is in flight; nothing destructive may have happened yet.
        expect(axiosPostMock).toHaveBeenCalledTimes(1);
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
        expect(callOrder).toEqual(["revoke"]);
        expect(removeCookieMock).not.toHaveBeenCalled();
        expect(routerReplaceMock).not.toHaveBeenCalled();
        expect(windowReplaceMock).not.toHaveBeenCalled();
        expect(store.user).toEqual({ id: 7, name: "Agung" });

        releaseRevoke({ data: {} });
        await logoutPromise;

        expect(callOrder.slice(0, 2)).toEqual(["revoke", "removeCookie"]);
        // A hard redirect is allowed as a last-resort fallback only after revoke.
        expect(windowReplaceMock).toHaveBeenCalledWith("/auth/login");
        expect(store.user).toBeNull();
        expect(store.error).toBeNull();
        expect(store.loading).toBe(false);
        expect(cookieState.token).toBeUndefined();
    });

    it("still clears the local session and navigates when the revoke request fails", async () => {
        axiosPostMock.mockRejectedValueOnce(new Error("network down"));

        const store = useAuthStore();
        store.user = { id: 8, name: "Ayu" };

        await expect(store.logout()).resolves.toBeUndefined();

        expect(cookieState.token).toBeUndefined();
        expect(store.user).toBeNull();
        expect(store.loading).toBe(false);
        expect(routerReplaceMock).toHaveBeenCalledWith({ name: "login" });
    });

    it("does not call the API when there is no stored token", async () => {
        cookieState.token = undefined;

        const store = useAuthStore();
        store.user = { id: 9, name: "Tasyia" };

        await store.logout();

        expect(axiosPostMock).not.toHaveBeenCalled();
        expect(store.user).toBeNull();
        expect(routerReplaceMock).toHaveBeenCalledWith({ name: "login" });
    });

    it("does not block logout when router navigation promise hangs", async () => {
        routerReplaceMock.mockReturnValue(new Promise(() => {}));
        axiosPostMock.mockResolvedValue({ data: {} });

        const store = useAuthStore();
        store.user = { id: 10, name: "Bima" };

        await expect(store.logout()).resolves.toBeUndefined();

        expect(cookieState.token).toBeUndefined();
        expect(store.user).toBeNull();
        expect(store.loading).toBe(false);
        expect(routerReplaceMock).toHaveBeenCalledWith({ name: "login" });
    });
});
