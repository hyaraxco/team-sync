import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const { reviewStoreMock, reviewStoreRefs, toastSuccessMock, toastErrorMock, routerPushMock } = vi.hoisted(() => ({
    reviewStoreMock: {
        fetchTemplates: vi.fn(),
        createCycle: vi.fn(),
        error: null,
    },
    reviewStoreRefs: {
        templates: {
            __v_isRef: true,
            value: [],
        },
        templatesLoading: {
            __v_isRef: true,
            value: false,
        },
        cyclesLoading: {
            __v_isRef: true,
            value: false,
        },
    },
    toastSuccessMock: vi.fn(),
    toastErrorMock: vi.fn(),
    routerPushMock: vi.fn(),
}));

vi.mock("@/stores/performanceReview", () => ({
    usePerformanceReviewStore: () => reviewStoreMock,
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: toastSuccessMock,
        error: toastErrorMock,
    }),
}));

vi.mock("vue-router", () => ({
    useRouter: () => ({
        push: routerPushMock,
    }),
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        storeToRefs: (store) => {
            if (store === reviewStoreMock) {
                return reviewStoreRefs;
            }
            return {};
        },
    };
});

import ReviewCycleCreate from "@/views/admin/performance/ReviewCycleCreate.vue";

const factory = () => mount(ReviewCycleCreate);

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

describe("ReviewCycleCreate smoke", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        reviewStoreMock.error = null;
        reviewStoreRefs.templates.value = [
            { id: 1, name: "Default Template", is_default: true, sections_count: 3 },
            { id: 2, name: "Engineering Template", is_default: false, sections_count: 5 },
        ];
        reviewStoreRefs.templatesLoading.value = false;
        reviewStoreRefs.cyclesLoading.value = false;

        reviewStoreMock.fetchTemplates.mockResolvedValue(undefined);
        reviewStoreMock.createCycle.mockResolvedValue({ id: 42 });
    });

    it("renders without crashing", () => {
        const wrapper = factory();
        expect(wrapper.exists()).toBe(true);
    });

    it("calls fetchTemplates on mount", async () => {
        factory();
        await flushAsync();
        expect(reviewStoreMock.fetchTemplates).toHaveBeenCalled();
    });

    it("displays form heading", () => {
        const wrapper = factory();
        expect(wrapper.text()).toContain("Buat Siklus Review");
    });

    it("renders template select with options", () => {
        const wrapper = factory();
        const templateSelect = wrapper.findAll("select").find((s) => s.text().includes("Default Template"));
        expect(templateSelect).toBeTruthy();
        expect(templateSelect.text()).toContain("Engineering Template");
    });

    it("submit button disabled when required fields empty", () => {
        const wrapper = factory();
        const submitButton = wrapper.find('button[type="submit"]');
        expect(submitButton.attributes("disabled")).toBeDefined();
    });

    it("submits cycle via store action and navigates", async () => {
        const wrapper = factory();
        await flushAsync();

        await wrapper.find('input[type="text"]').setValue("Q1 2026 Review");
        const dateInputs = wrapper.findAll('input[type="date"]');
        await dateInputs[0].setValue("2026-01-01");
        await dateInputs[1].setValue("2026-03-31");
        await dateInputs[2].setValue("2026-04-01");
        await dateInputs[3].setValue("2026-04-30");

        await wrapper.find("form").trigger("submit");
        await flushAsync();

        expect(reviewStoreMock.createCycle).toHaveBeenCalledWith(
            expect.objectContaining({
                name: "Q1 2026 Review",
                cycle_type: "quarterly",
                review_period_start: "2026-01-01",
                review_period_end: "2026-03-31",
                start_date: "2026-04-01",
                end_date: "2026-04-30",
            }),
        );
        expect(toastSuccessMock).toHaveBeenCalled();
        expect(routerPushMock).toHaveBeenCalledWith({
            name: "admin.performance.cycles",
        });
    });

    it("shows error toast on submission failure", async () => {
        reviewStoreMock.createCycle.mockRejectedValue(new Error("Server error"));
        reviewStoreMock.error = "Failed to create cycle";

        const wrapper = factory();
        await flushAsync();

        await wrapper.find('input[type="text"]').setValue("Q1 2026 Review");
        const dateInputs = wrapper.findAll('input[type="date"]');
        await dateInputs[0].setValue("2026-01-01");
        await dateInputs[1].setValue("2026-03-31");
        await dateInputs[2].setValue("2026-04-01");
        await dateInputs[3].setValue("2026-04-30");

        await wrapper.find("form").trigger("submit");
        await flushAsync();

        expect(toastErrorMock).toHaveBeenCalled();
        expect(routerPushMock).not.toHaveBeenCalled();
    });

    it("handles 422 validation errors (object format)", async () => {
        reviewStoreMock.createCycle.mockRejectedValue(new Error("Validation"));
        reviewStoreMock.error = {
            end_date: ["The end date must be after start date."],
            review_period_end: ["The review period end must be before start date."],
        };

        const wrapper = factory();
        await flushAsync();

        await wrapper.find('input[type="text"]').setValue("Q1 2026 Review");
        const dateInputs = wrapper.findAll('input[type="date"]');
        await dateInputs[0].setValue("2026-01-01");
        await dateInputs[1].setValue("2026-03-31");
        await dateInputs[2].setValue("2026-04-01");
        await dateInputs[3].setValue("2026-04-30");

        await wrapper.find("form").trigger("submit");
        await flushAsync();

        expect(toastErrorMock).toHaveBeenCalledWith(
            "Failed to create cycle",
            expect.stringContaining("The end date must be after start date"),
        );
    });

    it("navigates back when cancel clicked", async () => {
        const wrapper = factory();
        const cancelButton = wrapper.findAll("button").find((b) => b.text() === "Cancel");
        await cancelButton.trigger("click");
        expect(routerPushMock).toHaveBeenCalledWith({
            name: "admin.performance.cycles",
        });
    });
});
