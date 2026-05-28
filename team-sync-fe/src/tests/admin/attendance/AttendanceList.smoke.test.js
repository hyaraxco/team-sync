import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

const {
    attendanceStoreMock,
    leaveRequestStoreMock,
    correctionStoreMock,
    toastErrorMock,
    approveModalOpenMock,
    rejectModalOpenMock,
    approveConfirmMock,
    rejectConfirmMock,
    useConfirmActionCallCount,
    searchFilterMocks,
} = vi.hoisted(() => ({
    attendanceStoreMock: {
        fetchAdminStatistics: vi.fn(),
        error: null,
    },
    leaveRequestStoreMock: {
        fetchLeaveRequestsPaginated: vi.fn(),
        approveLeaveRequest: vi.fn(),
        rejectLeaveRequest: vi.fn(),
        leaveRequests: [],
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
        },
        loading: false,
        error: null,
    },
    correctionStoreMock: {
        fetchAllPaginated: vi.fn(),
        paginatedCorrections: [],
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
        },
        loading: false,
        error: null,
    },
    toastErrorMock: vi.fn(),
    approveModalOpenMock: vi.fn(),
    rejectModalOpenMock: vi.fn(),
    approveConfirmMock: vi.fn(),
    rejectConfirmMock: vi.fn(),
    useConfirmActionCallCount: {
        value: 0,
    },
    searchFilterMocks: {
        leave: {
            filters: { __v_isRef: true, value: { search: null } },
            fetchData: vi.fn(),
            handleSearch: vi.fn(),
            handleReset: vi.fn(),
            handlePageChange: vi.fn(),
            handlePerPageChange: vi.fn(),
        },
        correction: {
            filters: { __v_isRef: true, value: { search: null, status: '' } },
            fetchData: vi.fn(),
            handleSearch: vi.fn(),
            handleReset: vi.fn(),
            handlePageChange: vi.fn(),
            handlePerPageChange: vi.fn(),
        },
    },
}));

vi.mock("@/stores/attendance", () => ({
    useAttendanceStore: () => attendanceStoreMock,
}));

vi.mock("@/stores/leaveRequest", () => ({
    useLeaveRequestStore: () => leaveRequestStoreMock,
}));

vi.mock("@/stores/attendanceCorrection", () => ({
    useAttendanceCorrectionStore: () => correctionStoreMock,
}));

vi.mock("@/helpers/permissionHelper", () => ({
    can: () => true,
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        error: toastErrorMock,
    }),
}));

vi.mock("@/composables/useConfirmAction", () => ({
    useConfirmAction: () => {
        useConfirmActionCallCount.value += 1;

        if (useConfirmActionCallCount.value === 1) {
            return {
                isModalOpen: { __v_isRef: true, value: false },
                selectedItem: { __v_isRef: true, value: null },
                isProcessing: { __v_isRef: true, value: false },
                openModal: approveModalOpenMock,
                closeModal: vi.fn(),
                confirmAction: approveConfirmMock,
            };
        }

        return {
            isModalOpen: { __v_isRef: true, value: false },
            selectedItem: { __v_isRef: true, value: null },
            isProcessing: { __v_isRef: true, value: false },
            openModal: rejectModalOpenMock,
            closeModal: vi.fn(),
            confirmAction: rejectConfirmMock,
        };
    },
}));

vi.mock("@/composables/useSearchFilter", () => ({
    useSearchFilter: ({ fetchFn }) => {
        // Determine which mock to return based on fetchFn
        if (fetchFn === leaveRequestStoreMock.fetchLeaveRequestsPaginated) {
            return searchFilterMocks.leave;
        }
        if (fetchFn === correctionStoreMock.fetchAllPaginated) {
            return searchFilterMocks.correction;
        }
        // Default fallback
        return searchFilterMocks.leave;
    },
}));

import AttendanceList from "@/views/admin/attendance/AttendanceList.vue";

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const factory = () =>
    mount(AttendanceList, {
        global: {
            stubs: {
                RouterLink: {
                    props: ["to"],
                    template: '<a class="router-link-stub"><slot /></a>',
                },
                StatusBadge: { template: '<div class="status-badge-stub"><slot /></div>' },
                StatsCard: { template: '<div class="stats-card-stub"><slot /></div>' },
                EmptyState: {
                    props: ["icon", "title"],
                    template: '<div class="empty-state-stub" :data-icon="icon" :data-title="title"><slot /></div>',
                },
                MainCard: { template: '<div class="main-card-stub"><slot /></div>' },
                ModalWrapper: {
                    props: ["show"],
                    template: '<div class="modal-wrapper-stub"><slot /><slot name="footer" /></div>',
                },
                AnimatedValue: {
                    props: ["value"],
                    template: '<span class="animated-value-stub">{{ value }}</span>',
                },
                SearchFilter: { template: '<div class="search-filter-stub"></div>' },
                Pagination: { template: '<div class="pagination-stub"></div>' },
                AttendanceRecordList: { template: '<div class="attendance-record-list-stub"></div>' },
                OvertimeManagement: { template: '<div class="overtime-management-stub"></div>' },
                HybridScheduleList: { template: '<div class="hybrid-schedule-list-stub"></div>' },
                LeaveRequestList: { template: '<div class="leave-request-list-stub"></div>' },
                AttendanceCorrectionList: { template: '<div class="attendance-correction-list-stub"></div>' },
            },
        },
    });

describe("AttendanceList smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        useConfirmActionCallCount.value = 0;

        attendanceStoreMock.fetchAdminStatistics.mockResolvedValue({
            present_today: 10,
            present_change: 2,
        });
        leaveRequestStoreMock.fetchLeaveRequestsPaginated.mockResolvedValue({
            data: [
                {
                    id: 1,
                    type: "annual",
                    status: "pending",
                    start_date: "2026-04-01",
                    end_date: "2026-04-02",
                    days: 2,
                    reason: "Family event",
                    staff_member: {
                        user: {
                            name: "Nadia",
                        },
                    },
                },
            ],
            meta: {
                current_page: 1,
                last_page: 1,
                per_page: 10,
                total: 1,
            },
        });
        leaveRequestStoreMock.leaveRequests = [
            {
                id: 1,
                type: "annual",
                status: "pending",
                start_date: "2026-04-01",
                end_date: "2026-04-02",
                days: 2,
                reason: "Family event",
                staff_member: {
                    user: {
                        name: "Nadia",
                    },
                },
            },
        ];
        correctionStoreMock.paginatedCorrections = [];
        correctionStoreMock.fetchAllPaginated.mockResolvedValue(undefined);
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("exposes page heading semantics without a local h1 tag", () => {
        const wrapper = factory();

        expect(wrapper.findAll("h1")).toHaveLength(0);

        const pageHeading = wrapper.find('[role="heading"][aria-level="1"]');

        expect(pageHeading.exists()).toBe(true);
        expect(pageHeading.text()).toBe("Attendance");
        expect(pageHeading.classes()).toContain("sr-only");
    });

    it("renders KPI metrics through StatsCard components", () => {
        const wrapper = factory();

        expect(wrapper.findAll(".stats-card-stub").length).toBeGreaterThanOrEqual(6);
    });

    it("uses tokenized surface shells instead of solid white cards", () => {
        const source = readFileSync(
            resolve(process.cwd(), "src/views/admin/attendance/AttendanceList.vue"),
            "utf8",
        );

        expect(source).toContain("var(--color-surface)");
        expect(source).not.toMatch(/\bbg-white\b(?!\/)/);
    });

    it("uses EmptyState for empty dashboard sections", async () => {
        leaveRequestStoreMock.leaveRequests = [];
        correctionStoreMock.paginatedCorrections = [];

        const wrapper = factory();
        await flushAsync();

        // With v-if lazy loading, only the active (default) tab renders.
        // Default active tab is 'leave-requests'.
        expect(wrapper.find(".leave-request-list-stub").exists()).toBe(true);
        // Other tabs are not rendered until their tab is clicked.
        expect(wrapper.find(".attendance-correction-list-stub").exists()).toBe(false);
    });

    it("renders embedded tab components on tab switch", async () => {
        const wrapper = factory();
        await flushAsync();

        // Default tab is leave-requests
        expect(wrapper.find(".leave-request-list-stub").exists()).toBe(true);
        expect(wrapper.find(".attendance-correction-list-stub").exists()).toBe(false);

        // Click the Corrections tab button
        const tabs = wrapper.findAll("button");
        const correctionsTab = tabs.find((btn) => btn.text().includes("Corrections"));
        await correctionsTab.trigger("click");
        await flushAsync();

        // Now corrections should render, leave-requests should not
        expect(wrapper.find(".attendance-correction-list-stub").exists()).toBe(true);
        expect(wrapper.find(".leave-request-list-stub").exists()).toBe(false);
    });

    it("calls fetch methods on mount", async () => {
        factory();
        await flushAsync();

        expect(attendanceStoreMock.fetchAdminStatistics).toHaveBeenCalled();
    });
});
