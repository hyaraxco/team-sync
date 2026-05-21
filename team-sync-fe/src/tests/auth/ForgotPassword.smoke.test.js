import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const { authStoreMock, authStoreRefs } = vi.hoisted(() => ({
    authStoreMock: {
        forgotPassword: vi.fn(),
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

import ForgotPassword from "@/views/auth/ForgotPassword.vue";

const factory = () =>
    mount(ForgotPassword, {
        global: {
            stubs: {
                RouterLink: {
                    props: ["to"],
                    template: "<a><slot /></a>",
                },
            },
        },
    });

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

describe("ForgotPassword smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        authStoreRefs.loading.value = false;
        authStoreRefs.error.value = null;
        authStoreRefs.success.value = null;
        authStoreMock.forgotPassword.mockResolvedValue(undefined);
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("displays reset password heading", () => {
        const wrapper = factory();
        expect(wrapper.text()).toContain("Atur Ulang Password");
    });

    it("submits forgot password request", async () => {
        const wrapper = factory();

        await wrapper.find("#email").setValue("employee@teamsync.test");
        await wrapper.find("form").trigger("submit");
        await flushAsync();

        expect(authStoreMock.forgotPassword).toHaveBeenCalledWith({
            email: "employee@teamsync.test",
        });
    });

    it("shows success state when request succeeds", () => {
        authStoreRefs.success.value = "Reset link sent";
        const wrapper = factory();

        expect(wrapper.text()).toContain("Cek Email Anda");
        expect(wrapper.text()).toContain("Return to sign in");
    });

    it("disables submit button while loading", () => {
        authStoreRefs.loading.value = true;
        const wrapper = factory();

        expect(wrapper.find('button[type="submit"]').attributes("disabled")).toBeDefined();
    });
});
