import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const {
    routeState,
    routerPushMock,
} = vi.hoisted(() => ({
    routeState: {
        name: "admin.staffMembers.success",
        query: {},
        params: {},
    },
    routerPushMock: vi.fn(),
}));

vi.mock("vue-router", () => ({
    useRoute: () => routeState,
    useRouter: () => ({
        push: routerPushMock,
    }),
}));

import StaffMemberSuccess from "@/views/admin/staff-member/StaffMemberSuccess.vue";

const factory = () => mount(StaffMemberSuccess);

describe("StaffMemberSuccess smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("displays success content", () => {
        const wrapper = factory();

        expect(wrapper.text()).toContain("Added Successfully!");
        expect(wrapper.text()).toContain("View All Staff Members");
        expect(wrapper.text()).toContain("Add Another Employee");
    });

    it("navigates to staff member list from primary action", async () => {
        const wrapper = factory();
        const button = wrapper.findAll("button").find((b) => b.text().includes("View All Staff Members"));

        await button.trigger("click");

        expect(routerPushMock).toHaveBeenCalledWith({
            name: "admin.staffMembers",
        });
    });

    it("navigates to create page from secondary action", async () => {
        const wrapper = factory();
        const button = wrapper.findAll("button").find((b) => b.text().includes("Add Another Employee"));

        await button.trigger("click");

        expect(routerPushMock).toHaveBeenCalledWith({
            name: "admin.staffMembers.create",
        });
    });
});
