import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const {
    projectStoreMock,
    teamStoreMock,
    staffMemberStoreMock,
    optionStoreMock,
    projectStoreRefs,
    teamStoreRefs,
    staffMemberStoreRefs,
    optionStoreRefs,
    routerPushMock,
} = vi.hoisted(() => ({
    projectStoreMock: {
        createProject: vi.fn(),
    },
    teamStoreMock: {
        fetchTeams: vi.fn(),
    },
    staffMemberStoreMock: {
        fetchStaffMembers: vi.fn(),
    },
    optionStoreMock: {
        fetchProjectTaskTemplates: vi.fn(),
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
    optionStoreRefs: {
        projectTaskTemplates: { __v_isRef: true, value: [] },
    },
    routerPushMock: vi.fn(),
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

vi.mock("@/stores/option", () => ({
    useOptionStore: () => optionStoreMock,
}));

vi.mock("@/router", () => ({
    default: {
        push: routerPushMock,
    },
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
            if (store === optionStoreMock) {
                return optionStoreRefs;
            }
            return {};
        },
    };
});

import ProjectCreate from "@/views/admin/project/ProjectCreate.vue";

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const factory = () =>
    mount(ProjectCreate, {
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

describe("ProjectCreate smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
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
        optionStoreMock.fetchProjectTaskTemplates.mockResolvedValue(undefined);
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
        expect(optionStoreMock.fetchProjectTaskTemplates).toHaveBeenCalled();
    });

    it("opens leader modal when leader selector clicked", async () => {
        const wrapper = factory();
        const openButton = wrapper.findAll("button").find((btn) => btn.text().includes("Select project leader"));

        await openButton.trigger("click");

        expect(wrapper.text()).toContain("Select Project Leader");
    });
});
