import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const {
    teamStoreMock,
    teamStoreRefs,
    staffMemberStoreMock,
    staffMemberStoreRefs,
    optionStoreMock,
    optionStoreRefs,
    routerPushMock,
} = vi.hoisted(() => ({
    teamStoreMock: {
        createTeam: vi.fn(),
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
        push: routerPushMock,
    },
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

import TeamCreate from "@/views/admin/team/TeamCreate.vue";

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const factory = () =>
    mount(TeamCreate, {
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

describe("TeamCreate smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        teamStoreRefs.loading.value = false;
        teamStoreRefs.error.value = null;
        teamStoreRefs.success.value = null;
        staffMemberStoreRefs.staffMembers.value = [
            {
                id: 1,
                user: { id: 11, name: "Ayu Employee" },
                job_information: { job_title: "Engineer" },
            },
        ];
        optionStoreRefs.departments.value = [{ value: "engineering", label: "Engineering" }];
        optionStoreMock.fetchDepartments.mockResolvedValue(undefined);
        staffMemberStoreMock.fetchStaffMembers.mockResolvedValue(undefined);
        teamStoreMock.createTeam.mockResolvedValue(undefined);
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls initial data fetches on mount", async () => {
        factory();
        await flushAsync();

        expect(optionStoreMock.fetchDepartments).toHaveBeenCalled();
        expect(staffMemberStoreMock.fetchStaffMembers).toHaveBeenCalledWith({
            limit: 6,
        });
    });

    it("displays core section heading", () => {
        const wrapper = factory();
        expect(wrapper.text()).toContain("Team Information");
    });

    it("submits create action and navigates when successful", async () => {
        const wrapper = factory();
        await flushAsync();

        teamStoreRefs.success.value = "Team created";
        await wrapper.find("form").trigger("submit");
        await flushAsync();

        expect(teamStoreMock.createTeam).toHaveBeenCalled();
        expect(routerPushMock).toHaveBeenCalledWith({ name: "admin.teams" });
    });
});
