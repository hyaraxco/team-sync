import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const {
    routerPushMock,
    teamStoreMock,
    teamStoreRefs,
    optionStoreMock,
    optionStoreRefs,
    searchFilterState,
} = vi.hoisted(() => ({
    routerPushMock: vi.fn(),
    teamStoreMock: {
        fetchTeamsPaginated: vi.fn(),
    },
    teamStoreRefs: {
        teams: {
            __v_isRef: true,
            value: [],
        },
        meta: {
            __v_isRef: true,
            value: {
                from: 1,
                to: 1,
                total: 1,
            },
        },
        loading: {
            __v_isRef: true,
            value: false,
        },
        success: {
            __v_isRef: true,
            value: null,
        },
    },
    optionStoreMock: {
        fetchDepartments: vi.fn(),
    },
    optionStoreRefs: {
        departments: {
            __v_isRef: true,
            value: [{ value: 1, label: "Engineering" }],
        },
    },
    searchFilterState: {
        filters: {
            __v_isRef: true,
            value: { search: null, status: "", department: "" },
        },
        fetchData: vi.fn(),
        handleSearch: vi.fn(),
        handleReset: vi.fn(),
        handlePageChange: vi.fn(),
        handlePerPageChange: vi.fn(),
    },
}));

vi.mock("@/stores/team", () => ({
    useTeamStore: () => teamStoreMock,
}));

vi.mock("@/stores/option", () => ({
    useOptionStore: () => optionStoreMock,
}));

vi.mock("@/composables/useSearchFilter", () => ({
    useSearchFilter: () => searchFilterState,
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
            if (store === optionStoreMock) {
                return optionStoreRefs;
            }
            return {};
        },
    };
});

import TeamList from "@/views/admin/team/TeamList.vue";

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const factory = () =>
    mount(TeamList, {
        global: {
            stubs: {
                Statistic: { template: '<div class="statistic-stub"></div>' },
                CardList: { template: '<div class="card-list-stub"></div>' },
                Pagination: { template: '<div class="pagination-stub"></div>' },
                EmptyState: { template: '<div class="empty-state-stub"></div>' },
                SearchFilter: {
                    template:
                        '<button class="search-trigger" @click="$emit(\'search\', { search: \'Platform\' })">Search</button>',
                },
                Alert: { template: '<div class="alert-stub"></div>' },
                RouterLink: {
                    props: ["to"],
                    template: '<a class="router-link-stub"><slot /></a>',
                },
            },
        },
    });

describe("TeamList smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        teamStoreRefs.teams.value = [{ id: 1, name: "Platform Team" }];
        teamStoreRefs.meta.value = { from: 1, to: 1, total: 1 };
        teamStoreRefs.loading.value = false;
        teamStoreRefs.success.value = null;
        optionStoreMock.fetchDepartments.mockResolvedValue(undefined);
        searchFilterState.fetchData.mockResolvedValue(undefined);
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetch on mount", async () => {
        factory();
        await flushAsync();

        expect(optionStoreMock.fetchDepartments).toHaveBeenCalled();
        expect(searchFilterState.fetchData).toHaveBeenCalled();
    });

    it("handles search interaction", async () => {
        const wrapper = factory();
        await wrapper.find(".search-trigger").trigger("click");

        expect(searchFilterState.handleSearch).toHaveBeenCalledWith({ search: "Platform" });
    });
});
