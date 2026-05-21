import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const { performanceReviewStoreMock, performanceReviewRefs, routerLinkStub } = vi.hoisted(() => ({
    performanceReviewStoreMock: {
        fetchOutcomeRules: vi.fn(),
        createOutcomeRule: vi.fn(),
        updateOutcomeRule: vi.fn(),
        deleteOutcomeRule: vi.fn(),
    },
    performanceReviewRefs: {
        outcomeRules: {
            __v_isRef: true,
            value: [],
        },
        outcomeRulesLoading: {
            __v_isRef: true,
            value: false,
        },
    },
    routerLinkStub: {
        name: "RouterLink",
        props: ["to"],
        template: '<a class="router-link-stub"><slot /></a>',
    },
}));

vi.mock("@/stores/performanceReview", () => ({
    usePerformanceReviewStore: () => performanceReviewStoreMock,
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        storeToRefs: () => performanceReviewRefs,
    };
});

vi.mock("vue-router", () => ({
    useRoute: () => ({ name: "admin.performance.outcome-rules" }),
    useRouter: () => ({ push: vi.fn() }),
    RouterLink: routerLinkStub,
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: vi.fn(),
        error: vi.fn(),
        warning: vi.fn(),
    }),
}));

import OutcomeRulesSettings from "@/views/admin/performance/OutcomeRulesSettings.vue";

const factory = () =>
    mount(OutcomeRulesSettings, {
        global: {
            stubs: {
                RouterLink: routerLinkStub,
            },
        },
    });

const sampleRules = [
    {
        id: 1,
        label: "Outstanding",
        min_rating: 4.5,
        max_rating: 5.0,
        bonus_months: 3.0,
        salary_increase_pct: 10.0,
        promotion_eligible: true,
        pip_required: false,
        is_active: true,
        description: "Top performer",
    },
    {
        id: 2,
        label: "Needs Improvement",
        min_rating: 1.5,
        max_rating: 2.49,
        bonus_months: 0,
        salary_increase_pct: 0,
        promotion_eligible: false,
        pip_required: true,
        is_active: true,
        description: "PIP required",
    },
];

describe("OutcomeRulesSettings smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        performanceReviewRefs.outcomeRules.value = [];
        performanceReviewRefs.outcomeRulesLoading.value = false;
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetchOutcomeRules on mount", () => {
        factory();
        expect(performanceReviewStoreMock.fetchOutcomeRules).toHaveBeenCalled();
    });

    it("displays page header", () => {
        const wrapper = factory();
        expect(wrapper.text()).toContain("Performance Outcome Rules");
    });

    it("displays loading state", () => {
        performanceReviewRefs.outcomeRulesLoading.value = true;
        const wrapper = factory();
        expect(wrapper.find(".animate-spin").exists()).toBe(true);
    });

    it("displays empty state when no rules", () => {
        performanceReviewRefs.outcomeRules.value = [];
        const wrapper = factory();
        expect(wrapper.text()).toMatch(/aturan outcome belum dikonfigurasi/i);
    });

    it("displays rules table when rules exist", () => {
        performanceReviewRefs.outcomeRules.value = sampleRules;
        const wrapper = factory();
        expect(wrapper.text()).toContain("Outstanding");
        expect(wrapper.text()).toContain("Needs Improvement");
    });

    it("shows promotion badge for eligible rules", () => {
        performanceReviewRefs.outcomeRules.value = sampleRules;
        const wrapper = factory();
        const promotionCells = wrapper.findAll("td").filter((td) => td.text().includes("Yes"));
        expect(promotionCells.length).toBeGreaterThan(0);
    });

    it("shows PIP badge for required rules", () => {
        performanceReviewRefs.outcomeRules.value = sampleRules;
        const wrapper = factory();
        expect(wrapper.text()).toContain("Required");
    });

    it("opens create modal on Add Rule click", async () => {
        const wrapper = factory();
        const addBtn = wrapper.findAll("button").find((b) => b.text().includes("Add Rule"));
        await addBtn.trigger("click");
        expect(wrapper.text()).toContain("Add Outcome Rule");
    });

    it("opens edit modal on edit button click", async () => {
        performanceReviewRefs.outcomeRules.value = sampleRules;
        const wrapper = factory();
        const editBtn = wrapper.findAll("td").at(-1)?.findAll("button")?.at(0);
        if (editBtn) {
            await editBtn.trigger("click");
            expect(wrapper.text()).toContain("Edit Rule");
        }
    });

    it("sorts rules by min_rating ascending", () => {
        performanceReviewRefs.outcomeRules.value = [
            { ...sampleRules[0], min_rating: 4.5 },
            { ...sampleRules[1], min_rating: 1.5 },
        ];
        const wrapper = factory();
        const rows = wrapper.findAll("tbody tr");
        expect(rows[0].text()).toContain("Needs Improvement");
        expect(rows[1].text()).toContain("Outstanding");
    });
});
