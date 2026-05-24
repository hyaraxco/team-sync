import { mount } from "@vue/test-utils";
import { describe, it, expect, vi, beforeEach } from "vitest";
import { defineComponent, ref } from "vue";

const RouterLinkStub = defineComponent({ name: "RouterLink", template: "<a><slot /></a>" });
const SearchFilterStub = defineComponent({ name: "SearchFilter", template: "<div />" });
const PaginationStub = defineComponent({ name: "Pagination", template: "<div />" });
const AlertStub = defineComponent({ name: "Alert", template: "<div />" });
const DataTableCardStub = defineComponent({
    name: "DataTableCard",
    template: "<div><slot /></div>",
});
const TableStateRowsStub = defineComponent({
    name: "TableStateRows",
    props: ["loading", "empty", "colspan"],
    template: "<tr v-if=\"loading || empty\"><td>stub</td></tr>",
});
const EmployeeCellStub = defineComponent({
    name: "EmployeeCell",
    props: ["photo", "name", "subtitle"],
    template: "<div>{{ name }}</div>",
});
const ModalFooterActionsStub = defineComponent({
    name: "ModalFooterActions",
    props: ["processing", "confirmLabel", "confirmColor", "confirmDisabled"],
    emits: ["cancel", "confirm"],
    template: `<div data-test="modal-footer-actions"><button @click="$emit('cancel')">Cancel</button><button :disabled="confirmDisabled || processing" @click="$emit('confirm')">{{ processing ? 'Processing...' : confirmLabel }}</button></div>`,
});
const ModalWrapperStub = defineComponent({
    name: "ModalWrapper",
    props: ["show", "title"],
    template: `
        <section v-if="show" data-test="modal-wrapper">
            <p data-test="modal-title">{{ title }}</p>
            <slot />
            <footer data-test="modal-footer"><slot name="footer" /></footer>
        </section>
    `,
});
const EmptyStateStub = defineComponent({ name: "EmptyState", template: "<div />" });
const StatusBadgeStub = defineComponent({
    name: "StatusBadge",
    template: '<span data-test="status-badge"><slot /></span>',
});

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
                    RouterLink: RouterLinkStub,
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
        expect(wrapper.findComponent({ name: "DataTableCard" }).exists()).toBe(true);
        expect(wrapper.find('[role="heading"][aria-level="1"]').text()).toBe("Attendance Corrections");
        expect(wrapper.find("h1").exists()).toBe(false);
    });

    it("uses shared StatusBadge for correction status", () => {
        paginatedCorrections.value = [
            {
                id: "1",
                status: "approved",
                requested_check_in: "2026-04-20T09:00:00Z",
                requested_check_out: "2026-04-20T18:00:00Z",
                reason: "forgot",
            },
        ];
        meta.value = { current_page: 1, last_page: 1, per_page: 10, total: 1 };

        const wrapper = mount(AttendanceCorrectionList, {
            global: {
                stubs: {
                    RouterLink: RouterLinkStub,
                    SearchFilter: SearchFilterStub,
                    Pagination: PaginationStub,
                    Alert: AlertStub,
                    ModalWrapper: ModalWrapperStub,
                    EmptyState: EmptyStateStub,
                    StatusBadge: StatusBadgeStub,
                    DataTableCard: DataTableCardStub,
                    TableStateRows: TableStateRowsStub,
                    EmployeeCell: EmployeeCellStub,
                    ModalFooterActions: ModalFooterActionsStub,
                },
            },
        });

        expect(wrapper.findComponent({ name: "StatusBadge" }).exists()).toBe(true);
        expect(wrapper.find('[data-test="status-badge"]').exists()).toBe(true);
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
                    requested_check_in: "2026-04-20 09:00:00",
                    requested_check_out: "2026-04-20 16:00:00",
                    reason: "forgot",
                },
            ];
            meta.value = { current_page: 1, last_page: 1, per_page: 10, total: 1 };

            const wrapper = mount(AttendanceCorrectionList, {
                global: {
                    stubs: {
                        RouterLink: RouterLinkStub,
                        SearchFilter: SearchFilterStub,
                        Pagination: PaginationStub,
                        Alert: AlertStub,
                        ModalWrapper: ModalWrapperStub,
                        EmptyState: EmptyStateStub,
                        StatusBadge: StatusBadgeStub,
                        DataTableCard: DataTableCardStub,
                        TableStateRows: TableStateRowsStub,
                        EmployeeCell: EmployeeCellStub,
                        ModalFooterActions: ModalFooterActionsStub,
                    },
                },
            });

            const approveBtn = wrapper.find('[aria-label="Approve correction"]');

            expect(approveBtn.exists()).toBe(true);
            await approveBtn.trigger("click");

            expect(wrapper.find('[data-test="modal-wrapper"]').exists()).toBe(true);
            expect(wrapper.find('[data-test="modal-title"]').text()).toBe("Approve Correction");
            expect(wrapper.text()).toContain("Confirm approval for this attendance correction.");
            expect(wrapper.text()).toContain("Requested In:");
            expect(wrapper.text()).toContain("16:00");
            expect(wrapper.text()).toContain('"forgot"');
            expect(wrapper.find('[data-test="modal-footer"]').text()).toContain("Cancel");
            expect(wrapper.find('[data-test="modal-footer"]').text()).toContain("Approve");
        });

        it("shows reject modal when reject button is clicked", async () => {
            paginatedCorrections.value = [
                {
                    id: "1",
                    status: "pending",
                    requested_check_in: "2026-04-20 09:00:00",
                    requested_check_out: "2026-04-20 16:00:00",
                    reason: "forgot",
                },
            ];
            meta.value = { current_page: 1, last_page: 1, per_page: 10, total: 1 };

            const wrapper = mount(AttendanceCorrectionList, {
                global: {
                    stubs: {
                        RouterLink: RouterLinkStub,
                        SearchFilter: SearchFilterStub,
                        Pagination: PaginationStub,
                        Alert: AlertStub,
                        ModalWrapper: ModalWrapperStub,
                        EmptyState: EmptyStateStub,
                        StatusBadge: StatusBadgeStub,
                        DataTableCard: DataTableCardStub,
                        TableStateRows: TableStateRowsStub,
                        EmployeeCell: EmployeeCellStub,
                        ModalFooterActions: ModalFooterActionsStub,
                    },
                },
            });

            const rejectBtn = wrapper.find('[aria-label="Reject correction"]');

            expect(rejectBtn.exists()).toBe(true);
            await rejectBtn.trigger("click");

            expect(wrapper.find('[data-test="modal-wrapper"]').exists()).toBe(true);
            expect(wrapper.find('[data-test="modal-title"]').text()).toBe("Reject Correction");
            expect(wrapper.text()).toContain("Confirm rejection for this attendance correction.");
            expect(wrapper.text()).toContain("Requested In");
            expect(wrapper.text()).toContain("16:00");
            expect(wrapper.find("textarea").exists()).toBe(true);
            const modalFooter = wrapper.find('[data-test="modal-footer"]');
            expect(modalFooter.text()).toContain("Cancel");
            expect(modalFooter.text()).toContain("Reject");
        });
    });
});
