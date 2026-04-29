import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const {
    projectStoreMock,
    projectStoreRefs,
    searchFilterState,
    routerPushMock,
} = vi.hoisted(() => ({
    projectStoreMock: {
        fetchProjectsPaginated: vi.fn(),
    },
    projectStoreRefs: {
        projects: {
            __v_isRef: true,
            value: [],
        },
        meta: {
            __v_isRef: true,
            value: {
                current_page: 1,
                last_page: 1,
                per_page: 10,
                total: 0,
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
    searchFilterState: {
        filters: {
            __v_isRef: true,
            value: { search: null, status: "" },
        },
        fetchData: vi.fn(),
        handleSearch: vi.fn(),
        handleReset: vi.fn(),
        handlePageChange: vi.fn(),
        handlePerPageChange: vi.fn(),
    },
    routerPushMock: vi.fn(),
}));

vi.mock("@/stores/project", () => ({
    useProjectStore: () => projectStoreMock,
}));

vi.mock("@/composables/useSearchFilter", () => ({
    useSearchFilter: () => searchFilterState,
}));

vi.mock("@/helpers/permissionHelper", () => ({
    can: () => true,
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
            if (store === projectStoreMock) {
                return projectStoreRefs;
            }
            return {};
        },
    };
});

import ProjectList from "@/views/admin/project/ProjectList.vue";

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const factory = () =>
    mount(ProjectList, {
        global: {
            stubs: {
                Statistics: { template: '<div class="statistics-stub"></div>' },
                CardList: {
                    props: ["data"],
                    template: '<div class="card-list-stub">{{ data?.name }}</div>',
                },
                SearchFilter: {
                    template:
                        '<button class="search-trigger" @click="$emit(\'search\', { search: \'Mobile\' })">Search</button>',
                },
                Pagination: { template: '<div class="pagination-stub"></div>' },
                Alert: { template: '<div class="alert-stub"></div>' },
                EmptyState: { template: '<div class="empty-state-stub"></div>' },
                RouterLink: {
                    props: ["to"],
                    template: '<a class="router-link-stub"><slot /></a>',
                },
            },
        },
    });

describe("ProjectList smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        projectStoreRefs.projects.value = [{ id: 3, name: "Mobile Revamp" }];
        projectStoreRefs.meta.value = {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 1,
        };
        projectStoreRefs.loading.value = false;
        projectStoreRefs.success.value = null;
        searchFilterState.fetchData.mockResolvedValue(undefined);
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetchData on mount", async () => {
        factory();
        await flushAsync();

        expect(searchFilterState.fetchData).toHaveBeenCalled();
    });

    it("displays projects heading", () => {
        const wrapper = factory();
        expect(wrapper.text()).toContain("All Projects");
    });

    it("forwards search event into composable handler", async () => {
        const wrapper = factory();
        await wrapper.find(".search-trigger").trigger("click");

        expect(searchFilterState.handleSearch).toHaveBeenCalledWith({
            search: "Mobile",
        });
    });
});
