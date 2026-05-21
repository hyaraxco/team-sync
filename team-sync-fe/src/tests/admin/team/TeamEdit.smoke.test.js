import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const {
    routeState,
    teamStoreMock,
    teamStoreRefs,
    staffMemberStoreMock,
    staffMemberStoreRefs,
    optionStoreMock,
    optionStoreRefs,
    routerBackMock,
    routerPushMock,
} = vi.hoisted(() => ({
    routeState: {
        params: {
            id: "9",
        },
    },
    teamStoreMock: {
        updateTeam: vi.fn(),
        fetchTeam: vi.fn(),
        selectLeadPayload: vi.fn((employee) => ({
            selectedLead: employee,
            team_lead_id: employee?.user?.id ?? null,
        })),
        removeLeadPayload: vi.fn(() => ({ selectedLead: null, team_lead_id: null })),
        addResponsibility: vi.fn((list) => [...list, ""]),
        removeResponsibility: vi.fn((list, idx) => {
            const clone = [...list];
            clone.splice(idx, 1);
            return clone;
        }),
    },
    teamStoreRefs: {
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
    staffMemberStoreMock: {
        fetchStaffMembers: vi.fn(),
    },
    staffMemberStoreRefs: {
        staffMembers: {
            __v_isRef: true,
            value: [],
        },
    },
    optionStoreMock: {
        fetchDepartments: vi.fn(),
    },
    optionStoreRefs: {
        departments: {
            __v_isRef: true,
            value: [{ value: "engineering", label: "Engineering" }],
        },
    },
    routerBackMock: vi.fn(),
    routerPushMock: vi.fn(),
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
        back: routerBackMock,
        push: routerPushMock,
    },
}));

vi.mock("vue-router", () => ({
    useRoute: () => routeState,
    useRouter: () => ({
        push: routerPushMock,
        back: routerBackMock,
    }),
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        storeToRefs: (store) => {
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

import TeamEdit from "@/views/admin/team/TeamEdit.vue";

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const factory = () =>
    mount(TeamEdit, {
        global: {
            stubs: {
                Input: {
                    props: ["label"],
                    template: '<div class="input-stub">{{ label }}</div>',
                },
                Select: {
                    props: ["label"],
                    template: '<div class="select-stub">{{ label }}</div>',
                },
                TextArea: {
                    props: ["label"],
                    template: '<div class="textarea-stub">{{ label }}</div>',
                },
                RightSidebarForm: {
                    template: '<div class="right-sidebar-form-stub"></div>',
                },
                EmptyState: {
                    template: '<div class="empty-state-stub"></div>',
                },
            },
        },
    });

describe("TeamEdit smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        routeState.params.id = "9";
        teamStoreRefs.loading.value = false;
        teamStoreRefs.error.value = null;
        teamStoreRefs.success.value = null;

        optionStoreMock.fetchDepartments.mockResolvedValue(undefined);
        staffMemberStoreMock.fetchStaffMembers.mockResolvedValue(undefined);
        teamStoreMock.updateTeam.mockResolvedValue(undefined);
        teamStoreMock.fetchTeam.mockResolvedValue({
            id: 9,
            name: "Platform Team",
            expected_size: 8,
            description: "Core platform work",
            icon: "https://example.com/team-icon.png",
            department: "engineering",
            status: "active",
            team_lead_id: 11,
            responsibilities: ["Build APIs", "Code review", "Mentoring"],
            leader: {
                id: 11,
                name: "Ayu Lead",
                employee_profile: {
                    job_information: {
                        job_title: "Engineering Manager",
                    },
                },
            },
        });
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("fetches dependencies and team payload on mount", async () => {
        factory();
        await flushAsync();

        expect(optionStoreMock.fetchDepartments).toHaveBeenCalled();
        expect(staffMemberStoreMock.fetchStaffMembers).toHaveBeenCalledWith({
            limit: 6,
        });
        expect(teamStoreMock.fetchTeam).toHaveBeenCalledWith("9");
    });

    it("shows page section content", async () => {
        const wrapper = factory();
        await flushAsync();

        expect(wrapper.text()).toContain("Informasi Tim");
    });

    it("submits update and navigates back when successful", async () => {
        const wrapper = factory();
        await flushAsync();

        teamStoreRefs.success.value = "Team updated";
        await wrapper.find("form").trigger("submit");
        await flushAsync();

        expect(teamStoreMock.updateTeam).toHaveBeenCalledWith("9", expect.objectContaining({ name: "Platform Team" }));
        expect(routerBackMock).toHaveBeenCalled();
    });
});
