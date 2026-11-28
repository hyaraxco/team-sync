import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { defineComponent, h, ref } from "vue";

const grantedPermissions = new Set();
const routeRef = ref({ name: "admin.dashboard", query: {} });

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

vi.mock("vue-router", async () => {
    const actual = await vi.importActual("vue-router");
    return {
        ...actual,
        useRoute: () => routeRef.value,
    };
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
            stubs: {
                RouterLink: RouterLinkStub,
            },
        },
    });

const currentPersonalLinks = (wrapper) =>
    wrapper.findAll('[data-testid="sidebar-section-personal"] a[aria-current="page"]');

const allPermissions = {
    staff: [
        "dashboard-menu",
        "dashboard-view",
        "dashboard-self-view",
        "team-view",
        "project-menu",
        "project-list",
        "task-menu",
        "task-list",
        "task-edit",
        "profile-menu",
        "attendance-my-attendances",
        "attendance-my-statistics",
        "attendance-last-attendance",
        "attendance-check-in",
        "attendance-check-out",
        "attendance-correction-create",
        "leave-request-menu",
        "leave-request-create",
        "leave-request-my-requests",
        "payslip-view",
        "performance-menu",
        "review-self-submit",
        "goal-create-own",
        "meeting-menu",
        "meeting-list",
        "overtime-create",
    ],
    manager: [
        "dashboard-menu",
        "dashboard-view",
        "dashboard-team-view",
        "team-menu",
        "team-statistic",
        "team-list",
        "team-create",
        "team-edit",
        "team-delete",
        "team-view",
        "project-menu",
        "project-statistic",
        "project-list",
        "project-create",
        "project-edit",
        "project-delete",
        "task-menu",
        "task-list",
        "attendance-menu",
        "attendance-list",
        "attendance-correction-list",
        "attendance-correction-approve",
        "leave-request-list",
        "leave-request-approve",
        "overtime-list",
        "overtime-create",
        "overtime-approve",
        "analytics-menu",
        "analytics-team-view",
        "analytics-performance-view",
        "analytics-project-view",
        "profile-menu",
        "attendance-my-attendances",
        "attendance-my-statistics",
        "attendance-last-attendance",
        "attendance-check-in",
        "attendance-check-out",
        "attendance-correction-create",
        "leave-request-menu",
        "leave-request-create",
        "leave-request-my-requests",
        "payslip-view",
        "performance-menu",
        "review-self-submit",
        "review-manager-submit",
        "goal-create-own",
        "goal-assign-team",
        "feedback-give",
        "performance-analytics-view",
        "meeting-menu",
        "meeting-list",
    ],
    hr: [
        "dashboard-menu",
        "dashboard-view",
        "dashboard-hr-view",
        "team-menu",
        "team-statistic",
        "team-list",
        "team-create",
        "team-edit",
        "team-delete",
        "team-view",
        "staff-member-menu",
        "staff-member-list",
        "staff-member-create",
        "staff-member-edit",
        "staff-member-delete",
        "project-menu",
        "project-list",
        "task-menu",
        "task-list",
        "attendance-menu",
        "attendance-list",
        "attendance-correction-list",
        "attendance-correction-approve",
        "leave-request-menu",
        "leave-request-list",
        "leave-request-create",
        "leave-request-my-requests",
        "leave-request-approve",
        "overtime-list",
        "overtime-create",
        "overtime-approve",
        "analytics-menu",
        "analytics-view",
        "analytics-export",
        "analytics-hr-view",
        "analytics-workforce-view",
        "analytics-attendance-view",
        "analytics-leave-view",
        "analytics-performance-view",
        "analytics-project-view",
        "profile-menu",
        "attendance-my-attendances",
        "attendance-my-statistics",
        "attendance-last-attendance",
        "attendance-check-in",
        "attendance-check-out",
        "attendance-correction-create",
        "payslip-view",
        "performance-menu",
        "review-self-submit",
        "review-cycle-manage",
        "review-calibrate",
        "goal-create-own",
        "goal-assign-team",
        "feedback-give",
        "performance-analytics-view",
        "meeting-menu",
        "meeting-list",
        "meeting-create",
        "settings-hr-manage",
        "payroll-readiness-view",
        "thr-list",
    ],
    finance: [
        "dashboard-menu",
        "dashboard-view",
        "dashboard-finance-view",
        "project-menu",
        "project-list",
        "task-menu",
        "task-list",
        "task-edit",
        "payroll-menu",
        "payroll-list",
        "payroll-create",
        "payroll-edit",
        "payroll-delete",
        "payroll-process",
        "payroll-statistics",
        "payroll-readiness-view",
        "thr-list",
        "thr-generate",
        "thr-approve",
        "thr-process",
        "analytics-menu",
        "analytics-view",
        "analytics-export",
        "analytics-finance-view",
        "overtime-list",
        "profile-menu",
        "attendance-my-attendances",
        "attendance-my-statistics",
        "attendance-last-attendance",
        "attendance-check-in",
        "attendance-check-out",
        "attendance-correction-create",
        "leave-request-menu",
        "leave-request-create",
        "leave-request-my-requests",
        "payslip-view",
        "performance-menu",
        "review-self-submit",
        "goal-create-own",
        "meeting-menu",
        "meeting-list",
        "settings-finance-manage",
    ],
    superadmin: [
        "dashboard-menu",
        "dashboard-view",
        "dashboard-hr-view",
        "dashboard-finance-view",
        "dashboard-team-view",
        "dashboard-self-view",
        "staff-member-menu",
        "staff-member-list",
        "staff-member-create",
        "staff-member-edit",
        "staff-member-delete",
        "team-menu",
        "team-statistic",
        "team-list",
        "team-create",
        "team-edit",
        "team-delete",
        "team-view",
        "project-menu",
        "project-statistic",
        "project-list",
        "project-create",
        "project-edit",
        "project-delete",
        "task-menu",
        "task-list",
        "task-edit",
        "attendance-menu",
        "attendance-list",
        "attendance-correction-list",
        "attendance-correction-approve",
        "attendance-my-attendances",
        "attendance-my-statistics",
        "attendance-last-attendance",
        "attendance-check-in",
        "attendance-check-out",
        "attendance-correction-create",
        "leave-request-menu",
        "leave-request-list",
        "leave-request-create",
        "leave-request-my-requests",
        "leave-request-approve",
        "overtime-list",
        "overtime-create",
        "overtime-approve",
        "payroll-menu",
        "payroll-list",
        "payroll-create",
        "payroll-edit",
        "payroll-delete",
        "payroll-process",
        "payroll-statistics",
        "payroll-readiness-view",
        "thr-list",
        "thr-generate",
        "thr-approve",
        "thr-process",
        "analytics-menu",
        "analytics-view",
        "analytics-export",
        "analytics-hr-view",
        "analytics-finance-view",
        "analytics-workforce-view",
        "analytics-attendance-view",
        "analytics-leave-view",
        "analytics-performance-view",
        "analytics-project-view",
        "analytics-team-view",
        "profile-menu",
        "payslip-view",
        "performance-menu",
        "review-self-submit",
        "review-manager-submit",
        "review-cycle-manage",
        "review-calibrate",
        "goal-create-own",
        "goal-assign-team",
        "feedback-give",
        "performance-analytics-view",
        "meeting-menu",
        "meeting-list",
        "meeting-create",
        "settings-hr-manage",
        "settings-finance-manage",
        "settings-system-manage",
    ],
};


describe("Sidebar smoke", () => {
    beforeEach(() => {
        routeRef.value = { name: "admin.dashboard", query: {} };
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
        routeRef.value = { name: routeName, query: {} };

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

    it("keeps link labels in the DOM while collapsed so icons have accessible names", () => {
        setPermissions(["dashboard-menu", "project-menu"]);
        sidebarHolder.refs.isCollapsed.value = true;

        const wrapper = factory();
        const links = wrapper.findAll("nav .nav-link, nav .nav-link-active");

        expect(links.length).toBeGreaterThan(0);
        links.forEach((link) => {
            const label = link.find(":scope > span");

            expect(label.exists()).toBe(true);
            expect(label.text().length).toBeGreaterThan(0);
            expect(label.attributes("style") ?? "").not.toContain("display: none");
        });

        expect(wrapper.get('nav a[data-route-name="admin.dashboard"]').text()).toContain("Dashboard");
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

    describe("per-role destination visibility", () => {
        it("shows correct destinations for Staff role", () => {
            setPermissions(allPermissions.staff);
            const wrapper = factory();
            const generalText = wrapper.get('[data-testid="sidebar-section-general"]').text();
            const text = wrapper.text();

            expect(generalText).toContain("Dashboard");
            expect(generalText).toContain("Projects");
            expect(generalText).toContain("Meetings");
            expect(generalText).not.toContain("Employees");
            expect(generalText).not.toContain("Our Teams");
            expect(generalText).not.toContain("Attendance");
            expect(generalText).not.toContain("Payroll");
            expect(generalText).not.toContain("Analytics");
            expect(text).toContain("My Reviews");
            expect(text).toContain("My Goals");
            expect(text).toContain("Feedback");
            expect(text).not.toContain("Team Reviews");
            expect(text).not.toContain("Pending Calibration");
            expect(text).not.toContain("Review Cycles");
            expect(text).not.toContain("Settings");
            expect(text).toContain("My Attendance");
            expect(text).toContain("My Team");
            expect(text).toContain("My Payroll");
            expect(text).toContain("My Profile");
        });

        it("shows correct destinations for Manager role", () => {
            setPermissions(allPermissions.manager);
            const wrapper = factory();
            const text = wrapper.text();

            expect(text).toContain("Dashboard");
            expect(text).toContain("Projects");
            expect(text).toContain("Our Teams");
            expect(text).toContain("Meetings");
            expect(text).toContain("Attendance");
            expect(text).toContain("Analytics");
            expect(text).not.toContain("Employees");
            expect(text).toContain("Team Reviews");
            expect(text).toContain("Team Goals");
            expect(text).toContain("My Reviews");
            expect(text).toContain("My Goals");
            expect(text).toContain("Feedback");
            expect(text).not.toContain("Pending Calibration");
            expect(text).not.toContain("Review Cycles");
            expect(text).not.toContain("Settings");
            expect(text).toContain("My Attendance");
            expect(text).toContain("My Team");
            expect(text).toContain("My Payroll");
            expect(text).toContain("My Profile");
        });

        it("shows correct destinations for HR role", () => {
            setPermissions(allPermissions.hr);
            const wrapper = factory();
            const text = wrapper.text();

            expect(text).toContain("Dashboard");
            expect(text).toContain("Projects");
            expect(text).toContain("Employees");
            expect(text).toContain("Our Teams");
            expect(text).toContain("Meetings");
            expect(text).toContain("Attendance");
            expect(text).toContain("Analytics");
            expect(text).toContain("Pending Calibration");
            expect(text).toContain("Review Cycles");
            expect(text).toContain("Outcome Rules");
            expect(text).toContain("Review Templates");
            expect(text).toContain("Team Goals");
            expect(text).toContain("My Reviews");
            expect(text).toContain("My Goals");
            expect(text).toContain("Feedback");
            expect(text).not.toContain("Team Reviews");
            expect(text).toContain("Settings");
            expect(text).toContain("My Attendance");
            expect(text).toContain("My Team");
            expect(text).toContain("My Payroll");
            expect(text).toContain("My Profile");
        });

        it("shows correct destinations for Finance role", () => {
            setPermissions(allPermissions.finance);
            const wrapper = factory();
            const text = wrapper.text();

            expect(text).toContain("Dashboard");
            expect(text).toContain("Projects");
            expect(text).toContain("Payroll");
            expect(text).toContain("Payroll Adjustments");
            expect(text).toContain("Analytics");
            expect(text).not.toContain("Employees");
            expect(text).not.toContain("Our Teams");
            expect(text).not.toContain("Team Reviews");
            expect(text).not.toContain("Pending Calibration");
            expect(text).not.toContain("Review Cycles");
            expect(text).toContain("Settings");
            expect(text).toContain("My Reviews");
            expect(text).toContain("My Goals");
            expect(text).toContain("My Attendance");
            expect(text).toContain("My Payroll");
            expect(text).toContain("My Profile");
            expect(text).not.toContain("My Team");
        });

        it("shows all destinations for Superadmin role", () => {
            setPermissions(allPermissions.superadmin);
            const wrapper = factory();
            const text = wrapper.text();

            expect(text).toContain("Dashboard");
            expect(text).toContain("Projects");
            expect(text).toContain("Employees");
            expect(text).toContain("Our Teams");
            expect(text).toContain("Meetings");
            expect(text).toContain("Attendance");
            expect(text).toContain("Payroll");
            expect(text).toContain("Payroll Adjustments");
            expect(text).toContain("Analytics");
            expect(text).toContain("Team Reviews");
            expect(text).toContain("Pending Calibration");
            expect(text).toContain("Review Cycles");
            expect(text).toContain("Outcome Rules");
            expect(text).toContain("Review Templates");
            expect(text).toContain("Team Goals");
            expect(text).toContain("My Reviews");
            expect(text).toContain("My Goals");
            expect(text).toContain("Feedback");
            expect(text).toContain("Settings");
            expect(text).toContain("My Attendance");
            expect(text).toContain("My Team");
            expect(text).toContain("My Payroll");
            expect(text).toContain("My Profile");
        });

        it("marks only the active route for Payroll but not Payroll Adjustments", () => {
            setPermissions(["payroll-menu"]);
            routeRef.value = { name: "admin.payroll.dashboard", query: {} };

            const wrapper = factory();
            const activeLinks = wrapper.findAll('[data-testid="sidebar-section-general"] a.nav-link-active');

            expect(activeLinks).toHaveLength(1);
            expect(activeLinks[0].text()).toContain("Payroll");
            expect(activeLinks[0].text()).not.toContain("Adjustments");
        });

        it("marks Payroll Adjustments as active on its own route", () => {
            setPermissions(["payroll-menu"]);
            routeRef.value = { name: "admin.payroll.adjustments", query: {} };

            const wrapper = factory();
            const activeLinks = wrapper.findAll('[data-testid="sidebar-section-general"] a.nav-link-active');

            expect(activeLinks).toHaveLength(1);
            expect(activeLinks[0].text()).toContain("Payroll Adjustments");
        });

        it("marks nested project routes as active on the Projects link", () => {
            setPermissions(["project-menu"]);
            routeRef.value = { name: "admin.projects.detail", query: {} };

            const wrapper = factory();
            const activeLinks = wrapper.findAll("a.nav-link-active");

            expect(activeLinks).toHaveLength(1);
            expect(activeLinks[0].text()).toContain("Projects");
        });
    });
});
