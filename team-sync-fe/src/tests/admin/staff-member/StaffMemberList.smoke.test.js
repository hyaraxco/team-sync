import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const {
    routerPushMock,
    staffMemberStoreMock,
    staffMemberStoreRefs,
    optionStoreMock,
    optionStoreRefs,
    searchFilterState,
} = vi.hoisted(() => ({
    routerPushMock: vi.fn(),
    staffMemberStoreMock: {
        fetchStaffMembersPaginated: vi.fn(),
    },
    staffMemberStoreRefs: {
        staffMembers: {
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
        error: {
            __v_isRef: true,
            value: null,
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
        fetchEmploymentTypes: vi.fn(),
        fetchJobStatuses: vi.fn(),
    },
    optionStoreRefs: {
        employmentTypes: {
            __v_isRef: true,
            value: [{ value: "full_time", label: "Full Time" }],
        },
        jobStatuses: {
            __v_isRef: true,
            value: [{ value: "active", label: "Active" }],
        },
    },
    searchFilterState: {
        filters: {
            __v_isRef: true,
            value: { search: null, type: "", status: "" },
        },
        fetchData: vi.fn(),
        handleSearch: vi.fn(),
        handleReset: vi.fn(),
        handlePageChange: vi.fn(),
        handlePerPageChange: vi.fn(),
    },
}));

vi.mock("@/stores/staffMember", () => ({
    useStaffMemberStore: () => staffMemberStoreMock,
}));

vi.mock("@/stores/option", () => ({
    useOptionStore: () => optionStoreMock,
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

import StaffMemberList from "@/views/admin/staff-member/StaffMemberList.vue";

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const factory = () =>
    mount(StaffMemberList, {
        global: {
            stubs: {
                Statistics: { template: '<div class="statistics-stub"></div>' },
                CardList: { template: '<div class="card-list-stub"></div>' },
                SearchFilter: {
                    template:
                        "<button class=\"search-trigger\" @click=\"$emit('search', { search: 'John' })\">Search</button>",
                },
                Pagination: { template: '<div class="pagination-stub"></div>' },
                Alert: { props: ["title", "message"], template: '<div class="alert-stub">{{ title }} {{ message }}</div>' },
                RouterLink: {
                    props: ["to"],
                    template: '<a class="router-link-stub"><slot /></a>',
                },
            },
        },
    });

describe("StaffMemberList smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        staffMemberStoreRefs.staffMembers.value = [{ id: 1, name: "John" }];
        staffMemberStoreRefs.meta.value = { from: 1, to: 1, total: 1 };
        staffMemberStoreRefs.loading.value = false;
        staffMemberStoreRefs.error.value = null;
        staffMemberStoreRefs.success.value = null;
        optionStoreMock.fetchEmploymentTypes.mockResolvedValue(undefined);
        optionStoreMock.fetchJobStatuses.mockResolvedValue(undefined);
        searchFilterState.fetchData.mockResolvedValue(undefined);
    });

    it("shows loading instead of empty while fetching", () => {
        staffMemberStoreRefs.staffMembers.value = [];
        staffMemberStoreRefs.loading.value = true;

        const wrapper = factory();
        expect(wrapper.text()).toContain("Loading staff members");
        expect(wrapper.find(".empty-state-stub").exists()).toBe(false);
    });

    it("shows the empty message after successful load with no staff", () => {
        staffMemberStoreRefs.staffMembers.value = [];

        const wrapper = factory();
        expect(wrapper.text()).toContain("No staff members found");
    });

    it("shows fetch failures instead of treating them as empty", () => {
        staffMemberStoreRefs.staffMembers.value = [];
        staffMemberStoreRefs.error.value = "Network unavailable";

        const wrapper = factory();
        expect(wrapper.text()).toContain("Unable to load staff members");
        expect(wrapper.text()).toContain("Network unavailable");
        expect(wrapper.text()).not.toContain("No staff members found");
    });
    it("retries a failed fetch and clears its error when loading succeeds", async () => {
        staffMemberStoreRefs.error.value = "Network unavailable";
        const wrapper = factory();
        searchFilterState.fetchData.mockImplementation(async () => {
            staffMemberStoreRefs.error.value = null;
            staffMemberStoreRefs.staffMembers.value = [{ id: 2, name: "Jane" }];
            await nextTick();
        });

        await wrapper.find("button.btn-secondary").trigger("click");
        await wrapper.vm.$forceUpdate();
        await nextTick();

        expect(searchFilterState.fetchData).toHaveBeenCalled();
        expect(staffMemberStoreRefs.error.value).toBeNull();
        expect(wrapper.text()).not.toContain("Unable to load staff members");
        expect(wrapper.findAll(".card-list-stub")).toHaveLength(1);
    });

    it("renders cards after successful loading", () => {
        staffMemberStoreRefs.staffMembers.value = [{ id: 1, name: "John" }];

        const wrapper = factory();
        expect(wrapper.findAll(".card-list-stub")).toHaveLength(1);
    });
    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetch on mount", async () => {
        factory();
        await flushAsync();

        expect(searchFilterState.fetchData).toHaveBeenCalled();
    });

    it("handles search interaction", async () => {
        const wrapper = factory();
        await wrapper.find(".search-trigger").trigger("click");

        expect(searchFilterState.handleSearch).toHaveBeenCalledWith({ search: "John" });
    });
});
