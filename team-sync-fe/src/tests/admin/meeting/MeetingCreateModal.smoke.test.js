import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const createMeeting = vi.fn().mockResolvedValue(undefined);
const fetchTeams = vi.fn().mockResolvedValue(undefined);
const toastSuccess = vi.fn();
const toastError = vi.fn();

const teamStoreMock = {
    fetchTeams,
    teams: [
        { id: 1, name: "Engineering" },
        { id: 2, name: "Product" },
    ],
    loading: false,
};

vi.mock("@/stores/meeting", () => ({
    useMeetingStore: () => ({
        createMeeting,
        error: null,
    }),
}));

vi.mock("@/stores/team", () => ({
    useTeamStore: () => teamStoreMock,
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();

    return {
        ...actual,
        storeToRefs: vi.fn(),
    };
});

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: toastSuccess,
        error: toastError,
    }),
}));

import MeetingCreateModal from "@/components/admin/meeting/MeetingCreateModal.vue";

const ModalWrapperStub = {
    props: ["show", "title"],
    emits: ["close"],
    template: `
        <div v-if="show" data-testid="modal-wrapper">
            <h3>{{ title }}</h3>
            <slot />
            <slot name="footer" />
        </div>
    `,
};

const factory = (props = {}) => mount(MeetingCreateModal, {
    props: {
        show: true,
        ...props,
    },
    global: {
        stubs: {
            ModalWrapper: ModalWrapperStub,
        },
    },
});

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

describe("MeetingCreateModal smoke", () => {
    beforeEach(() => {
        createMeeting.mockClear();
        fetchTeams.mockClear();
        toastSuccess.mockClear();
        toastError.mockClear();
    });

    it("renders modal when show=true", async () => {
        const wrapper = factory({ show: true });
        await flushAsync();

        expect(wrapper.find('[data-testid="modal-wrapper"]').exists()).toBe(true);
        expect(wrapper.text()).toContain("Schedule Meeting");
        expect(fetchTeams).toHaveBeenCalledTimes(1);
    });

    it("does not render modal when show=false", async () => {
        const wrapper = factory({ show: false });
        await flushAsync();

        expect(wrapper.find('[data-testid="modal-wrapper"]').exists()).toBe(false);
    });

    it("shows required form fields", async () => {
        const wrapper = factory({ show: true });
        await flushAsync();

        expect(wrapper.find('input[placeholder="Meeting title"]').exists()).toBe(true);
        expect(wrapper.find('input[type="datetime-local"]').exists()).toBe(true);
        expect(wrapper.find("select").exists()).toBe(true);
        expect(wrapper.find('input[placeholder="Paste GMeet/Zoom link or enter location"]').exists()).toBe(true);
    });

    it("emits close when cancel clicked", async () => {
        const wrapper = factory({ show: true });
        await flushAsync();

        const buttons = wrapper.findAll("button");
        const cancelButton = buttons.find((button) => button.text() === "Cancel");

        expect(cancelButton).toBeTruthy();
        await cancelButton.trigger("click");

        expect(wrapper.emitted("close")).toBeTruthy();
        expect(wrapper.emitted("close").length).toBe(1);
    });
});
