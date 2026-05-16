import { describe, expect, it, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { ref } from "vue";

const mockLeaveRequests = ref([]);
const mockMeta = ref({
    current_page: 1,
    last_page: 5,
    per_page: 10,
    total: 50,
});
const mockLoading = ref(false);
const mockCalendarData = ref([]);
const mockError = ref(null);

const mockFetchLeaveRequestsPaginated = vi.fn().mockResolvedValue([]);
const mockFetchCalendarData = vi.fn().mockResolvedValue([]);
const mockApproveLeaveRequest = vi.fn().mockResolvedValue({});
const mockRejectLeaveRequest = vi.fn().mockResolvedValue({});
const mockBulkAction = vi.fn().mockResolvedValue({});

vi.mock("@/stores/leaveRequest", () => ({
    useLeaveRequestStore: () => ({
        leaveRequests: mockLeaveRequests,
        meta: mockMeta,
        loading: mockLoading,
        calendarData: mockCalendarData,
        error: mockError,
        fetchLeaveRequestsPaginated: mockFetchLeaveRequestsPaginated,
        fetchCalendarData: mockFetchCalendarData,
        approveLeaveRequest: mockApproveLeaveRequest,
        rejectLeaveRequest: mockRejectLeaveRequest,
        bulkAction: mockBulkAction,
    }),
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        storeToRefs: () => ({
            leaveRequests: mockLeaveRequests,
            meta: mockMeta,
            loading: mockLoading,
            calendarData: mockCalendarData,
            error: mockError,
        }),
    };
});

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: vi.fn(),
        warning: vi.fn(),
        error: vi.fn(),
    }),
}));

vi.mock("@/composables/useConfirmAction", () => ({
    useConfirmAction: () => ({
        isModalOpen: ref(false),
        selectedItem: ref(null),
        isProcessing: ref(false),
        openModal: vi.fn(),
        closeModal: vi.fn(),
        confirmAction: vi.fn(),
    }),
}));

vi.mock("@/composables/useSearchFilter", () => ({
    useSearchFilter: () => ({
        filters: ref({ search: null, status: "" }),
        fetchData: vi.fn(),
        handleSearch: vi.fn(),
        handleReset: vi.fn(),
        handlePageChange: vi.fn(),
        handlePerPageChange: vi.fn(),
    }),
}));

vi.mock("vue-router", () => ({
    useRoute: () => ({
        params: {},
        query: {},
    }),
    createRouter: vi.fn(() => ({ push: vi.fn(), beforeEach: vi.fn() })),
    createWebHistory: vi.fn(),
}));

vi.mock("luxon", async (importOriginal) => {
    const actual = await importOriginal();
    return actual;
});

import LeaveRequestList from "@/views/admin/attendance/LeaveRequestList.vue";

const flushPromises = async () => {
    await Promise.resolve();
    await Promise.resolve();
};

const factory = () =>
    mount(LeaveRequestList, {
        global: {
            stubs: {
                SearchFilter: {
                    template: '<div class="search-filter-stub" />',
                },
                Pagination: {
                    template: '<div class="pagination-stub" />',
                },
                EmptyState: {
                    template: '<div class="empty-state-stub" />',
                },
                ModalWrapper: {
                    props: ["show", "title", "maxWidth"],
                    template: '<div v-if="show"><slot /><slot name="footer" /></div>',
                },
                StatusBadge: {
                    props: ["type", "value"],
                    template: "<span>{{ value }}</span>",
                },
            },
        },
    });

describe("LeaveRequestList - normalizeErrorMessage", () => {
    beforeEach(() => {
        mockLeaveRequests.value = [];
        mockLoading.value = false;
        mockError.value = null;
        mockFetchLeaveRequestsPaginated.mockClear();
    });

    it("extracts message from axios response.data.message", () => {
        const wrapper = factory();
        const error = {
            response: {
                data: {
                    message: "Server is down",
                },
            },
        };
        expect(wrapper.vm.normalizeErrorMessage(error)).toBe("Server is down");
    });

    it("extracts first validation error when message is missing", () => {
        const wrapper = factory();
        const error = {
            response: {
                data: {
                    errors: {
                        start_date: ["The start date is required."],
                        end_date: ["The end date must be after start."],
                    },
                },
            },
        };
        expect(wrapper.vm.normalizeErrorMessage(error)).toBe("The start date is required.");
    });

    it("flattens nested validation error arrays", () => {
        const wrapper = factory();
        const error = {
            response: {
                data: {
                    errors: {
                        dates: ["Error A", "Error B"],
                    },
                },
            },
        };
        expect(wrapper.vm.normalizeErrorMessage(error)).toBe("Error A");
    });

    it("falls back to store error when axios error has no data", () => {
        mockError.value = "Store-level error";
        const wrapper = factory();
        const error = { response: { data: {} } };
        expect(wrapper.vm.normalizeErrorMessage(error)).toBe("Store-level error");
    });

    it("returns generic fallback when nothing else is available", () => {
        mockError.value = null;
        const wrapper = factory();
        const error = { response: { data: {} } };
        expect(wrapper.vm.normalizeErrorMessage(error)).toBe("Failed to process selected leave requests.");
    });

    it("handles completely empty error object", () => {
        mockError.value = null;
        const wrapper = factory();
        expect(wrapper.vm.normalizeErrorMessage({})).toBe("Failed to process selected leave requests.");
    });
});

describe("LeaveRequestList - Calendar grid", () => {
    beforeEach(() => {
        mockLeaveRequests.value = [];
        mockCalendarData.value = [];
        mockLoading.value = false;
    });

    it("calendarGrid generates correct number of days for a month", () => {
        const wrapper = factory();
        const grid = wrapper.vm.calendarGrid;

        // Grid should be a multiple of 7 (full weeks)
        expect(grid.length % 7).toBe(0);

        // All days should be Luxon DateTime objects
        for (const day of grid) {
            expect(day.toISODate).toBeDefined();
        }
    });

    it("calendarGrid starts on Monday", () => {
        const wrapper = factory();
        const grid = wrapper.vm.calendarGrid;
        // Luxon weekday: 1=Mon, 7=Sun
        expect(grid[0].weekday).toBe(1);
    });

    it("calendarGrid ends on Sunday", () => {
        const wrapper = factory();
        const grid = wrapper.vm.calendarGrid;
        expect(grid[grid.length - 1].weekday).toBe(7);
    });

    it("getLeavesForDate filters requests by date range", () => {
        mockCalendarData.value = [
            {
                id: 1,
                start_date: "2026-06-01",
                end_date: "2026-06-03",
                status: "approved",
                staff_member: { user: { name: "Alice" } },
            },
            {
                id: 2,
                start_date: "2026-06-10",
                end_date: "2026-06-12",
                status: "pending",
                staff_member: { user: { name: "Bob" } },
            },
        ];
        const wrapper = factory();

        const { DateTime } = require("luxon");
        const june2 = DateTime.fromISO("2026-06-02");
        const june11 = DateTime.fromISO("2026-06-11");
        const june15 = DateTime.fromISO("2026-06-15");

        expect(wrapper.vm.getLeavesForDate(june2)).toHaveLength(1);
        expect(wrapper.vm.getLeavesForDate(june2)[0].id).toBe(1);

        expect(wrapper.vm.getLeavesForDate(june11)).toHaveLength(1);
        expect(wrapper.vm.getLeavesForDate(june11)[0].id).toBe(2);

        expect(wrapper.vm.getLeavesForDate(june15)).toHaveLength(0);
    });
});
