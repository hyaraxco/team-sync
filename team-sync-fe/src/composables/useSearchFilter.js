import { ref } from "vue";

/**
 * Composable for search, filter, and pagination logic.
 *
 * @param {Object} options
 * @param {Object} options.defaultFilters - Default filter values, e.g. { search: null, status: '', department: '' }
 * @param {Function} options.fetchFn - Function to call when fetching data, receives merged params
 * @param {number} [options.debounceMs=300] - Debounce delay in ms
 * @param {number} [options.defaultPerPage=10] - Default rows per page
 */
export function useSearchFilter({ defaultFilters = {}, fetchFn, debounceMs: _debounceMs = 300, defaultPerPage = 10 }) {
    const filters = ref({ ...defaultFilters });

    const serverOptions = ref({
        page: 1,
        row_per_page: defaultPerPage,
    });

    const fetchData = async () => {
        await fetchFn({
            ...serverOptions.value,
            ...filters.value,
        });
    };

    const handleSearch = (newFilters) => {
        // Reset all filter keys to defaults first, then apply new values.
        // This ensures cleared fields (e.g. empty search) don't retain stale values.
        const reset = {};
        for (const key of Object.keys(defaultFilters)) {
            reset[key] = defaultFilters[key];
        }
        Object.assign(filters.value, reset, newFilters);
        serverOptions.value.page = 1;
        fetchData();
    };

    const handleReset = () => {
        Object.assign(filters.value, { ...defaultFilters });
        serverOptions.value.page = 1;
        fetchData();
    };

    const handlePageChange = (page) => {
        serverOptions.value.page = page;
        fetchData();
    };

    const handlePerPageChange = (perPage) => {
        serverOptions.value.row_per_page = perPage;
        serverOptions.value.page = 1;
        fetchData();
    };

    return {
        filters,
        serverOptions,
        fetchData,
        handleSearch,
        handleReset,
        handlePageChange,
        handlePerPageChange,
    };
}
