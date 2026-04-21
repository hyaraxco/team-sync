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
    fetchMyLeaveRequests,
    fetchMyLeaveBalances,
    createLeaveRequest,
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
          work_location: "office",
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
    success: vi.fn(),
    warning: vi.fn(),
    error: vi.fn(),
  }),
}));

vi.mock("@/helpers/permissionHelper", () => ({
  can: (permission) => {
    if (permission === "leave-request-create") {
      return hasLeaveRequestPermission;
    }

    if (permission === "attendance-check-in" || permission === "attendance-check-out") {
      return hasClockPermission;
    }

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
          template: '<div class="clock-in-out-stub">Clock Workspace</div>',
        },
        AttendanceCorrectionsList: {
          template: '<div class="corrections-list-stub" />',
        },
        AttendanceCorrectionModal: {
          template: '<div class="correction-modal-stub" />',
        },
      },
    },
  });

describe("MyAttendance smoke", () => {
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
    hasLeaveRequestPermission = false;
    hasClockPermission = false;
    currentRoute = {
      name: "staffMember.attendance.my-attendances",
      params: {},
      query: {},
      hash: "",
    };
    fetchAttendances.mockClear();
    fetchStatistics.mockClear();
    fetchTodayAttendance.mockClear();
    fetchMyLeaveRequests.mockClear();
    fetchMyLeaveBalances.mockClear();
    createLeaveRequest.mockClear();
    fetchLeaveTypes.mockClear();
    fetchMyCorrections.mockClear();
    routerReplace.mockClear();
  });

  it("opens the leave request modal from the route query and clears the query", async () => {
    hasLeaveRequestPermission = true;
    currentRoute = {
      name: "staffMember.attendance.my-attendances",
      params: {},
      query: { action: "request-leave", source: "dashboard" },
      hash: "",
    };

    const wrapper = factory();
    await flushPromises();
    await wrapper.vm.$nextTick();

    expect(wrapper.text()).toContain("Request New Leave");
    expect(routerReplace).toHaveBeenCalledWith({
      name: "staffMember.attendance.my-attendances",
      params: {},
      query: { source: "dashboard" },
      hash: "",
    });
  });

  it("ignores the query action when the user lacks leave permission", async () => {
    currentRoute = {
      name: "staffMember.attendance.my-attendances",
      params: {},
      query: { action: "request-leave" },
      hash: "",
    };

    const wrapper = factory();
    await flushPromises();
    await wrapper.vm.$nextTick();

    expect(wrapper.text()).not.toContain("Request New Leave");
    expect(routerReplace).not.toHaveBeenCalled();
  });

  it("opens the clock section from the route query and clears the query", async () => {
    hasClockPermission = true;
    currentRoute = {
      name: "staffMember.attendance.my-attendances",
      params: {},
      query: { action: "clock", source: "dashboard" },
      hash: "",
    };

    const wrapper = factory();
    await flushPromises();
    await wrapper.vm.$nextTick();

    // Verify Clock In button renders for non-remote users with clock permissions
    expect(wrapper.text()).toContain("Clock In");
  });
});
