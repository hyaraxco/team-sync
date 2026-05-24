import { mount } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { createPinia, setActivePinia } from "pinia";
import { nextTick, ref } from "vue";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import EmptyState from "@/components/common/EmptyState.vue";

const paginatedHolidays = ref([]);
const loading = ref(false);
const error = ref(null);
const meta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 });

const fetchAllPaginated = vi.fn().mockResolvedValue(undefined);
const createHoliday = vi.fn().mockResolvedValue(undefined);
const updateHoliday = vi.fn().mockResolvedValue(undefined);
const deleteHoliday = vi.fn().mockResolvedValue(undefined);

const toastSuccessMock = vi.fn();
const toastErrorMock = vi.fn();

vi.mock("@/stores/holidayCalendar", () => ({
    useHolidayCalendarStore: () => ({
        paginatedHolidays,
        loading,
        error,
        meta,
        fetchAllPaginated,
        createHoliday,
        updateHoliday,
        deleteHoliday,
    }),
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: toastSuccessMock,
        error: toastErrorMock,
    }),
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        storeToRefs: (store) => store,
    };
});

import HolidayCalendar from "@/views/admin/attendance/HolidayCalendar.vue";

describe("HolidayCalendar smoke", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();

        paginatedHolidays.value = [];
        loading.value = false;
        error.value = null;
        meta.value = { current_page: 1, last_page: 1, per_page: 10, total: 0 };
    });

    const createWrapper = () =>
        mount(HolidayCalendar, {
            global: {
                stubs: {
                    MainCard: {
                        template: "<div><slot /></div>",
                    },
                    EmptyState: true,
                    StatusBadge: true,
                    Pagination: true,
                    teleport: true,
                },
            },
        });

    it("renders without crashing", () => {
        const wrapper = createWrapper();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetchAllPaginated on mount", async () => {
        createWrapper();
        await nextTick();
        expect(fetchAllPaginated).toHaveBeenCalled();
    });

    it("displays Add Holiday button", () => {
        const wrapper = createWrapper();
        expect(wrapper.text()).toContain("Add Holiday");
    });

    it("preserves semantic heading without visible duplicate local h1", () => {
        const wrapper = createWrapper();
        const semanticHeading = wrapper.find('[role="heading"][aria-level="1"]');

        expect(semanticHeading.exists()).toBe(true);
        expect(semanticHeading.text()).toBe("Holiday Calendar");
        expect(semanticHeading.classes()).toContain("sr-only");
        expect(wrapper.findAll("h1")).toHaveLength(0);
    });

    it("renders English empty state", () => {
        const wrapper = createWrapper();
        const emptyState = wrapper.findComponent(EmptyState);

        expect(emptyState.exists()).toBe(true);
        expect(emptyState.props("title")).toBe("No holidays found");
        expect(emptyState.props("subtitle")).toBe("Add a holiday to start building the company calendar.");
    });

    it("Add Holiday button opens modal", async () => {
        const wrapper = createWrapper();
        const modals = wrapper.findAllComponents(ModalWrapper);

        expect(modals[0].props("show")).toBe(false);

        const addHolidayButton = wrapper.findAll("button").find((button) => button.text().includes("Add Holiday"));
        expect(addHolidayButton).toBeTruthy();

        await addHolidayButton.trigger("click");
        await nextTick();

        const updatedModals = wrapper.findAllComponents(ModalWrapper);
        expect(updatedModals[0].props("show")).toBe(true);
    });
});
