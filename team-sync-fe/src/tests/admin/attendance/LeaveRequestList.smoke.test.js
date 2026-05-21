import { mount } from "@vue/test-utils";
import { describe, it, expect, vi, beforeEach } from "vitest";
import { createPinia, setActivePinia } from "pinia";
import { ref } from "vue";
import { DateTime } from "luxon";

const leaveRequests = ref([]);
const calendarData = ref([]);
const loading = ref(false);
const error = ref(null);
const meta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 });

const fetchLeaveRequestsPaginated = vi.fn().mockResolvedValue(undefined);
const fetchCalendarData = vi.fn().mockResolvedValue(undefined);
const approveLeaveRequest = vi.fn().mockResolvedValue(undefined);
const rejectLeaveRequest = vi.fn().mockResolvedValue(undefined);

vi.mock("@/stores/leaveRequest", () => ({
    useLeaveRequestStore: () => ({
        leaveRequests,
        calendarData,
        loading,
        error,
        meta,
        fetchLeaveRequestsPaginated,
        fetchCalendarData,
        approveLeaveRequest,
        rejectLeaveRequest,
    }),
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        storeToRefs: (store) => store,
    };
});

// Mock Lucide icons with importOriginal to cover all icons from child components
vi.mock("lucide-vue-next", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
    };
});

import LeaveRequestList from "@/views/admin/attendance/LeaveRequestList.vue";

describe("LeaveRequestList smoke", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        leaveRequests.value = [];
        calendarData.value = [];
        loading.value = false;
        error.value = null;
        meta.value = { current_page: 1, last_page: 1, per_page: 10, total: 0 };
    });

    const createWrapper = () => {
        return mount(LeaveRequestList, {
            global: {
                stubs: {
                    RouterLink: true,
                    SearchFilter: true,
                    Pagination: true,
                    StatusBadge: true,
                },
            },
        });
    };

    it("renders correctly", () => {
        const wrapper = createWrapper();
        expect(wrapper.exists()).toBe(true);
    });

    it("contains header text", () => {
        const wrapper = createWrapper();
        expect(wrapper.text()).toContain("Pengajuan Cuti");
    });

    it("fetches paginated data on mount (list view is default)", () => {
        createWrapper();
        expect(fetchLeaveRequestsPaginated).toHaveBeenCalled();
    });

    it("can switch to calendar view and fetches month data", async () => {
        const wrapper = createWrapper();

        // Default is list, calendar tab shouldn't have been called yet
        expect(fetchCalendarData).not.toHaveBeenCalled();

        // Find and click calendar tab button
        const buttons = wrapper.findAll("button");
        const calendarBtn = buttons.find((b) => b.text().includes("Calendar"));
        await calendarBtn.trigger("click");

        // Should fetch calendar data for current month
        const currentMonthStr = DateTime.now().startOf("month").toFormat("yyyy-MM");
        expect(fetchCalendarData).toHaveBeenCalledWith(currentMonthStr);
    });
});
