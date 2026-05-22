import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const { attendanceStoreMock, attendanceStoreRefs, searchFilterState } = vi.hoisted(() => ({
    attendanceStoreMock: {
        fetchAllPaginated: vi.fn(),
    },
    attendanceStoreRefs: {
        paginatedAttendances: {
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
        error: {
            __v_isRef: true,
            value: null,
        },
    },
    searchFilterState: {
        filters: {
            __v_isRef: true,
            value: { search: null },
        },
        fetchData: vi.fn(),
        handleSearch: vi.fn(),
        handleReset: vi.fn(),
        handlePageChange: vi.fn(),
        handlePerPageChange: vi.fn(),
    },
}));

vi.mock("@/stores/attendance", () => ({
    useAttendanceStore: () => attendanceStoreMock,
}));

vi.mock("@/composables/useSearchFilter", () => ({
    useSearchFilter: () => searchFilterState,
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        storeToRefs: (store) => {
            if (store === attendanceStoreMock) {
                return attendanceStoreRefs;
            }
            return {};
        },
    };
});

import AttendanceRecordList from "@/views/admin/attendance/AttendanceRecordList.vue";

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const factory = () =>
    mount(AttendanceRecordList, {
        global: {
            stubs: {
                SearchFilter: {
                    props: ["search"],
                    template: '<button class="search-trigger" @click="$emit(\'search\')">Search</button>',
                },
                Pagination: { template: '<div class="pagination-stub"></div>' },
                Alert: { template: '<div class="alert-stub"></div>' },
                EmptyState: { template: '<div class="empty-state-stub"></div>' },
                StatusBadge: { template: '<div class="status-badge-stub"></div>' },
            },
        },
    });

describe("AttendanceRecordList smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
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

    it("forwards search interaction to handler", async () => {
        const wrapper = factory();
        await wrapper.find(".search-trigger").trigger("click");

        expect(searchFilterState.handleSearch).toHaveBeenCalled();
    });

    it("keeps standardized list helpers without local h1", () => {
        const wrapper = factory();

        expect(wrapper.find(".search-trigger").exists()).toBe(true);
        expect(wrapper.find(".empty-state-stub").exists()).toBe(true);
        expect(wrapper.find("h1").exists()).toBe(false);
    });
});
