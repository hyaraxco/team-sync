import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";

const mockFetchProjectMembers = vi.fn();

vi.mock("@/stores/project", () => ({
    useProjectStore: () => ({
        fetchProjectMembers: mockFetchProjectMembers,
    }),
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: vi.fn(),
        warning: vi.fn(),
        error: vi.fn(),
    }),
}));

vi.mock("@/components/common/ModalWrapper.vue", () => ({
    default: {
        name: "ModalWrapperStub",
        props: ["show", "title", "maxWidth"],
        emits: ["close"],
        template: `
            <div v-if="show" class="modal-stub">
                <slot />
                <slot name="footer" />
            </div>
        `,
    },
}));

import TaskCreateModal from "@/components/admin/project/detail/TaskCreateModal.vue";

const factory = (props = {}) =>
    mount(TaskCreateModal, {
        props: { isOpen: true, projectId: 1, ...props },
    });

describe("TaskCreateModal", () => {
    beforeEach(() => {
        mockFetchProjectMembers.mockReset();
        mockFetchProjectMembers.mockResolvedValue([]);
    });

    it("fetches project members when opened", async () => {
        mockFetchProjectMembers.mockResolvedValue([
            { id: 10, user: { name: "Alice" }, code: "EMP-001" },
            { id: 11, user: { name: "Bob" }, code: "EMP-002" },
        ]);
        factory({ isOpen: true });
        await flushPromises();
        expect(mockFetchProjectMembers).toHaveBeenCalledWith(1);
    });

    it("renders status as read-only text (no status dropdown)", async () => {
        const wrapper = factory();
        await flushPromises();
        // No <select> with id "task-status"
        const selects = wrapper.findAll("select");
        const statusSelect = selects.find((s) => s.attributes("id") === "task-status");
        expect(statusSelect).toBeUndefined();
        // "To Do" text visible as read-only badge
        expect(wrapper.text()).toContain("To Do");
        expect(wrapper.text()).toContain("(set automatically)");
    });

    it("populates assignee dropdown with project members", async () => {
        mockFetchProjectMembers.mockResolvedValue([
            { id: 10, user: { name: "Alice" }, code: "EMP-001" },
            { id: 11, user: { name: "Bob" }, code: "EMP-002" },
        ]);
        const wrapper = factory();
        await flushPromises();
        const assigneeSelect = wrapper.find("#task-assignee");
        const options = assigneeSelect.findAll("option");
        const optionTexts = options.map((o) => o.text());
        expect(optionTexts).toContain("Unassigned");
        expect(optionTexts).toContain("Alice");
        expect(optionTexts).toContain("Bob");
    });

    it("treats assignee as optional — submits with null assignee", async () => {
        mockFetchProjectMembers.mockResolvedValue([
            { id: 10, user: { name: "Alice" }, code: "EMP-001" },
        ]);
        const wrapper = factory();
        await flushPromises();
        await wrapper.find("input[type='text']").setValue("New Task");
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();
        const emitted = wrapper.emitted("created");
        expect(emitted).toBeTruthy();
        expect(emitted[0][0].name).toBe("New Task");
        expect(emitted[0][0].assignee_id).toBeNull();
        expect(emitted[0][0].status).toBe("todo");
    });

    it("shows empty state when no members are available", async () => {
        mockFetchProjectMembers.mockResolvedValue([]);
        const wrapper = factory();
        await flushPromises();
        expect(wrapper.text()).toContain("No team members available");
    });

    it("emits task with status 'todo' regardless of any prior state", async () => {
        mockFetchProjectMembers.mockResolvedValue([]);
        const wrapper = factory();
        await flushPromises();
        await wrapper.find("input[type='text']").setValue("Validate Status");
        await wrapper.find("form").trigger("submit.prevent");
        await flushPromises();
        const emitted = wrapper.emitted("created");
        expect(emitted[0][0].status).toBe("todo");
    });

    it("disables assignee select and shows loading text while fetching members", () => {
        mockFetchProjectMembers.mockReturnValue(new Promise(() => {}));
        const wrapper = factory();

        const select = wrapper.find("#task-assignee");
        expect(select.attributes("disabled")).toBeDefined();
        expect(select.text()).toContain("Loading members...");
    });

    it("shows empty state when fetchProjectMembers rejects", async () => {
        mockFetchProjectMembers.mockRejectedValue(new Error("Network error"));
        const wrapper = factory();
        await flushPromises();

        const select = wrapper.find("#task-assignee");
        expect(select.attributes("disabled")).toBeUndefined();
        expect(wrapper.text()).toContain("No team members available");
    });
});
