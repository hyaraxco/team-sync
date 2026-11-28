import { afterEach, expect, it, vi } from "vitest";
import { createPinia, setActivePinia } from "pinia";
import Cookies from "js-cookie";

vi.mock("@/stores/auth", () => {
    throw new Error("Failed to load auth chunk");
});

import { axiosInstance } from "@/plugins/axios";

afterEach(() => {
    Cookies.remove("token");
    vi.restoreAllMocks();
});

it("clears the cookie, redirects, and preserves the API error when auth cannot load", async () => {
    setActivePinia(createPinia());
    Cookies.set("token", "expired-token");
    const location = { pathname: "/admin/dashboard", href: "/admin/dashboard" };
    vi.spyOn(window, "location", "get").mockReturnValue(location);
    const failure = { response: { status: 401, data: { message: "Unauthorized" } } };

    await expect(axiosInstance.get("/me", { adapter: () => Promise.reject(failure) })).rejects.toBe(failure);

    expect(Cookies.get("token")).toBeUndefined();
    expect(location.href).toBe("/auth/login");
});
