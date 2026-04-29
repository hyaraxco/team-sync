import { describe, expect, it, vi } from "vitest";
import { mount, RouterLinkStub } from "@vue/test-utils";
import Settings from "@/views/admin/Settings.vue";

const push = vi.fn();

vi.mock("vue-router", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        useRouter: () => ({
            push,
        }),
    };
});

vi.mock("@/helpers/permissionHelper", () => ({
    can: vi.fn().mockReturnValue(true),
}));

describe("Settings.vue smoke test", () => {
    const factory = () => {
        return mount(Settings, {
            global: {
                stubs: {
                    RouterLink: RouterLinkStub,
                },
            },
        });
    };

    it("renders 'Settings' heading", () => {
        const wrapper = factory();
        expect(wrapper.find("h1").text()).toBe("Settings");
        expect(wrapper.text()).toContain("Manage your organization's configuration and policies.");
    });

    it("renders settings sections", () => {
        const wrapper = factory();
        const sections = ["Payroll & Finance", "Attendance & Time", "Performance & Growth"];
        
        sections.forEach(section => {
            expect(wrapper.text()).toContain(section);
        });
    });

    it("renders links to existing config pages", () => {
        const wrapper = factory();
        
        const expectedLinks = [
            "Payroll Settings",
            "Attendance Policy",
            "Attendance Periods",
            "Holiday Calendar",
            "Review Cycles",
            "Outcome Rules",
            "Review Templates"
        ];

        expectedLinks.forEach(linkText => {
            expect(wrapper.text()).toContain(linkText);
        });

        const links = wrapper.findAllComponents(RouterLinkStub);
        
        const payrollLink = links.find(l => l.text().includes("Payroll Settings"));
        expect(payrollLink.props("to")).toEqual({ name: "admin.payroll.settings" });

        const attendanceLink = links.find(l => l.text().includes("Attendance Policy"));
        expect(attendanceLink.props("to")).toEqual({ name: "admin.attendance.settings" });
    });
});
