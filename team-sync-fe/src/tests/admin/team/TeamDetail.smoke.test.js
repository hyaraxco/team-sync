import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const { routeState, routerPushMock, teamStoreMock, teamStoreRefs, staffMemberStoreMock, staffMemberStoreRefs } =
    vi.hoisted(() => ({
        routeState: {
            params: {
                id: "19",
            },
        },
        routerPushMock: vi.fn(),
        teamStoreMock: {
            fetchTeam: vi.fn(),
            deleteTeam: vi.fn(),
            addMember: vi.fn(),
            removeMember: vi.fn(),
        },
        teamStoreRefs: {
            loading: {
                __v_isRef: true,
                value: false,
            },
            success: {
                __v_isRef: true,
                value: null,
            },
            error: {
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
    }));

vi.mock("@/stores/auth", () => ({
    useAuthStore: () => ({
        user: { company_timezone: "Asia/Jakarta" },
    }),
}));

vi.mock("@/stores/team", () => ({
    useTeamStore: () => teamStoreMock,
}));

vi.mock("@/stores/staffMember", () => ({
    useStaffMemberStore: () => staffMemberStoreMock,
}));

vi.mock("vue-router", () => ({
    useRoute: () => routeState,
    useRouter: () => ({
        push: routerPushMock,
    }),
    createRouter: vi.fn(() => ({ push: vi.fn(), beforeEach: vi.fn() })),
    createWebHistory: vi.fn(),
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
            return {};
        },
    };
});

import TeamDetail from "@/views/admin/team/TeamDetail.vue";

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const teamPayload = {
    id: 19,
    name: "Platform Team",
    department: "engineering",
    expected_size: 8,
    created_at: "2026-03-01",
    responsibilities: ["Build APIs"],
    leader: null,
    members: [],
};

const factory = () =>
    mount(TeamDetail, {
        global: {
            stubs: {
                Alert: {
                    template: '<div class="alert-stub"></div>',
                },
                Header: {
                    template: '<div class="header-stub"></div>',
                },
                Statistic: {
                    template: '<div class="statistic-stub"></div>',
                },
                Chart: {
                    template: '<div class="chart-stub"></div>',
                },
                ConfirmationModal: {
                    template: '<div class="confirmation-modal-stub"></div>',
                },
                EmptyState: {
                    template: '<div class="empty-state-stub"></div>',
                },
                RouterLink: {
                    props: ["to"],
                    template: '<a class="router-link-stub"><slot /></a>',
                },
            },
        },
    });

describe("TeamDetail smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        routeState.params.id = "19";
        teamStoreRefs.loading.value = false;
        teamStoreRefs.success.value = null;
        teamStoreRefs.error.value = null;
        teamStoreMock.fetchTeam.mockResolvedValue(teamPayload);
        teamStoreMock.deleteTeam.mockResolvedValue(undefined);
        teamStoreMock.addMember.mockResolvedValue(undefined);
        teamStoreMock.removeMember.mockResolvedValue(undefined);
        staffMemberStoreRefs.staffMembers.value = [
            {
                id: 77,
                user: {
                    name: "Ayu Employee",
                },
                job_information: {
                    job_title: "Backend Engineer",
                },
            },
        ];
        staffMemberStoreMock.fetchStaffMembers.mockResolvedValue(undefined);
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetchTeam on mount", async () => {
        factory();
        await flushAsync();

        expect(teamStoreMock.fetchTeam).toHaveBeenCalledWith("19");
    });

    it("opens add member modal and fetches employees", async () => {
        const wrapper = factory();
        await flushAsync();

        const addButton = wrapper.findAll("button").find((button) => {
            return button.text().includes("Add Member");
        });
        await addButton.trigger("click");
        await flushAsync();

        expect(staffMemberStoreMock.fetchStaffMembers).toHaveBeenCalledWith({
            limit: 6,
        });
        expect(wrapper.text()).toContain("Add Team Member");
    });
});
