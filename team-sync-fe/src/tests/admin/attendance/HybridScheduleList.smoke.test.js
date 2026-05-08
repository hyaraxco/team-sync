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
        expect(wrapper.text()).toContain("Hybrid Work Schedules");
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
