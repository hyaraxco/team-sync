import { mount } from "@vue/test-utils";
import { describe, it, expect, vi, beforeEach } from "vitest";
import { createPinia, setActivePinia } from "pinia";
import { ref } from "vue";

const paginatedSchedules = ref([]);
const loading = ref(false);
const error = ref(null);

const fetchAllPaginated = vi.fn().mockResolvedValue(undefined);
const approveOverride = vi.fn().mockResolvedValue(undefined);
const rejectOverride = vi.fn().mockResolvedValue(undefined);

vi.mock("@/stores/hybridSchedule", () => ({
    useHybridScheduleStore: () => ({
        paginatedSchedules,
        loading,
        error,
        fetchAllPaginated,
        approveOverride,
        rejectOverride,
    }),
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        storeToRefs: (store) => store,
    };
});

import HybridScheduleList from "@/views/admin/attendance/HybridScheduleList.vue";

describe("HybridScheduleList smoke", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        paginatedSchedules.value = [];
        loading.value = false;
        error.value = null;
    });

    const createWrapper = () => mount(HybridScheduleList);

    it("renders without crashing", () => {
        const wrapper = createWrapper();
        expect(wrapper.exists()).toBe(true);
    });

    it("does not render duplicate local h1 title", () => {
        const wrapper = createWrapper();
        expect(wrapper.find("h1").exists()).toBe(false);
    });

    it("uses CSS variable for surface background", () => {
        const wrapper = createWrapper();
        expect(wrapper.html()).toContain("var(--color-surface)");
    });

    it("calls fetchAllPaginated on mount", () => {
        createWrapper();
        expect(fetchAllPaginated).toHaveBeenCalled();
    });

    it("displays tab navigation", () => {
        const wrapper = createWrapper();
        expect(wrapper.text()).toContain("Schedules");
        expect(wrapper.text()).toContain("Override Requests");
    });
});
