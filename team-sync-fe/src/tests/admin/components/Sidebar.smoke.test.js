import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { defineComponent, h, ref } from "vue";

const grantedPermissions = new Set();
const currentRoute = {
    name: "admin.dashboard",
};

const setPermissions = (permissions) => {
    grantedPermissions.clear();
    permissions.forEach((permission) => grantedPermissions.add(permission));
};

vi.mock("@/helpers/permissionHelper", () => ({
    can: (permission) => grantedPermissions.has(permission),
    canOneOf: (permissions) => permissions.some((permission) => grantedPermissions.has(permission)),
}));

const sidebarHolder = vi.hoisted(() => ({ refs: null }));

vi.mock("@/composables/useSidebar", () => {
    sidebarHolder.refs = {
        isOpen: ref(true),
        isCollapsed: ref(false),
        toggleCollapse: vi.fn(),
        closeMobile: vi.fn(),
    };

    return { useSidebar: () => sidebarHolder.refs };
});

import Sidebar from "@/components/admin/Sidebar.vue";

const RouterLinkStub = defineComponent({
    name: "RouterLink",
    props: ["to", "custom"],
    setup(props, { slots }) {
        return () => {
            const href = typeof props.to === "string" ? props.to : `/${props.to?.name || ""}`;
            const content = slots.default?.({ href, navigate: () => {} });

            return props.custom ? content : h("a", { "data-route-name": props.to?.name }, content);
        };
    },
});

const factory = () =>
    mount(Sidebar, {
        global: {
            mocks: {
                $route: currentRoute,
            },
            stubs: {
                RouterLink: RouterLinkStub,
            },
        },
    });

const currentPersonalLinks = (wrapper) =>
    wrapper.findAll('[data-testid="sidebar-section-personal"] a[aria-current="page"]');


describe("Sidebar smoke", () => {
    beforeEach(() => {
        currentRoute.name = "admin.dashboard";
        setPermissions([]);
        sidebarHolder.refs.isOpen.value = true;
        sidebarHolder.refs.isCollapsed.value = false;
    });

    it.each([
        ["staffMember.attendance.my-attendances", "My Attendance"],
        ["staffMember.attendance.clock", "My Attendance"],
        ["staffMember.payroll", "My Payroll"],
        ["staffMember.payroll.detail", "My Payroll"],
        ["staffMember.profile", "My Profile"],
        ["staffMember.profile.edit", "My Profile"],
        ["staffMember.team", "My Team"],
    ])("marks only %s as the current personal destination", (routeName, label) => {
        setPermissions([
            "dashboard-menu",
            "attendance-my-attendances",
            "attendance-check-in",
            "attendance-check-out",
            "payslip-view",
            "profile-menu",
            "team-view",
        ]);
        currentRoute.name = routeName;

        const wrapper = factory();
        const currentLinks = currentPersonalLinks(wrapper);

        expect(currentLinks).toHaveLength(1);
        expect(currentLinks[0].text()).toContain(label);
        expect(currentLinks[0].classes()).toContain("nav-link-active");
    });

    it("does not mark a personal destination current on an unrelated route", () => {
        setPermissions(["attendance-my-attendances", "payslip-view", "profile-menu", "team-view"]);

        expect(currentPersonalLinks(factory())).toHaveLength(0);
    });

    it("hides payroll menu for manager-style permissions", () => {
        setPermissions(["dashboard-menu", "dashboard-view", "team-menu", "staff-member-menu", "attendance-menu"]);

        const wrapper = factory();

        expect(wrapper.text()).not.toContain("Payroll");
    });

    it("shows payroll menu for HR or Finance when payroll-menu is granted", () => {
        setPermissions(["payroll-menu"]);

        const wrapper = factory();

        expect(wrapper.text()).toContain("Payroll");
        expect(wrapper.text()).toContain("Payroll Adjustments");
    });

    it("shows My Payroll when payslip-view is granted", () => {
        setPermissions(["payslip-view"]);

        const wrapper = factory();

        expect(wrapper.text()).toContain("My Payroll");
        expect(wrapper.text()).not.toContain("Clock In/Out");
    });

    it("shows mixed admin and self-service navigation for finance, hr, and manager", () => {
        setPermissions([
            "dashboard-menu",
            "profile-menu",
            "attendance-my-attendances",
            "attendance-check-in",
            "attendance-check-out",
            "leave-request-menu",
            "leave-request-create",
            "leave-request-my-requests",
            "payslip-view",
            "payroll-menu",
        ]);

        const financeWrapper = factory();

        expect(financeWrapper.get('[data-testid="sidebar-section-general"]').text()).toContain("GENERAL");
        expect(financeWrapper.get('[data-testid="sidebar-section-personal"]').text()).toContain("PERSONAL");
        expect(financeWrapper.text()).toContain("Payroll");
        expect(financeWrapper.text()).toContain("My Payroll");
        expect(financeWrapper.text()).toContain("My Attendance");
        expect(financeWrapper.text()).toContain("My Profile");
    });

    it("hides Personal section when user has no personal workspace permissions", () => {
        setPermissions(["dashboard-menu", "team-menu", "staff-member-menu"]);

        const wrapper = factory();

        expect(wrapper.find('[data-testid="sidebar-section-personal"]').exists()).toBe(false);
    });

    it("keeps payroll menu hidden for employee-style permissions", () => {
        setPermissions([
            "dashboard-menu",
            "dashboard-view",
            "attendance-my-attendances",
            "attendance-check-in",
            "attendance-check-out",
            "leave-request-create",
        ]);

        const wrapper = factory();

        expect(wrapper.text()).not.toContain("Payroll");
    });

    it("does not render placeholder menu entries", () => {
        setPermissions(["dashboard-menu", "profile-menu"]);

        const wrapper = factory();

        expect(wrapper.text()).not.toContain("Reports");
        expect(wrapper.find('a[href="#"]').exists()).toBe(false);
    });

    it("marks the drawer as an open dialog only while it is open", async () => {
        setPermissions(["dashboard-menu"]);

        const wrapper = factory();
        const drawer = wrapper.get("#sidebar");

        expect(drawer.attributes("role")).toBe("dialog");
        expect(drawer.attributes("aria-modal")).toBe("true");
        expect(drawer.attributes("aria-label")).toBe("Main sidebar");

        sidebarHolder.refs.isOpen.value = false;
        await wrapper.vm.$nextTick();

        expect(wrapper.get("#sidebar").attributes("role")).toBeUndefined();
        expect(wrapper.get("#sidebar").attributes("aria-modal")).toBeUndefined();
    });
});
