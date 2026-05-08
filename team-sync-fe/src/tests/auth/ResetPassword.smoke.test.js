import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const { routeState, authStoreMock, authStoreRefs } = vi.hoisted(() => ({
    routeState: {
        query: {
            token: "valid-reset-token",
            email: "employee@teamsync.test",
        },
    },
    authStoreMock: {
        resetPassword: vi.fn(),
    },
    authStoreRefs: {
        loading: {
            __v_isRef: true,
            value: false,
        },
        error: {
            __v_isRef: true,
            value: null,
        },
        success: {
            __v_isRef: true,
            value: null,
        },
    },
}));

vi.mock("@/stores/auth", () => ({
    useAuthStore: () => authStoreMock,
}));

vi.mock("vue-router", () => ({
    useRoute: () => routeState,
    RouterLink: {
        name: "RouterLink",
        props: ["to"],
        template: "<a><slot /></a>",
    },
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        storeToRefs: (store) => {
            if (store === authStoreMock) {
                return authStoreRefs;
            }
            return {};
        },
    };
});

import ResetPassword from "@/views/auth/ResetPassword.vue";

const factory = () => mount(ResetPassword);

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

describe("ResetPassword smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        routeState.query = {
            token: "valid-reset-token",
            email: "employee@teamsync.test",
        };
        authStoreRefs.loading.value = false;
        authStoreRefs.error.value = null;
        authStoreRefs.success.value = null;
        authStoreMock.resetPassword.mockResolvedValue(undefined);
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("shows invalid token state when token is missing", () => {
        routeState.query = {
            email: "employee@teamsync.test",
        };

        const wrapper = factory();
        expect(wrapper.text()).toContain("Invalid reset link");
        expect(wrapper.text()).toContain("Request new link");
    });

    it("submits reset password payload", async () => {
        const wrapper = factory();

        await wrapper.find("#password").setValue("newPassword123");
        await wrapper.find("#password_confirmation").setValue("newPassword123");
        await wrapper.find("form").trigger("submit");
        await flushAsync();

        expect(authStoreMock.resetPassword).toHaveBeenCalledWith({
            email: "employee@teamsync.test",
            token: "valid-reset-token",
            password: "newPassword123",
            password_confirmation: "newPassword123",
        });
    });

    it("shows success state when reset succeeds", () => {
        authStoreRefs.success.value = "Password reset success";
        const wrapper = factory();

        expect(wrapper.text()).toContain("Password updated");
        expect(wrapper.text()).toContain("Return to sign in");
    });

    it("disables submit button while loading", () => {
        authStoreRefs.loading.value = true;
        const wrapper = factory();

        expect(wrapper.find('button[type="submit"]').attributes("disabled")).toBeDefined();
    });
});
