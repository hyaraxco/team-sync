import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick, ref } from "vue";

// 1. State refs at top
const myRecords = ref([]);
const meta = ref({
    current_page: 1,
    last_page: 1,
    per_page: 12,
    total: 0,
    from: null,
    to: null,
});
const loading = ref(false);
const error = ref(null);

// 2. Action mocks
const fetchMyOvertime = vi.fn().mockResolvedValue(undefined);
const push = vi.fn();

// 3. Mocks
vi.mock("@/stores/overtime", () => ({
    useOvertimeStore: () => ({
        fetchMyOvertime,
    }),
}));

vi.mock("lodash", () => ({
    debounce: (fn) => fn,
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();

    return {
        ...actual,
        storeToRefs: () => ({
            myRecords,
            meta,
            loading,
            error,
        }),
    };
});

vi.mock("vue-router", () => ({
    useRouter: () => ({
        push,
    }),
    useRoute: () => ({
        params: {},
        query: {},
        name: "staff-member.my-overtime",
    }),
    createRouter: vi.fn(() => ({
        push,
    })),
    createWebHistory: vi.fn(),
}));

// 4. Import view AFTER mocks
import MyOvertime from "@/views/staff-member/MyOvertime.vue";

// 5. Factory
const factory = () =>
    mount(MyOvertime, {
        global: {
            stubs: {
                RouterLink: {
                    props: ["to"],
                    template: "<a><slot /></a>",
                },
                Pagination: {
                    props: ["meta", "loading"],
                    template: '<div class="pagination-stub"><slot /></div>',
                },
                EmptyState: {
                    props: ["icon", "title", "subtitle"],
                    template: '<div class="empty-state-stub">{{ title }}</div>',
                },
            },
        },
    });

describe("MyOvertime smoke", () => {
    beforeEach(() => {
        myRecords.value = [];
        meta.value = {
            current_page: 1,
            last_page: 1,
            per_page: 12,
            total: 0,
            from: null,
            to: null,
        };
        loading.value = false;
        error.value = null;
        fetchMyOvertime.mockClear();
        push.mockClear();
    });

    it("renders the page title and stats cards", async () => {
        const wrapper = factory();
        await nextTick();

        expect(wrapper.text()).toContain("My Overtime");
        expect(wrapper.text()).toContain("Track submitted overtime hours, approvals, and rejection notes");
        expect(wrapper.text()).toContain("Loaded Hours");
        expect(wrapper.text()).toContain("Approved Hours");
        expect(wrapper.text()).toContain("Pending Records");
        expect(wrapper.text()).toContain("Rejected Records");
    });

    it("fetches overtime data on mount", async () => {
        const wrapper = factory();
        await nextTick();
        await Promise.resolve();

        expect(fetchMyOvertime).toHaveBeenCalledTimes(1);
        expect(fetchMyOvertime).toHaveBeenCalledWith({
            page: 1,
            per_page: 12,
            status: "",
        });
    });

    it("shows empty state when no records exist", async () => {
        const wrapper = factory();
        await nextTick();
        await Promise.resolve();

        expect(wrapper.text()).toContain("No overtime records found");
    });

    it("renders overtime records when data is loaded", async () => {
        myRecords.value = [
            {
                id: 1,
                overtime_type: "weekday",
                date: "2026-05-10",
                start_time: "18:00:00",
                end_time: "21:00:00",
                hours: 3,
                status: "approved",
                notes: "Project deadline",
            },
            {
                id: 2,
                overtime_type: "weekend",
                date: "2026-05-11",
                start_time: "09:00:00",
                end_time: "13:00:00",
                hours: 4,
                status: "pending",
                notes: null,
            },
        ];
        meta.value = {
            current_page: 1,
            last_page: 1,
            per_page: 12,
            total: 2,
            from: 1,
            to: 2,
        };
        const wrapper = factory();
        await nextTick();

        expect(wrapper.text()).toContain("Weekday");
        expect(wrapper.text()).toContain("Weekend");
        expect(wrapper.text()).toContain("Approved");
        expect(wrapper.text()).toContain("Pending");
        expect(wrapper.text()).toContain("3.0h");
        expect(wrapper.text()).toContain("4.0h");
        expect(wrapper.find('[data-testid="my-overtime-record-1"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="my-overtime-record-2"]').exists()).toBe(true);
    });

    it("shows error message when error exists", async () => {
        error.value = "Server error";
        const wrapper = factory();
        await nextTick();

        expect(wrapper.text()).toContain("Unable to load overtime records");
        expect(wrapper.text()).toContain("Server error");
    });
});
