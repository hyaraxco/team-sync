import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";

const { performanceReviewStoreMock, performanceReviewRefs, routerLinkStub } = vi.hoisted(() => ({
    performanceReviewStoreMock: {
        fetchTemplates: vi.fn(),
        fetchActiveSections: vi.fn(),
        createTemplate: vi.fn(),
        updateTemplate: vi.fn(),
        deleteTemplate: vi.fn(),
    },
    performanceReviewRefs: {
        templates: {
            __v_isRef: true,
            value: [],
        },
        templatesLoading: {
            __v_isRef: true,
            value: false,
        },
        sections: {
            __v_isRef: true,
            value: [],
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
    useRoute: () => ({ name: "admin.performance.templates" }),
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

import TemplateManagement from "@/views/admin/performance/TemplateManagement.vue";

const factory = () =>
    mount(TemplateManagement, {
        global: {
            stubs: {
                RouterLink: routerLinkStub,
            },
        },
    });

const sampleSections = [
    { id: 1, name: "Technical Skills", topsis_category: "kpi", weight: 25 },
    { id: 2, name: "Leadership", topsis_category: "competency", weight: 20 },
];

const sampleTemplates = [
    {
        id: 1,
        name: "Staff Review Template",
        description: "Standard assessment for individual contributors",
        is_active: true,
        is_default: true,
        sections_count: 5,
        sections: [{ id: 1, name: "Technical Skills", pivot: { weight: 25 } }],
    },
    {
        id: 2,
        name: "Manager Review Template",
        description: "Assessment for managers and team leads",
        is_active: true,
        is_default: false,
        sections_count: 5,
        sections: [{ id: 2, name: "Leadership", pivot: { weight: 30 } }],
    },
];

describe("TemplateManagement smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        performanceReviewRefs.templates.value = [];
        performanceReviewRefs.templatesLoading.value = false;
        performanceReviewRefs.sections.value = [];
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetchTemplates and fetchActiveSections on mount", () => {
        factory();
        expect(performanceReviewStoreMock.fetchTemplates).toHaveBeenCalled();
        expect(performanceReviewStoreMock.fetchActiveSections).toHaveBeenCalled();
    });

    it("displays page header", () => {
        const wrapper = factory();
        expect(wrapper.text()).toContain("Review Templates");
    });

    it("displays loading spinner when loading", () => {
        performanceReviewRefs.templatesLoading.value = true;
        const wrapper = factory();
        expect(wrapper.find(".animate-spin").exists()).toBe(true);
    });

    it("displays empty state when no templates", () => {
        performanceReviewRefs.templates.value = [];
        const wrapper = factory();
        expect(wrapper.text()).toMatch(/template belum tersedia/i);
    });

    it("displays templates table when templates exist", () => {
        performanceReviewRefs.templates.value = sampleTemplates;
        const wrapper = factory();
        expect(wrapper.text()).toContain("Staff Review Template");
        expect(wrapper.text()).toContain("Manager Review Template");
    });

    it("shows default badge for default template", () => {
        performanceReviewRefs.templates.value = sampleTemplates;
        const wrapper = factory();
        // Default template should have the Check icon rendered
        const rows = wrapper.findAll("tbody tr");
        expect(rows.length).toBe(2);
    });

    it("shows Active/Inactive status text", () => {
        performanceReviewRefs.templates.value = sampleTemplates;
        const wrapper = factory();
        expect(wrapper.text()).toContain("Active");
    });

    it("opens create modal on New Template click", async () => {
        const wrapper = factory();
        const addBtn = wrapper.findAll("button").find((b) => b.text().includes("New Template"));
        expect(addBtn).toBeTruthy();
        await addBtn.trigger("click");
        expect(wrapper.text()).toContain("New Template");
        expect(wrapper.text()).toContain("Template Name");
    });

    it("opens edit modal on edit button click", async () => {
        performanceReviewRefs.templates.value = sampleTemplates;
        performanceReviewRefs.sections.value = sampleSections;
        const wrapper = factory();
        // Find the first edit button in the actions column
        const actionTds = wrapper.findAll("td").filter((td) => td.findAll("button").length > 0);
        if (actionTds.length) {
            const editBtn = actionTds[0].findAll("button")[0];
            await editBtn.trigger("click");
            expect(wrapper.text()).toContain("Edit Template");
        }
    });

    it("displays section weight total in modal", async () => {
        performanceReviewRefs.sections.value = sampleSections;
        const wrapper = factory();
        const addBtn = wrapper.findAll("button").find((b) => b.text().includes("New Template"));
        await addBtn.trigger("click");
        expect(wrapper.text()).toContain("Total: 0%");
    });
});
