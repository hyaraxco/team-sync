import { describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const authState = vi.hoisted(() => ({ roles: [] }));

vi.mock("@/stores/auth", () => ({
    useAuthStore: () => ({ user: { roles: authState.roles } }),
}));

vi.mock("@/stores/team", () => ({
    useTeamStore: () => ({ fetchTeams: vi.fn(), teams: [] }),
}));

vi.mock("@/stores/option", () => ({
    useOptionStore: () => ({
        fetchEmploymentTypes: vi.fn(),
        fetchJobStatuses: vi.fn(),
        fetchWorkLocations: vi.fn(),
        employmentTypes: [],
        jobStatuses: [],
        workLocations: [],
    }),
}));

vi.mock("@/stores/payroll", () => ({
    usePayrollStore: () => ({ fetchSettings: vi.fn(), settings: null }),
}));

vi.mock("@/stores/performanceReview", () => ({
    usePerformanceReviewStore: () => ({ fetchTemplates: vi.fn(), templates: [] }),
}));

vi.mock("pinia", async (importOriginal) => ({
    ...(await importOriginal()),
    storeToRefs: () => ({
        teams: { value: [] },
        employmentTypes: { value: [] },
        jobStatuses: { value: [] },
        workLocations: { value: [] },
        settings: { value: null },
    }),
}));

import Step2JobInfo from "@/components/admin/staff-member/create/steps/Step2JobInfo.vue";

const roleOptionValues = (wrapper) =>
    wrapper
        .findAll('select[name="role"] option')
        .map((option) => option.element.value)
        .filter(Boolean);

const mountStep = (role) =>
    mount(Step2JobInfo, {
        props: { modelValue: { role, job_title: "", team_id: "", monthly_salary: "" } },
    });

describe("Step2JobInfo role selector", () => {
    it("offers only the staff role and the current role to a non-superadmin", () => {
        authState.roles = ["hr"];

        expect(roleOptionValues(mountStep("manager"))).toEqual(["manager"]);
        expect(roleOptionValues(mountStep(""))).toEqual(["staff"]);
    });

    it("offers every role to a superadmin", () => {
        authState.roles = ["superadmin"];

        expect(roleOptionValues(mountStep(""))).toEqual(["superadmin", "manager", "hr", "finance", "staff"]);
    });
});
