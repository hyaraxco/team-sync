import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";
import AuthLayout from "@/layouts/Auth.vue";

describe("Auth layout", () => {
    it("keeps the auth form without unsupported stats or a remote hero image", () => {
        const wrapper = mount(AuthLayout, {
            global: {
                stubs: {
                    ErrorBoundary: { template: "<slot />" },
                    RouterView: { template: '<div data-testid="auth-form">Login form</div>' },
                },
            },
        });

        expect(wrapper.text()).toContain("Login form");
        expect(wrapper.text()).not.toMatch(/\b(?:6\s+Modul HR|4\s+Role Pengguna|PPh 21|TER 2024)\b/i);
        expect(wrapper.find("img").exists()).toBe(false);
        expect(wrapper.find("aside").exists()).toBe(true);
    });
});
