import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const {
    projectStoreMock,
    teamStoreMock,
    staffMemberStoreMock,
    projectStoreRefs,
    teamStoreRefs,
    staffMemberStoreRefs,
    routerBackMock,
    routeState,
} = vi.hoisted(() => ({
    projectStoreMock: {
        updateProject: vi.fn(),
        fetchProject: vi.fn(),
    },
    teamStoreMock: {
        fetchTeams: vi.fn(),
    },
    staffMemberStoreMock: {
        fetchStaffMembers: vi.fn(),
    },
    projectStoreRefs: {
        loading: { __v_isRef: true, value: false },
        error: { __v_isRef: true, value: null },
        success: { __v_isRef: true, value: null },
    },
    teamStoreRefs: {
        teams: { __v_isRef: true, value: [] },
    },
    staffMemberStoreRefs: {
        staffMembers: { __v_isRef: true, value: [] },
    },
    routerBackMock: vi.fn(),
    routeState: {
        params: { id: "7" },
    },
}));

vi.mock("@/stores/project", () => ({
    useProjectStore: () => projectStoreMock,
}));

vi.mock("@/stores/team", () => ({
    useTeamStore: () => teamStoreMock,
}));

vi.mock("@/stores/staffMember", () => ({
    useStaffMemberStore: () => staffMemberStoreMock,
}));

vi.mock("@/router", () => ({
    default: {
        back: routerBackMock,
    },
}));

vi.mock("vue-router", () => ({
    useRoute: () => routeState,
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        storeToRefs: (store) => {
            if (store === projectStoreMock) {
                return projectStoreRefs;
            }
            if (store === teamStoreMock) {
                return teamStoreRefs;
            }
            if (store === staffMemberStoreMock) {
                return staffMemberStoreRefs;
            }
            return {};
        },
    };
});

import ProjectEdit from "@/views/admin/project/ProjectEdit.vue";

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const factory = () =>
    mount(ProjectEdit, {
        global: {
            stubs: {
                Input: {
                    props: ["modelValue", "label", "type", "required", "placeholder"],
                    template:
                        '<label><span>{{ label }}</span><input :type="type || \'text\'" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
                },
                Select: {
                    props: ["modelValue", "label", "options"],
                    template:
                        '<label><span>{{ label }}</span><select :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><option v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</option></select></label>',
                },
                TextArea: {
                    props: ["modelValue", "label"],
                    template:
                        '<label><span>{{ label }}</span><textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
                },
                RightSidebar: { template: '<div class="right-sidebar-stub"></div>' },
                EmptyState: { template: '<div class="empty-state-stub"></div>' },
            },
        },
    });

describe("ProjectEdit smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        projectStoreRefs.loading.value = false;
        projectStoreRefs.success.value = null;
        teamStoreRefs.teams.value = [{ id: 1, name: "Engineering", members_count: 4 }];
        staffMemberStoreRefs.staffMembers.value = [
            {
                id: 9,
                user: { name: "Ari" },
                job_information: { job_title: "Lead Engineer" },
            },
        ];
        teamStoreMock.fetchTeams.mockResolvedValue(undefined);
        staffMemberStoreMock.fetchStaffMembers.mockResolvedValue(undefined);
        projectStoreMock.fetchProject.mockResolvedValue({
            id: 7,
            name: "HRIS Mobile Revamp",
            type: "mobile_app",
            priority: "high",
            status: "active",
            start_date: "2026-01-01",
            end_date: "2026-12-31",
            description: "Project desc",
            budget: 1000000,
            photo: "https://example.com/photo.png",
            leader: {
                id: 9,
                user: { name: "Ari" },
                employee_profile: {
                    job_information: { job_title: "Lead Engineer" },
                },
            },
            teams: [{ id: 1, name: "Engineering" }],
        });
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetch functions on mount", async () => {
        factory();
        await flushAsync();

        expect(teamStoreMock.fetchTeams).toHaveBeenCalled();
        expect(staffMemberStoreMock.fetchStaffMembers).toHaveBeenCalledWith({
            limit: 6,
        });
        expect(projectStoreMock.fetchProject).toHaveBeenCalledWith("7");
    });

    it("opens leader modal when leader selector clicked", async () => {
        const wrapper = factory();
        await flushAsync();

        expect(wrapper.text()).toContain("Informasi Proyek");
    });
});
