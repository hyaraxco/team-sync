import { beforeEach, expect, it, vi } from "vitest";
import { createPinia, setActivePinia } from "pinia";
import Cookies from "js-cookie";

vi.mock("@/router", () => ({ default: { push: vi.fn(), replace: vi.fn() } }));

import { axiosInstance } from "@/plugins/axios";
import { useAuthStore } from "@/stores/auth";

beforeEach(() => {
    setActivePinia(createPinia());
    window.history.replaceState(null, "", "/auth/login");
    Cookies.remove("token");
});

it("clears reactive authentication state and cookie on an API 401 without a page reload", async () => {
    const store = useAuthStore();
    store.setToken("expired-token");
    store.user = { id: 1 };
    const failure = { response: { status: 401, data: { message: "Unauthorized" } } };

    await expect(axiosInstance.get("/me", { adapter: () => Promise.reject(failure) })).rejects.toBe(failure);

    expect(store.token).toBeUndefined();
    expect(store.user).toBeNull();
    expect(Cookies.get("token")).toBeUndefined();
});
