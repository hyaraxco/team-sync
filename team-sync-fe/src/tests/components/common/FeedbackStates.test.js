import { beforeEach, describe, expect, it, vi } from "vitest";
import { flushPromises, mount } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import Alert from "@/components/common/Alert.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import TableStateRows from "@/components/common/TableStateRows.vue";
import UpcomingMeetings from "@/components/common/UpcomingMeetings.vue";
import { useMeetingStore } from "@/stores/meeting";
import { axiosInstance } from "@/plugins/axios";

vi.mock("@/plugins/axios", () => ({ axiosInstance: { get: vi.fn() } }));
vi.mock("@/helpers/errorHelper", () => ({ handleError: () => "Request failed" }));

describe("Feedback states", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it.each(["error", "danger"])("announces %s as danger and preserves dismissal", async (type) => {
        const wrapper = mount(Alert, { props: { type, title: "Save failed", message: "Retry saving" } });
        expect(wrapper.get('[role="alert"]').text()).toContain("Save failed");
        expect(wrapper.get('[role="alert"]').classes()).toContain("text-[var(--color-feedback-danger)]");
        await wrapper.get('button[aria-label="Close"]').trigger("click");
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        expect(wrapper.emitted("close")).toEqual([[]]);
        await wrapper.setProps({ show: false });
        await wrapper.setProps({ show: true });
        expect(wrapper.get('[role="alert"]').text()).toContain("Save failed");
    });

    it("keeps empty actions outside the status announcement", () => {
        const wrapper = mount(EmptyState, { props: { title: "No meetings" }, slots: { default: '<button>Create meeting</button>' } });
        expect(wrapper.get('[role="status"]').text()).toContain("No meetings");
        expect(wrapper.get('[role="status"]').find("button").exists()).toBe(false);
    });

    it("changes table loading to an announced empty state", async () => {
        const wrapper = mount(TableStateRows, { props: { loading: true, empty: true, colspan: 3, emptyTitle: "No results" } });
        expect(wrapper.get('[role="status"]').text()).toContain("Loading data");
        await wrapper.setProps({ loading: false });
        expect(wrapper.get('[role="status"]').text()).toContain("No results");
        expect(wrapper.text()).not.toContain("Loading data");
    });

    it("isolates upcoming errors and retries through loading to empty", async () => {
        const store = useMeetingStore();
        store.error = "Unrelated create error";
        let rejectRequest;
        axiosInstance.get.mockImplementationOnce(() => new Promise((_resolve, reject) => { rejectRequest = reject; }));
        const wrapper = mount(UpcomingMeetings, { global: { stubs: { RouterLink: true } } });
        await wrapper.vm.$nextTick();
        expect(wrapper.get('[role="status"]').text()).toContain("Loading upcoming meetings");
        rejectRequest(new Error("Offline"));
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain("Unable to load upcoming meetings");
        expect(wrapper.text()).not.toContain("Belum ada meeting");
        expect(store.error).toBe("Unrelated create error");
        let resolveRequest;
        axiosInstance.get.mockImplementationOnce(() => new Promise((resolve) => { resolveRequest = resolve; }));
        await wrapper.get("button").trigger("click");
        expect(store.upcomingError).toBe(null);
        expect(wrapper.get('[role="status"]').text()).toContain("Loading upcoming meetings");
        resolveRequest({ data: { data: [] } });
        await flushPromises();
        expect(wrapper.get('[role="status"]').text()).toContain("Belum ada meeting mendatang");
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        expect(axiosInstance.get).toHaveBeenLastCalledWith("/meetings/upcoming", { params: { limit: 5 } });
        expect(store.error).toBe("Unrelated create error");
    });
});
