import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const {
    authStoreMock,
    authStoreRefs,
    staffMemberStoreMock,
    routerPushMock,
} = vi.hoisted(() => ({
    authStoreMock: {
        updateProfile: vi.fn(),
        checkAuth: vi.fn(),
    },
    authStoreRefs: {
        user: {
            __v_isRef: true,
            value: {
                name: "Agung Ramadhan",
                email: "agung@teamsync.test",
                profile_photo: "",
            },
        },
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
    staffMemberStoreMock: {},
    routerPushMock: vi.fn(),
}));

vi.mock("@/stores/auth", () => ({
    useAuthStore: () => authStoreMock,
}));

vi.mock("@/stores/staffMember", () => ({
    useStaffMemberStore: () => staffMemberStoreMock,
}));

vi.mock("vue-router", () => ({
    useRouter: () => ({
        push: routerPushMock,
    }),
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

import StaffMemberProfileEdit from "@/views/staff-member/StaffMemberProfileEdit.vue";

const factory = () => mount(StaffMemberProfileEdit);

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

describe("StaffMemberProfileEdit smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        authStoreRefs.user.value = {
            name: "Agung Ramadhan",
            email: "agung@teamsync.test",
            profile_photo: "",
        };
        authStoreRefs.loading.value = false;
        authStoreRefs.error.value = null;
        authStoreRefs.success.value = null;
        authStoreMock.checkAuth.mockResolvedValue(undefined);
        authStoreMock.updateProfile.mockResolvedValue(undefined);
    });

    it("renders without crashing", async () => {
        const wrapper = factory();
        await flushAsync();
        expect(wrapper.exists()).toBe(true);
    });

    it("prefills form from authenticated user", async () => {
        const wrapper = factory();
        await flushAsync();

        expect(wrapper.find("#name").element.value).toBe("Agung Ramadhan");
        expect(wrapper.find("#email").element.value).toBe("agung@teamsync.test");
    });

    it("calls checkAuth when user is missing", async () => {
        authStoreRefs.user.value = null;
        factory();
        await flushAsync();

        expect(authStoreMock.checkAuth).toHaveBeenCalled();
    });

    it("submits updated profile data", async () => {
        const wrapper = factory();
        await flushAsync();

        await wrapper.find("#name").setValue("Agung Updated");
        await wrapper.find("#email").setValue("agung.updated@teamsync.test");
        await wrapper.find("#password").setValue("newPassword123");
        await wrapper.find("#password_confirmation").setValue("newPassword123");

        await wrapper.find("form").trigger("submit");
        await flushAsync();

        expect(authStoreMock.updateProfile).toHaveBeenCalledWith(
            expect.objectContaining({
                name: "Agung Updated",
                email: "agung.updated@teamsync.test",
                password: "newPassword123",
                password_confirmation: "newPassword123",
            }),
        );
    });

    it("disables submit button while loading", async () => {
        authStoreRefs.loading.value = true;
        const wrapper = factory();
        await flushAsync();

        expect(wrapper.find('button[type="submit"]').attributes("disabled")).toBeDefined();
    });
});
