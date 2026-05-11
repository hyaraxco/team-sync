import { describe, expect, it } from "vitest";
import { mount } from "@vue/test-utils";

import Pagination from "@/components/admin/team/Pagination.vue";

const factory = (meta = {}, loading = false) =>
    mount(Pagination, {
        props: {
            meta: {
                current_page: 1,
                last_page: 1,
                per_page: 10,
                total: 0,
                ...meta,
            },
            loading,
        },
    });

describe("Pagination - visiblePages", () => {
    it("shows all pages when last page <= 7", () => {
        const wrapper = factory({ current_page: 1, last_page: 5 });
        const pages = wrapper.vm.visiblePages;
        expect(pages).toEqual([1, 2, 3, 4, 5]);
    });

    it("shows all 7 pages when last page is exactly 7", () => {
        const wrapper = factory({ current_page: 1, last_page: 7 });
        const pages = wrapper.vm.visiblePages;
        expect(pages).toEqual([1, 2, 3, 4, 5, 6, 7]);
    });

    it("shows ellipsis at end when current is near start (page 1 of 10)", () => {
        const wrapper = factory({ current_page: 1, last_page: 10 });
        const pages = wrapper.vm.visiblePages;
        expect(pages).toEqual([1, 2, 3, 4, 5, "...", 10]);
    });

    it("shows ellipsis at end when current is page 4 of 10", () => {
        const wrapper = factory({ current_page: 4, last_page: 10 });
        const pages = wrapper.vm.visiblePages;
        expect(pages).toEqual([1, 2, 3, 4, 5, "...", 10]);
    });

    it("shows ellipsis at start when current is near end (page 10 of 10)", () => {
        const wrapper = factory({ current_page: 10, last_page: 10 });
        const pages = wrapper.vm.visiblePages;
        expect(pages).toEqual([1, "...", 6, 7, 8, 9, 10]);
    });

    it("shows ellipsis at start when current is page 7 of 10", () => {
        const wrapper = factory({ current_page: 7, last_page: 10 });
        const pages = wrapper.vm.visiblePages;
        expect(pages).toEqual([1, "...", 6, 7, 8, 9, 10]);
    });

    it("shows ellipsis on both sides when current is in middle (page 5 of 10)", () => {
        const wrapper = factory({ current_page: 5, last_page: 10 });
        const pages = wrapper.vm.visiblePages;
        expect(pages).toEqual([1, "...", 4, 5, 6, "...", 10]);
    });

    it("shows ellipsis on both sides when current is page 6 of 10", () => {
        const wrapper = factory({ current_page: 6, last_page: 10 });
        const pages = wrapper.vm.visiblePages;
        expect(pages).toEqual([1, "...", 5, 6, 7, "...", 10]);
    });

    it("handles large page count (100 pages, current at 50)", () => {
        const wrapper = factory({ current_page: 50, last_page: 100 });
        const pages = wrapper.vm.visiblePages;
        expect(pages).toEqual([1, "...", 49, 50, 51, "...", 100]);
    });

    it("handles last page boundary (page 97 of 100)", () => {
        const wrapper = factory({ current_page: 97, last_page: 100 });
        const pages = wrapper.vm.visiblePages;
        expect(pages).toEqual([1, "...", 96, 97, 98, 99, 100]);
    });
});

describe("Pagination - Event emission", () => {
    it("emits page-change when a page button is clicked", async () => {
        const wrapper = factory({ current_page: 1, last_page: 5 });
        await wrapper.vm.handlePageChange(3);
        expect(wrapper.emitted("page-change")).toBeTruthy();
        expect(wrapper.emitted("page-change")[0]).toEqual([3]);
    });

    it("does not emit page-change when clicking current page", async () => {
        const wrapper = factory({ current_page: 3, last_page: 5 });
        await wrapper.vm.handlePageChange(3);
        expect(wrapper.emitted("page-change")).toBeFalsy();
    });

    it("does not emit page-change when loading", async () => {
        const wrapper = factory({ current_page: 1, last_page: 5 }, true);
        await wrapper.vm.handlePageChange(3);
        expect(wrapper.emitted("page-change")).toBeFalsy();
    });

    it("emits per-page-change when per-page value changes", async () => {
        const wrapper = factory({ current_page: 1, last_page: 5, per_page: 10 });
        await wrapper.vm.handlePerPageChange(24);
        expect(wrapper.emitted("per-page-change")).toBeTruthy();
        expect(wrapper.emitted("per-page-change")[0]).toEqual([24]);
    });

    it("does not emit per-page-change when same value selected", async () => {
        const wrapper = factory({ current_page: 1, last_page: 5, per_page: 10 });
        await wrapper.vm.handlePerPageChange(10);
        expect(wrapper.emitted("per-page-change")).toBeFalsy();
    });
});

describe("Pagination - Navigation buttons", () => {
    it("goToPrevious emits previous page", async () => {
        const wrapper = factory({ current_page: 3, last_page: 5 });
        await wrapper.vm.goToPrevious();
        expect(wrapper.emitted("page-change")).toBeTruthy();
        expect(wrapper.emitted("page-change")[0]).toEqual([2]);
    });

    it("goToPrevious does nothing on first page", async () => {
        const wrapper = factory({ current_page: 1, last_page: 5 });
        await wrapper.vm.goToPrevious();
        expect(wrapper.emitted("page-change")).toBeFalsy();
    });

    it("goToNext emits next page", async () => {
        const wrapper = factory({ current_page: 3, last_page: 5 });
        await wrapper.vm.goToNext();
        expect(wrapper.emitted("page-change")).toBeTruthy();
        expect(wrapper.emitted("page-change")[0]).toEqual([4]);
    });

    it("goToNext does nothing on last page", async () => {
        const wrapper = factory({ current_page: 5, last_page: 5 });
        await wrapper.vm.goToNext();
        expect(wrapper.emitted("page-change")).toBeFalsy();
    });
});
