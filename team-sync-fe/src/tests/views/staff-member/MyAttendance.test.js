import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { ref } from "vue";

const attendanceLoading = ref(false);
const attendances = ref([]);
const statistics = ref({});
const todayAttendance = ref(null);
const leaveLoading = ref(false);
const myLeaveRequests = ref([]);
const myLeaveBalances = ref([]);
const leaveTypes = ref([]);
const correctionLoading = ref(false);
const myCorrections = ref([]);

const fetchAttendances = vi.fn().mockResolvedValue(undefined);
const fetchStatistics = vi.fn().mockResolvedValue(undefined);
const fetchTodayAttendance = vi.fn().mockResolvedValue(undefined);
const checkIn = vi.fn().mockResolvedValue(undefined);
const checkOut = vi.fn().mockResolvedValue(undefined);
const fetchMyLeaveRequests = vi.fn().mockResolvedValue(undefined);
const fetchMyLeaveBalances = vi.fn().mockResolvedValue(undefined);
const createLeaveRequest = vi.fn().mockResolvedValue(undefined);
const uploadProof = vi.fn().mockResolvedValue(undefined);
const fetchLeaveTypes = vi.fn().mockResolvedValue(undefined);
const fetchMyCorrections = vi.fn().mockResolvedValue(undefined);
const requestCorrection = vi.fn().mockResolvedValue(undefined);
const routerReplace = vi.fn().mockResolvedValue(undefined);

let currentRoute = {
    name: "staffMember.attendance.my-attendances",
    params: {},
    query: {},
    hash: "",
};
let hasLeaveRequestPermission = false;
let hasClockPermission = false;
let mockWorkLocation = "office";
let toastWarningFn;
let toastErrorFn;
let toastSuccessFn;

vi.mock("@/stores/attendance", () => ({
    useAttendanceStore: () => ({
        loading: attendanceLoading,
        attendances,
        statistics,
        todayAttendance,
        fetchAttendances,
        fetchStatistics,
        fetchTodayAttendance,
        checkIn,
        checkOut,
    }),
}));

vi.mock("@/stores/leaveRequest", () => ({
    useLeaveRequestStore: () => ({
        loading: leaveLoading,
        myLeaveRequests,
        myLeaveBalances,
        error: null,
        fetchMyLeaveRequests,
        fetchMyLeaveBalances,
        createLeaveRequest,
        uploadProof,
    }),
}));

vi.mock("@/stores/option", () => ({
    useOptionStore: () => ({
        leaveTypes,
        fetchLeaveTypes,
    }),
}));

vi.mock("@/stores/attendanceCorrection", () => ({
    useAttendanceCorrectionStore: () => ({
        loading: correctionLoading,
        myCorrections,
        fetchMyCorrections,
        requestCorrection,
    }),
}));

vi.mock("@/stores/auth", () => ({
    useAuthStore: () => ({
        user: {
            employee_profile: {
                job_information: {
                    work_location: mockWorkLocation,
                },
            },
        },
    }),
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        storeToRefs: (store) => store,
    };
});

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: (...args) => toastSuccessFn?.(...args),
        warning: (...args) => toastWarningFn?.(...args),
        error: (...args) => toastErrorFn?.(...args),
    }),
}));

vi.mock("@/helpers/permissionHelper", () => ({
    can: (permission) => {
        if (permission === "leave-request-create") return hasLeaveRequestPermission;
        if (permission === "attendance-check-in" || permission === "attendance-check-out") return hasClockPermission;
        return false;
    },
}));

vi.mock("vue-router", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        useRoute: () => currentRoute,
        useRouter: () => ({
            replace: routerReplace,
        }),
    };
});

import MyAttendance from "@/views/staff-member/MyAttendance.vue";

const flushPromises = async () => {
    await Promise.resolve();
    await Promise.resolve();
};

const factory = () =>
    mount(MyAttendance, {
        global: {
            stubs: {
                Teleport: true,
                AttendanceStatsCards: {
                    template: '<div class="attendance-stats-stub" />',
                },
                LeaveRequestSuccessModal: {
                    template: '<div class="leave-success-modal-stub" />',
                },
                EmptyState: {
                    template: '<div class="empty-state-stub" />',
                },
                ClockInOut: {
                    template: '<div class="clock-in-out-stub" />',
                },
                AttendanceCorrectionsList: {
                    template: '<div class="corrections-list-stub" />',
                },
                AttendanceCorrectionModal: {
                    template: '<div class="correction-modal-stub" />',
                },
                MyOvertime: {
                    template: '<div class="my-overtime-stub" />',
                },
                HybridSchedules: {
                    template: '<div class="hybrid-schedules-stub" />',
                },
            },
        },
    });

describe("MyAttendance - Leave Request Validation", () => {
    beforeEach(() => {
        attendanceLoading.value = false;
        attendances.value = [];
        statistics.value = {};
        todayAttendance.value = null;
        leaveLoading.value = false;
        myLeaveRequests.value = [];
        myLeaveBalances.value = [];
        leaveTypes.value = [];
        correctionLoading.value = false;
        myCorrections.value = [];
        hasLeaveRequestPermission = true;
        hasClockPermission = false;
        mockWorkLocation = "office";
        currentRoute = {
            name: "staffMember.attendance.my-attendances",
            params: {},
            query: {},
            hash: "",
        };
        toastWarningFn = vi.fn();
        toastErrorFn = vi.fn();
        toastSuccessFn = vi.fn();
        fetchAttendances.mockClear();
        fetchStatistics.mockClear();
        fetchTodayAttendance.mockClear();
        checkIn.mockClear();
        checkOut.mockClear();
        fetchMyLeaveRequests.mockClear();
        fetchMyLeaveBalances.mockClear();
        createLeaveRequest.mockClear();
        fetchLeaveTypes.mockClear();
        fetchMyCorrections.mockClear();
        routerReplace.mockClear();
    });

    it("rejects leave request when end date is before start date", async () => {
        const wrapper = factory();
        await flushPromises();
        await wrapper.vm.$nextTick();

        // Open the leave request modal
        wrapper.vm.showLeaveRequestModal = true;
        await wrapper.vm.$nextTick();

        // Set form values with end date before start date
        wrapper.vm.leaveForm.leave_type = "annual_leave";
        wrapper.vm.leaveForm.start_date = "2026-06-10";
        wrapper.vm.leaveForm.end_date = "2026-06-05";
        wrapper.vm.leaveForm.reason = "Vacation";

        await wrapper.vm.submitLeaveRequest();
        await flushPromises();

        expect(createLeaveRequest).not.toHaveBeenCalled();
        expect(toastWarningFn).toHaveBeenCalledWith("Invalid Date", "End date must be after start date.");
    });

    it("rejects sick leave without proof file", async () => {
        const wrapper = factory();
        await flushPromises();
        await wrapper.vm.$nextTick();

        wrapper.vm.showLeaveRequestModal = true;
        await wrapper.vm.$nextTick();

        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const tomorrowStr = tomorrow.toISOString().split("T")[0];

        wrapper.vm.leaveForm.leave_type = "sick_leave";
        wrapper.vm.leaveForm.start_date = tomorrowStr;
        wrapper.vm.leaveForm.end_date = tomorrowStr;
        wrapper.vm.leaveForm.reason = "Not feeling well";
        wrapper.vm.leaveForm.proof_file = null;

        await wrapper.vm.submitLeaveRequest();
        await flushPromises();

        expect(createLeaveRequest).not.toHaveBeenCalled();
        expect(toastWarningFn).toHaveBeenCalledWith(
            "Missing Proof",
            "A medical certificate or proof is required for sick leave.",
        );
    });

    it("rejects proof file larger than 5MB", async () => {
        const wrapper = factory();
        await flushPromises();
        await wrapper.vm.$nextTick();

        const largeFile = new File(["x".repeat(6 * 1024 * 1024)], "large.pdf", {
            type: "application/pdf",
        });

        const mockEvent = {
            target: {
                files: [largeFile],
                value: "",
            },
        };

        wrapper.vm.handleProofFileChange(mockEvent);

        expect(toastWarningFn).toHaveBeenCalledWith("File Too Large", "Proof file must be less than 5MB.");
        expect(wrapper.vm.leaveForm.proof_file).toBeNull();
    });

    it("accepts proof file under 5MB", async () => {
        const wrapper = factory();
        await flushPromises();
        await wrapper.vm.$nextTick();

        const smallFile = new File(["x".repeat(1 * 1024 * 1024)], "proof.pdf", {
            type: "application/pdf",
        });

        const mockEvent = {
            target: {
                files: [smallFile],
                value: "",
            },
        };

        wrapper.vm.handleProofFileChange(mockEvent);

        expect(wrapper.vm.leaveForm.proof_file).toBe(smallFile);
    });
});

describe("MyAttendance - isClockOutDisabled", () => {
    beforeEach(() => {
        attendanceLoading.value = false;
        todayAttendance.value = null;
        hasClockPermission = true;
        mockWorkLocation = "office";
        toastWarningFn = vi.fn();
        toastErrorFn = vi.fn();
        toastSuccessFn = vi.fn();
    });

    it("returns true when not checked in", async () => {
        todayAttendance.value = { check_in: null, check_out: null };
        const wrapper = factory();
        await flushPromises();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isClockOutDisabled).toBe(true);
    });

    it("returns true when already checked out", async () => {
        const now = new Date();
        todayAttendance.value = {
            check_in: new Date(now.getTime() - 10 * 60 * 60 * 1000).toISOString(),
            check_out: new Date(now.getTime() - 1 * 60 * 60 * 1000).toISOString(),
        };
        const wrapper = factory();
        await flushPromises();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isClockOutDisabled).toBe(true);
    });

    it("returns true when checked in less than 8 hours ago", async () => {
        const now = Date.now();
        const twoHoursAgo = new Date(now - 2 * 60 * 60 * 1000).toISOString();
        todayAttendance.value = { check_in: twoHoursAgo, check_out: null };
        const wrapper = factory();
        await flushPromises();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isClockOutDisabled).toBe(true);
    });

    it("returns false when checked in more than 8 hours ago (uses reactive Date.now())", async () => {
        const now = Date.now();
        const nineHoursAgo = new Date(now - 9 * 60 * 60 * 1000).toISOString();
        todayAttendance.value = { check_in: nineHoursAgo, check_out: null };
        const wrapper = factory();
        await flushPromises();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isClockOutDisabled).toBe(false);
    });

    it("returns true when attendance is loading", async () => {
        attendanceLoading.value = true;
        const now = Date.now();
        const nineHoursAgo = new Date(now - 9 * 60 * 60 * 1000).toISOString();
        todayAttendance.value = { check_in: nineHoursAgo, check_out: null };
        const wrapper = factory();
        await flushPromises();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isClockOutDisabled).toBe(true);
    });
});
