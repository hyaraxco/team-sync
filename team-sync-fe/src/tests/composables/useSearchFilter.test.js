import { describe, it, expect, beforeEach, vi } from "vitest";
import { useSearchFilter } from "@/composables/useSearchFilter";

describe("useSearchFilter", () => {
    let fetchFn;
    let filter;

    beforeEach(() => {
        fetchFn = vi.fn().mockResolvedValue();
        filter = useSearchFilter({
            defaultFilters: { search: null, status: "", department: "" },
            fetchFn,
            defaultPerPage: 15,
        });
    });

    it("initializes with default filters", () => {
        expect(filter.filters.value).toEqual({ search: null, status: "", department: "" });
    });

    it("initializes with default server options", () => {
        expect(filter.serverOptions.value).toEqual({ page: 1, row_per_page: 15 });
    });

    it("fetchData calls fetchFn with merged params", async () => {
        filter.filters.value.search = "test";

        await filter.fetchData();

        expect(fetchFn).toHaveBeenCalledWith({
            page: 1,
            row_per_page: 15,
            search: "test",
            status: "",
            department: "",
        });
    });

    it("handleSearch updates filters and resets page to 1", () => {
        filter.serverOptions.value.page = 5;

        filter.handleSearch({ search: "new search", status: "active" });

        expect(filter.filters.value.search).toBe("new search");
        expect(filter.filters.value.status).toBe("active");
        expect(filter.filters.value.department).toBe("");
        expect(filter.serverOptions.value.page).toBe(1);
    });

    it("handleSearch calls fetchData", () => {
        filter.handleSearch({ search: "test" });

        expect(fetchFn).toHaveBeenCalled();
    });

    it("handleReset restores default filters", () => {
        filter.filters.value.search = "test";
        filter.filters.value.status = "active";

        filter.handleReset();

        expect(filter.filters.value).toEqual({ search: null, status: "", department: "" });
    });

    it("handleReset resets page to 1", () => {
        filter.serverOptions.value.page = 5;

        filter.handleReset();

        expect(filter.serverOptions.value.page).toBe(1);
    });

    it("handlePageChange updates page and fetches", () => {
        filter.handlePageChange(3);

        expect(filter.serverOptions.value.page).toBe(3);
        expect(fetchFn).toHaveBeenCalled();
    });

    it("handlePerPageChange updates perPage and resets page", () => {
        filter.serverOptions.value.page = 5;

        filter.handlePerPageChange(25);

        expect(filter.serverOptions.value.row_per_page).toBe(25);
        expect(filter.serverOptions.value.page).toBe(1);
        expect(fetchFn).toHaveBeenCalled();
    });
});
