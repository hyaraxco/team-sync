import { mount } from "@vue/test-utils";
import { describe, it, expect, vi, beforeEach } from "vitest";
import { ref } from "vue";

const paginatedCorrections = ref([]);
const loading = ref(false);
const error = ref(null);
const meta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 });

const fetchAllPaginated = vi.fn().mockResolvedValue(undefined);
const approveCorrection = vi.fn().mockResolvedValue(undefined);
const rejectCorrection = vi.fn().mockResolvedValue(undefined);

vi.mock("@/stores/attendanceCorrection", () => ({
    useAttendanceCorrectionStore: () => ({
        paginatedCorrections,
        loading,
        error,
        meta,
        fetchAllPaginated,
        approveCorrection,
        rejectCorrection,
    }),
}));

vi.mock("@/helpers/permissionHelper", () => ({
    can: () => true,
    canOneOf: () => true,
}));

import AttendanceCorrectionList from "@/views/admin/attendance/AttendanceCorrectionList.vue";

describe("AttendanceCorrectionList smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        paginatedCorrections.value = [];
        loading.value = false;
        error.value = null;
        meta.value = { current_page: 1, last_page: 1, per_page: 10, total: 0 };
    });

    const createWrapper = () => {
        return mount(AttendanceCorrectionList, {
            global: {
                stubs: {
                    RouterLink: { template: "<a><slot /></a>" },
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
        expect(wrapper.text()).toContain("Attendance Corrections");
        const pageHeading = wrapper.find('[role="heading"][aria-level="1"]');
        expect(pageHeading.exists()).toBe(true);
        expect(pageHeading.text()).toBe("Attendance Corrections");
        expect(pageHeading.classes()).toContain("sr-only");
        expect(wrapper.find("h1").exists()).toBe(false);
    });

    it("keeps standardized list helpers", () => {
        const wrapper = createWrapper();

        expect(wrapper.findComponent({ name: "SearchFilter" }).exists()).toBe(true);
        expect(wrapper.findComponent({ name: "EmptyState" }).exists()).toBe(true);
        expect(wrapper.find('[role="heading"][aria-level="1"]').text()).toBe("Attendance Corrections");
        expect(wrapper.find("h1").exists()).toBe(false);
    });

    it("fetches data on mount", () => {
        createWrapper();
        expect(fetchAllPaginated).toHaveBeenCalled();
    });

    describe("modal interactions", () => {
        it("shows approve modal when approve button is clicked", async () => {
            paginatedCorrections.value = [
                {
                    id: "1",
                    status: "pending",
                    requested_check_in: "09:00",
                    requested_check_out: "18:00",
                    reason: "forgot",
                },
            ];
            meta.value = { current_page: 1, last_page: 1, per_page: 10, total: 1 };

            const wrapper = mount(AttendanceCorrectionList, {
                global: {
                    stubs: {
                        RouterLink: { template: "<a><slot /></a>" },
                        SearchFilter: { template: "<div />" },
                        Pagination: { template: "<div />" },
                        Alert: { template: "<div />" },
                        ModalWrapper: { template: "<div><slot /></div>" },
                        EmptyState: { template: "<div />" },
                    },
                },
            });

            // Find approve button
            const buttons = wrapper.findAll("button");
            const approveBtn = buttons.find((b) => b.text().includes("Approve"));

            expect(approveBtn.exists()).toBe(true);
            await approveBtn.trigger("click");

            // In this smoke test we just verify it doesn't crash on trigger
        });

        it("shows reject modal when reject button is clicked", async () => {
            paginatedCorrections.value = [
                {
                    id: "1",
                    status: "pending",
                    requested_check_in: "09:00",
                    requested_check_out: "18:00",
                    reason: "forgot",
                },
            ];
            meta.value = { current_page: 1, last_page: 1, per_page: 10, total: 1 };

            const wrapper = mount(AttendanceCorrectionList, {
                global: {
                    stubs: {
                        RouterLink: { template: "<a><slot /></a>" },
                        SearchFilter: { template: "<div />" },
                        Pagination: { template: "<div />" },
                        Alert: { template: "<div />" },
                        ModalWrapper: { template: "<div><slot /></div>" },
                        EmptyState: { template: "<div />" },
                    },
                },
            });

            // Find reject button
            const buttons = wrapper.findAll("button");
            const rejectBtn = buttons.find((b) => b.text().includes("Reject"));

            expect(rejectBtn.exists()).toBe(true);
            await rejectBtn.trigger("click");
        });
    });
});
