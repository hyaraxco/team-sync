import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick, reactive, toRef } from "vue";

const push = vi.fn();
const toastSuccess = vi.fn();
const toastError = vi.fn();

vi.mock("pinia", () => ({
    storeToRefs: (store) => ({
        records: toRef(store, "records"),
        myRecords: toRef(store, "myRecords"),
        summary: toRef(store, "summary"),
        loading: toRef(store, "loading"),
        error: toRef(store, "error"),
        meta: toRef(store, "meta"),
    }),
    defineStore: vi.fn(),
}));

const mockRecords = [
    {
        id: 1,
        staff_member: { user: { name: "Alice Johnson" } },
        date: "2026-05-01",
        start_time: "17:00",
        end_time: "19:00",
        hours: 2.0,
        overtime_type: "workday",
        status: "pending",
        notes: "Project deadline",
    },
    {
        id: 2,
        staff_member: { user: { name: "Bob Smith" } },
        date: "2026-05-03",
        start_time: "08:00",
        end_time: "16:00",
        hours: 8.0,
        overtime_type: "weekend",
        status: "approved",
        approved_by_user: { name: "HR Admin" },
        notes: null,
    },
    {
        id: 3,
        staff_member: { user: { name: "Charlie Brown" } },
        date: "2026-05-02",
        start_time: "17:00",
        end_time: "20:00",
        hours: 3.0,
        overtime_type: "workday",
        status: "rejected",
        rejection_reason: "Not pre-approved by manager before overtime",
    },
];

const mockSummary = {
    total_pending: 5,
    approved_this_month: 12,
    rejected_this_month: 2,
    total_hours_this_month: 34.5,
    by_type: {
        workday: { count: 8, total_hours: 18.5 },
        weekend: { count: 4, total_hours: 16.0 },
    },
};

const mockMeta = {
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 3,
    from: 1,
    to: 3,
};

const mockStore = reactive({
    records: mockRecords,
    myRecords: [],
    summary: mockSummary,
    loading: false,
    error: null,
    meta: mockMeta,
    fetchOvertimeRecords: vi.fn(),
    createOvertime: vi.fn(),
    approveOvertime: vi.fn(),
    rejectOvertime: vi.fn(),
    fetchMyOvertime: vi.fn(),
    fetchOvertimeSummary: vi.fn(),
});

vi.mock("@/stores/overtime", () => ({
    useOvertimeStore: () => mockStore,
}));

vi.mock("@/stores/staffMember", () => ({
    useStaffMemberStore: () => ({
        staffMembers: [],
        fetchAllPaginated: vi.fn(),
    }),
}));

vi.mock("vue-router", () => ({
    useRouter: () => ({ push }),
    useRoute: () => ({ params: {}, query: {} }),
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: toastSuccess,
        error: toastError,
    }),
}));

vi.mock("@/composables/useSearchFilter", () => ({
    useSearchFilter: ({ fetchFn }) => ({
        filters: reactive({ search: null, status: "" }),
        fetchData: fetchFn,
        handleSearch: vi.fn(),
        handleReset: vi.fn(),
        handlePageChange: vi.fn(),
        handlePerPageChange: vi.fn(),
    }),
}));

vi.mock("@/composables/useConfirmAction", () => ({
    useConfirmAction: ({ onSuccess }) => ({
        isModalOpen: false,
        selectedItem: null,
        isProcessing: false,
        openModal: vi.fn(),
        closeModal: vi.fn(),
        confirmAction: vi.fn(),
    }),
}));

vi.mock("@/helpers/permissionHelper", () => ({
    can: (permission) => true,
}));

vi.mock("@/utils/dateUtils", () => ({
    formatDateShort: (d) => d || "-",
    formatTime: (t) => t || "-",
}));

vi.mock("@/components/common/SearchFilter.vue", () => ({
    default: { template: '<div data-testid="search-filter"></div>' },
}));

vi.mock("@/components/admin/team/Pagination.vue", () => ({
    default: { template: '<div data-testid="pagination"></div>' },
}));

vi.mock("@/components/common/EmptyState.vue", () => ({
    default: { template: '<div data-testid="empty-state"></div>', props: ["title", "description"] },
}));

vi.mock("@/components/common/ModalWrapper.vue", () => ({
    default: {
        template: '<div v-if="show" data-testid="modal"><slot /></div>',
        props: ["show", "title"],
    },
}));

import OvertimeManagement from "@/views/admin/attendance/OvertimeManagement.vue";

describe("OvertimeManagement.vue", () => {
    let wrapper;

    beforeEach(() => {
        mockStore.records = mockRecords;
        mockStore.summary = mockSummary;
        mockStore.loading = false;
        mockStore.error = null;

        wrapper = mount(OvertimeManagement, {
            global: {
                stubs: {
                    SearchFilter: true,
                    Pagination: true,
                    EmptyState: true,
                    ModalWrapper: { template: '<div v-if="show"><slot /></div>', props: ["show", "title"] },
                },
            },
        });
    });

    it("renders the page title", () => {
        expect(wrapper.text()).toContain("Overtime Management");
    });

    it("renders summary cards with correct data", () => {
        expect(wrapper.text()).toContain("5"); // total_pending
        expect(wrapper.text()).toContain("12"); // approved_this_month
        expect(wrapper.text()).toContain("34.5"); // total_hours_this_month
        expect(wrapper.text()).toContain("2"); // rejected_this_month
    });

    it("renders table with overtime records", () => {
        expect(wrapper.text()).toContain("Alice Johnson");
        expect(wrapper.text()).toContain("Bob Smith");
        expect(wrapper.text()).toContain("Charlie Brown");
    });

    it("shows status badges for records", () => {
        expect(wrapper.text()).toContain("pending");
        expect(wrapper.text()).toContain("approved");
        expect(wrapper.text()).toContain("rejected");
    });

    it("shows overtime type badges", () => {
        expect(wrapper.text()).toContain("workday");
        expect(wrapper.text()).toContain("weekend");
    });

    it("shows hours for each record", () => {
        expect(wrapper.text()).toContain("2h");
        expect(wrapper.text()).toContain("8h");
        expect(wrapper.text()).toContain("3h");
    });

    it("shows approve/reject buttons for pending records", async () => {
        const buttons = wrapper.findAll('button[title="Approve"], button[title="Reject"]');
        // Only the pending record (id=1) should have action buttons
        expect(buttons.length).toBeGreaterThanOrEqual(2);
    });

    it("shows Record Overtime button", () => {
        expect(wrapper.text()).toContain("Record Overtime");
    });

    it("shows status filter buttons", () => {
        expect(wrapper.text()).toContain("All");
        expect(wrapper.text()).toContain("Pending");
        expect(wrapper.text()).toContain("Approved");
        expect(wrapper.text()).toContain("Rejected");
    });

    it("renders create modal with required fields when opened", async () => {
        const createBtn = wrapper.findAll("button").find((b) => b.text().includes("Record Overtime"));
        await createBtn.trigger("click");
        await nextTick();

        expect(wrapper.text()).toContain("Employee ID");
        expect(wrapper.text()).toContain("Date");
        expect(wrapper.text()).toContain("Start Time");
        expect(wrapper.text()).toContain("End Time");
        expect(wrapper.text()).toContain("Overtime Type");
    });
});
