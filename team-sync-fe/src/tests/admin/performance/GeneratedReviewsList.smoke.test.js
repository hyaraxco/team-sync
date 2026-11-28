import { mount } from "@vue/test-utils";
import { describe, it, expect, vi, beforeEach } from "vitest";
import { createPinia, setActivePinia } from "pinia";
import GeneratedReviewsList from "@/components/admin/performance/GeneratedReviewsList.vue";
import ConfirmationModal from '@/components/common/ConfirmationModal.vue';

const { generateReviews } = vi.hoisted(() => ({ generateReviews: vi.fn() }));

vi.mock("vue-router", () => ({
    useRouter: () => ({ push: vi.fn() }),
    useRoute: () => ({ params: { id: 1 } }),
    createRouter: vi.fn(() => ({ push: vi.fn(), beforeEach: vi.fn() })),
    createWebHistory: vi.fn(),
}));

import { ref } from "vue";

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: vi.fn(),
        error: vi.fn(),
        warning: vi.fn(),
    }),
}));

vi.mock("@/stores/performanceReview", () => ({
    usePerformanceReviewStore: () => ({
        loading: ref(false),
        generateReviews,
        assignReviewer: vi.fn(),
    }),
}));

vi.mock("@/stores/staffMember", () => ({
    useStaffMemberStore: () => ({
        staffMembers: ref([]),
        fetchStaffMembers: vi.fn(),
    }),
}));

describe("GeneratedReviewsList.vue Smoke Test", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('keeps confirmation busy until generation completes', async () => {
        let finish;
        generateReviews.mockReturnValueOnce(new Promise(resolve => { finish = resolve; }));
        const wrapper = mount(GeneratedReviewsList, {
            props: { cycle: { id: 1, status: 'draft', reviews: [] } },
            global: { plugins: [createPinia()], stubs: { ConfirmationModal: true } },
        });
        await wrapper.findAll('button').find(button => button.text().includes('Generate Reviews')).trigger('click');
        const confirmation = wrapper.getComponent(ConfirmationModal);
        confirmation.vm.$emit('confirm');
        await wrapper.vm.$nextTick();
        expect(confirmation.props('loading')).toBe(true);
        expect(confirmation.props('show')).toBe(true);
        finish({ generated_count: 1 });
        await new Promise(resolve => setTimeout(resolve, 0));
        expect(confirmation.props('loading')).toBe(false);
        expect(confirmation.props('show')).toBe(false);
        wrapper.unmount();
    });

    it("renders empty state when cycle has no reviews", () => {
        const wrapper = mount(GeneratedReviewsList, {
            global: {
                plugins: [createPinia()],
            },
            props: {
                cycle: { id: 1, status: "draft", reviews: [] },
            },
        });

        expect(wrapper.text()).toContain("Generated Reviews");
        expect(wrapper.text()).toContain("Belum ada ulasan yang digenerate");
        // Generate reviews button is visible because status is draft
        expect(wrapper.text()).toContain("Generate Reviews");
    });

    it("renders reviews table when reviews exist", () => {
        const cycleWithReviews = {
            id: 1,
            status: "active",
            reviews: [
                {
                    id: 10,
                    status: "pending_self",
                    staff_member: {
                        id: 1,
                        user: { name: "Ahmad Fauzi" },
                        job_information: { job_title: "Developer" },
                    },
                    reviewer: {
                        id: 2,
                        user: {
                            name: "Jane Smith",
                            roles: [{ name: "manager" }],
                        },
                    },
                },
            ],
        };

        const wrapper = mount(GeneratedReviewsList, {
            global: {
                plugins: [createPinia()],
            },
            props: { cycle: cycleWithReviews },
        });

        expect(wrapper.text()).toContain("Ahmad Fauzi");
        expect(wrapper.text()).toContain("Developer");
        expect(wrapper.text()).toContain("Jane Smith");
        expect(wrapper.text()).toContain("manager");
        expect(wrapper.text()).toContain("pending self");
        expect(wrapper.text()).toContain("Assign");
    });

    it("shows unassigned state when reviewer is null", () => {
        const cycleWithNullReviewer = {
            id: 1,
            status: "active",
            reviews: [
                {
                    id: 10,
                    status: "pending_self",
                    staff_member: {
                        id: 1,
                        user: { name: "Ahmad Fauzi" },
                        job_information: { job_title: "Developer" },
                    },
                    reviewer: null,
                },
            ],
        };

        const wrapper = mount(GeneratedReviewsList, {
            global: {
                plugins: [createPinia()],
            },
            props: { cycle: cycleWithNullReviewer },
        });

        expect(wrapper.text()).toContain("Unassigned");
    });
});
