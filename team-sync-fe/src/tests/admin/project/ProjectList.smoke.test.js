import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const { projectStoreMock, projectStoreRefs, searchFilterState, routerPushMock } = vi.hoisted(() => ({
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
    createRouter: vi.fn(() => ({ push: vi.fn(), beforeEach: vi.fn() })),
    createWebHistory: vi.fn(),
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
                        "<button class=\"search-trigger\" @click=\"$emit('search', { search: 'Mobile' })\">Search</button>",
                },
                Pagination: {
                    props: ["meta"],
                    template: '<div class="pagination-stub">{{ meta?.current_page }} / {{ meta?.last_page }}</div>',
                },
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

    it("hides empty state and pagination before the initial load completes", () => {
        searchFilterState.fetchData.mockReturnValue(new Promise(() => {}));
        projectStoreRefs.projects.value = [];
        projectStoreRefs.meta.value = { current_page: undefined, last_page: undefined, per_page: undefined, total: undefined };

        const wrapper = factory();

        expect(wrapper.find(".empty-state-stub").exists()).toBe(false);
        expect(wrapper.find(".pagination-stub").exists()).toBe(false);
        expect(wrapper.text()).not.toContain("undefined");
    });

    it("shows an accurate empty state after a completed empty result", async () => {
        projectStoreRefs.projects.value = [];
        projectStoreRefs.meta.value = { current_page: 1, last_page: 1, per_page: 10, total: 0 };
        const wrapper = factory();
        await flushAsync();

        expect(wrapper.find(".empty-state-stub").exists()).toBe(true);
        expect(wrapper.find(".pagination-stub").text()).toContain("1 / 1");
        expect(wrapper.text()).not.toContain("undefined");
    });

    it("shows populated results with metadata-backed pagination", async () => {
        projectStoreRefs.projects.value = [{ id: 3, name: "Mobile Revamp" }];
        projectStoreRefs.meta.value = { current_page: 2, last_page: 4, per_page: 10, total: 40 };
        const wrapper = factory();
        await flushAsync();

        expect(wrapper.text()).toContain("Mobile Revamp");
        expect(wrapper.find(".empty-state-stub").exists()).toBe(false);
        expect(wrapper.find(".pagination-stub").text()).toContain("2 / 4");
    });
});
