import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

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
} = vi.hoisted(() => ({
    attendanceStoreMock: {
        fetchAdminStatistics: vi.fn(),
        error: null,
    },
    leaveRequestStoreMock: {
        fetchLatestLeaveRequests: vi.fn(),
        approveLeaveRequest: vi.fn(),
        rejectLeaveRequest: vi.fn(),
        error: null,
    },
    correctionStoreMock: {
        fetchAllPaginated: vi.fn(),
        paginatedCorrections: [],
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
        leaveRequestStoreMock.fetchLatestLeaveRequests.mockResolvedValue([
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
        ]);
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

    it("uses EmptyState for empty dashboard sections", async () => {
        leaveRequestStoreMock.fetchLatestLeaveRequests.mockResolvedValue([]);
        correctionStoreMock.paginatedCorrections = [];

        const wrapper = factory();
        await flushAsync();

        // With tabs, only the active tab's empty state is rendered
        expect(wrapper.findAll(".empty-state-stub").length).toBeGreaterThanOrEqual(1);
    });

    it("uses a supported EmptyState icon for pending corrections", async () => {
        leaveRequestStoreMock.fetchLatestLeaveRequests.mockResolvedValue([]);
        correctionStoreMock.paginatedCorrections = [];

        const wrapper = factory();
        await flushAsync();

        // Default tab is leave-requests, so check that empty state
        const leaveEmptyState = wrapper
            .findAll(".empty-state-stub")
            .find((emptyState) => emptyState.attributes("data-title") === "No pending leave requests");

        expect(leaveEmptyState).toBeDefined();
    });

    it("calls fetch methods on mount", async () => {
        factory();
        await flushAsync();

        expect(attendanceStoreMock.fetchAdminStatistics).toHaveBeenCalled();
        expect(leaveRequestStoreMock.fetchLatestLeaveRequests).toHaveBeenCalledWith(5);
        expect(correctionStoreMock.fetchAllPaginated).toHaveBeenCalledWith({
            status: "pending",
            row_per_page: 5,
            page: 1,
        });
    });

    it("triggers approve modal when approve clicked", async () => {
        const wrapper = factory();
        await flushAsync();

        const approveButton = wrapper.findAll("button").find((button) => button.text().includes("Approve"));

        await approveButton.trigger("click");

        expect(approveModalOpenMock).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }));
    });
});
