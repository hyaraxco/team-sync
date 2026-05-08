import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const { authStoreMock, authStoreRefs, routerPushMock } = vi.hoisted(() => ({
    authStoreMock: {
        login: vi.fn(),
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
    },
    routerPushMock: vi.fn(),
}));

vi.mock("@/stores/auth", () => ({
    useAuthStore: () => authStoreMock,
}));

vi.mock("vue-router", () => ({
    useRouter: () => ({
        push: routerPushMock,
    }),
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

import Login from "@/views/auth/Login.vue";

const factory = () => mount(Login);

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

describe("Login smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        authStoreRefs.loading.value = false;
        authStoreRefs.error.value = null;
        authStoreMock.login.mockResolvedValue(undefined);
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("displays login heading", () => {
        const wrapper = factory();
        expect(wrapper.text()).toContain("Welcome back");
        expect(wrapper.text()).toContain("Sign in");
    });

    it("submits credentials via auth store", async () => {
        const wrapper = factory();

        await wrapper.find("#email").setValue("admin@teamsync.test");
        await wrapper.find("#password").setValue("secret123");
        await wrapper.find("#remember").setValue(true);

        await wrapper.find("form").trigger("submit");
        await flushAsync();

        expect(authStoreMock.login).toHaveBeenCalledWith({
            email: "admin@teamsync.test",
            password: "secret123",
            remember: true,
        });
    });

    it("clears password after unauthorized response", async () => {
        authStoreMock.login.mockImplementation(async () => {
            authStoreRefs.error.value = "Unauthorized";
        });

        const wrapper = factory();
        await wrapper.find("#email").setValue("admin@teamsync.test");
        await wrapper.find("#password").setValue("wrong-password");

        await wrapper.find("form").trigger("submit");
        await flushAsync();

        expect(wrapper.find("#password").element.value).toBe("");
    });

    it("disables submit button while loading", () => {
        authStoreRefs.loading.value = true;
        const wrapper = factory();
        expect(wrapper.find('[data-testid="login-submit"]').attributes("disabled")).toBeDefined();
    });
});
