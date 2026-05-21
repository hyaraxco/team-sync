import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick, reactive } from "vue";

const authState = reactive({
    permissions: [],
});

const thrStore = reactive({
    thrPayrolls: [],
    yearSummary: {
        total_events: 1,
        total_employees: 10,
        total_thr_amount: 100000000,
        total_net_amount: 95000000,
    },
    simulation: null,
    meta: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    },
    loading: false,
    error: null,
    fetchThrPayrolls: vi.fn(),
    fetchYearSummary: vi.fn(),
    simulate: vi.fn(),
    generate: vi.fn(),
    approve: vi.fn(),
    markAsPaid: vi.fn(),
});

const toastSuccess = vi.fn();
const toastError = vi.fn();

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();
    const { toRefs } = await import("vue");

    return {
        ...actual,
        storeToRefs: (store) => toRefs(store),
    };
});

vi.mock("@/stores/auth", () => ({
    useAuthStore: () => ({
        user: {
            permissions: authState.permissions,
        },
    }),
}));

vi.mock("@/stores/thr", () => ({
    useThrStore: () => thrStore,
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: toastSuccess,
        error: toastError,
    }),
}));

vi.mock("@/components/admin/team/Pagination.vue", () => ({
    default: {
        name: "Pagination",
        props: ["meta"],
        emits: ["page-change"],
        template: '<nav data-testid="pagination"></nav>',
    },
}));

vi.mock("@/components/common/EmptyState.vue", () => ({
    default: {
        name: "EmptyState",
        props: ["title", "description"],
        template: '<div data-testid="empty-state"><h2>{{ title }}</h2><p>{{ description }}</p></div>',
    },
}));

vi.mock("@/components/common/ModalWrapper.vue", () => ({
    default: {
        name: "ModalWrapper",
        props: ["show", "title"],
        emits: ["close"],
        template: '<section v-if="show" data-testid="modal"><h2>{{ title }}</h2><slot /></section>',
    },
}));

vi.mock("@/utils/dateUtils", () => ({
    formatDateShort: (value) => value,
}));

import ThrManagement from "@/views/admin/payroll/ThrManagement.vue";

const pendingThr = {
    id: 1,
    event_label: "Idul Fitri",
    year: 2026,
    religion_holiday_date: "2026-03-20",
    payment_deadline: "2026-03-13",
    total_employees: 10,
    total_net_amount: 95000000,
    status: "pending",
};

const approvedThr = {
    ...pendingThr,
    id: 2,
    status: "approved",
};

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

const factory = () =>
    mount(ThrManagement, {
        global: {
            stubs: {
                RouterLink: {
                    props: ["to"],
                    template: "<a><slot /></a>",
                },
            },
        },
    });

describe("ThrManagement smoke", () => {
    beforeEach(() => {
        authState.permissions = [];
        thrStore.thrPayrolls = [pendingThr, approvedThr];
        thrStore.meta = {
            current_page: 1,
            last_page: 1,
            per_page: 15,
            total: 2,
        };
        thrStore.loading = false;
        thrStore.error = null;
        thrStore.fetchThrPayrolls = vi.fn(async () => ({ data: thrStore.thrPayrolls }));
        thrStore.fetchYearSummary = vi.fn(async () => thrStore.yearSummary);
        thrStore.simulate = vi.fn();
        thrStore.generate = vi.fn();
        thrStore.approve = vi.fn();
        thrStore.markAsPaid = vi.fn();
        toastSuccess.mockClear();
        toastError.mockClear();
    });

    it("shows HR generate action without finance approval or payment processing actions", async () => {
        authState.permissions = ["thr-list", "thr-generate"];

        const wrapper = factory();
        await flushAsync();

        expect(wrapper.get("h1").text()).toBe("Manajemen THR");
        expect(wrapper.findAll("button").some((button) => button.text() === "Generate THR")).toBe(true);
        expect(wrapper.findAll("button").some((button) => button.attributes("title") === "Approve")).toBe(false);
        expect(wrapper.findAll("button").some((button) => button.text() === "Mark Paid")).toBe(false);
    });

    it("shows finance approval and payment processing actions without generate action", async () => {
        authState.permissions = ["thr-list", "thr-approve", "thr-process"];

        const wrapper = factory();
        await flushAsync();

        expect(wrapper.findAll("button").some((button) => button.text() === "Generate THR")).toBe(false);
        expect(wrapper.findAll("button").some((button) => button.attributes("title") === "Approve")).toBe(true);
        expect(wrapper.findAll("button").some((button) => button.text() === "Mark Paid")).toBe(true);
    });
});
