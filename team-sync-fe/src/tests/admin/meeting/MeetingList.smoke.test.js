import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick, ref } from "vue";

const mocks = vi.hoisted(() => ({
    fetchMeetingsPaginated: vi.fn(),
    push: vi.fn(),
    toastSuccess: vi.fn(),
    toastError: vi.fn(),
    can: vi.fn(),
}));

const meetings = ref([]);
const meta = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
});
const loading = ref(false);

vi.mock("@/stores/meeting", () => ({
    useMeetingStore: () => ({
        fetchMeetingsPaginated: mocks.fetchMeetingsPaginated,
    }),
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();

    return {
        ...actual,
        storeToRefs: () => ({
            meetings,
            meta,
            loading,
        }),
    };
});

vi.mock("vue-router", () => ({
    useRouter: () => ({
        push: mocks.push,
    }),
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: mocks.toastSuccess,
        error: mocks.toastError,
    }),
}));

vi.mock("@/helpers/permissionHelper", () => ({
    can: mocks.can,
}));

import MeetingList from "@/views/admin/meeting/MeetingList.vue";

const factory = () => mount(MeetingList, {
    global: {
        stubs: {
            MeetingCreateModal: {
                template: "<div data-testid='meeting-create-modal-stub' />",
            },
        },
    },
});

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

describe("MeetingList smoke", () => {
    beforeEach(() => {
        meetings.value = [];
        meta.value = {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
        };
        loading.value = false;

        mocks.fetchMeetingsPaginated.mockReset();
        mocks.fetchMeetingsPaginated.mockResolvedValue(undefined);
        mocks.push.mockClear();
        mocks.toastSuccess.mockClear();
        mocks.toastError.mockClear();
        mocks.can.mockReset();
        mocks.can.mockReturnValue(true);
    });

    it("renders page title Meetings", async () => {
        const wrapper = factory();
        await flushAsync();

        expect(wrapper.text()).toContain("Meetings");
    });

    it("calls fetchMeetingsPaginated on mount", async () => {
        factory();
        await flushAsync();

        expect(mocks.fetchMeetingsPaginated).toHaveBeenCalledTimes(1);
        expect(mocks.fetchMeetingsPaginated).toHaveBeenCalledWith({
            row_per_page: 10,
            page: 1,
            search: undefined,
        });
    });

    it("shows empty state when no meetings", async () => {
        const wrapper = factory();
        await flushAsync();

        expect(wrapper.text()).toContain("No meetings scheduled yet");
        expect(wrapper.text()).toContain("Create your first meeting to get started");
    });

    it("shows Schedule Meeting button when user has meeting-create permission", async () => {
        mocks.can.mockReturnValue(true);
        const wrapper = factory();
        await flushAsync();

        expect(wrapper.find("button").text()).toContain("Schedule Meeting");
    });
});
