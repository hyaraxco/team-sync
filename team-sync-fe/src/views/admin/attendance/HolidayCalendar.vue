<script setup>
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { Calendar, Plus, Pencil, Trash2 } from "lucide-vue-next";
import { useHolidayCalendarStore } from "@/stores/holidayCalendar";
import { useToast } from "@/composables/useToast";
import MainCard from "@/components/common/MainCard.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import Pagination from "@/components/admin/team/Pagination.vue";

const holidayCalendarStore = useHolidayCalendarStore();
const { paginatedHolidays, meta, loading, error } = storeToRefs(holidayCalendarStore);
const toast = useToast();

const serverOptions = ref({
    page: 1,
    row_per_page: 10,
});

const isFormModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const isSubmitting = ref(false);
const selectedHoliday = ref(null);

const form = ref({
    date: "",
    name: "",
    type: "national_holiday",
    applies_to: [],
});

const isEditing = computed(() => Boolean(selectedHoliday.value));

const formTitle = computed(() => (isEditing.value ? "Edit Holiday" : "Add Holiday"));

const fetchData = async () => {
    await holidayCalendarStore.fetchAllPaginated({
        page: serverOptions.value.page,
        row_per_page: serverOptions.value.row_per_page,
    });
};

const resetForm = () => {
    form.value = {
        date: "",
        name: "",
        type: "national_holiday",
        applies_to: [],
    };
    selectedHoliday.value = null;
};

const openCreateModal = () => {
    resetForm();
    isFormModalOpen.value = true;
};

const openEditModal = (holiday) => {
    selectedHoliday.value = holiday;
    form.value = {
        date: holiday.date || "",
        name: holiday.name || holiday.description || "",
        type: holiday.type || "national_holiday",
        applies_to: holiday.applies_to || [],
    };
    isFormModalOpen.value = true;
};

const closeFormModal = () => {
    isFormModalOpen.value = false;
    resetForm();
};

const openDeleteModal = (holiday) => {
    selectedHoliday.value = holiday;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = () => {
    isDeleteModalOpen.value = false;
    selectedHoliday.value = null;
};

const handlePageChange = async (page) => {
    serverOptions.value.page = page;
    await fetchData();
};

const handlePerPageChange = async (perPage) => {
    serverOptions.value.row_per_page = perPage;
    serverOptions.value.page = 1;
    await fetchData();
};

const formatHolidayType = (type) => {
    if (type === "collective_leave") {
        return "Cuti Bersama";
    }
    return "National Holiday";
};

const getHolidayTypeColor = (type) => {
    if (type === "collective_leave") {
        return "bg-blue-100 text-blue-800 border-blue-200";
    }
    return "bg-red-100 text-red-800 border-red-200";
};

const submitForm = async () => {
    isSubmitting.value = true;
    try {
        if (isEditing.value) {
            await holidayCalendarStore.updateHoliday(selectedHoliday.value.id, form.value);
            toast.success("Updated", "Holiday has been updated successfully.");
        } else {
            await holidayCalendarStore.createHoliday(form.value);
            toast.success("Created", "Holiday has been added successfully.");
        }
        closeFormModal();
        await fetchData();
    } catch (submitError) {
        toast.error("Failed", holidayCalendarStore.error || submitError?.message || "Failed to save holiday.");
    } finally {
        isSubmitting.value = false;
    }
};

const confirmDelete = async () => {
    if (!selectedHoliday.value?.id) {
        return;
    }

    isSubmitting.value = true;
    try {
        await holidayCalendarStore.deleteHoliday(selectedHoliday.value.id);
        toast.success("Deleted", "Holiday has been deleted successfully.");
        closeDeleteModal();
        await fetchData();
    } catch (deleteError) {
        toast.error("Failed", holidayCalendarStore.error || deleteError?.message || "Failed to delete holiday.");
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(() => {
    fetchData();
});
</script>

<template>
    <div class="p-3 sm:p-4 md:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <span class="sr-only" role="heading" aria-level="1">Holiday Calendar</span>

    <MainCard>
        <div class="flex items-center justify-between mb-6">
            <div>
                <p class="text-2xl font-bold text-brand-dark flex items-center gap-2">
                    <Calendar class="w-6 h-6" />
                    Holiday Calendar
                </p>
                <p class="text-sm text-brand-light mt-1">Manage national holidays and collective leave dates.</p>
            </div>

            <button
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-brand-dark text-white text-sm font-semibold hover:bg-opacity-90 transition-all duration-300"
                @click="openCreateModal"
            >
                <Plus class="w-4 h-4" />
                Add Holiday
            </button>
        </div>

        <div v-if="loading" class="py-16 flex items-center justify-center">
            <div class="flex items-center gap-3 text-brand-light">
                <div class="w-5 h-5 border-2 border-brand-border border-t-brand-dark rounded-full animate-spin"></div>
                <span class="text-sm">Loading holidays...</span>
            </div>
        </div>

        <div v-else-if="!paginatedHolidays || paginatedHolidays.length === 0" class="py-8">
            <EmptyState
                icon="CalendarClock"
                title="No holidays found"
                subtitle="Add a holiday to start building the company calendar."
            />
        </div>

        <div v-else>
            <div class="overflow-x-auto w-full mb-6">
                <table class="w-full min-w-[700px]">
                    <thead>
                        <tr class="border-y border-brand-border">
                            <th class="py-4 px-4 text-left text-brand-light font-semibold text-sm">Date</th>
                            <th class="py-4 px-4 text-left text-brand-light font-semibold text-sm">Name</th>
                            <th class="py-4 px-4 text-left text-brand-light font-semibold text-sm">Type</th>
                            <th class="py-4 px-4 text-left text-brand-light font-semibold text-sm">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="holiday in paginatedHolidays"
                            :key="holiday.id"
                            class="border-b border-brand-border hover:bg-brand-border/20 transition-colors"
                        >
                            <td class="py-4 px-4 text-sm text-brand-dark font-medium">
                                {{ holiday.date }}
                            </td>
                            <td class="py-4 px-4 text-sm text-brand-dark">
                                {{ holiday.name || holiday.description }}
                            </td>
                            <td class="py-4 px-4">
                                <span
                                    :class="[
                                        'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border',
                                        getHolidayTypeColor(holiday.type),
                                    ]"
                                >
                                    {{ formatHolidayType(holiday.type) }}
                                </span>
                            </td>
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-2">
                                    <button
                                        class="inline-flex items-center gap-2 border border-brand-border rounded-lg hover:border-brand-primary hover:bg-blue-50 transition-all duration-300 px-3 py-2"
                                        @click="openEditModal(holiday)"
                                    >
                                        <Pencil class="w-4 h-4 text-blue-600" />
                                        <span class="text-xs font-semibold">Edit</span>
                                    </button>
                                    <button
                                        class="inline-flex items-center gap-2 border border-brand-border rounded-lg hover:border-red-500 hover:bg-red-50 transition-all duration-300 px-3 py-2"
                                        @click="openDeleteModal(holiday)"
                                    >
                                        <Trash2 class="w-4 h-4 text-red-600" />
                                        <span class="text-xs font-semibold">Delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination
                :meta="meta"
                :loading="loading"
                @page-change="handlePageChange"
                @per-page-change="handlePerPageChange"
            />
        </div>

        <p v-if="error" class="text-sm text-red-600 mt-4">
            {{ error }}
        </p>
    </MainCard>

    <ModalWrapper :show="isFormModalOpen" :title="formTitle" maxWidth="md" @close="closeFormModal">
        <form class="space-y-4" @submit.prevent="submitForm">
            <div>
                <label class="block text-sm font-semibold text-brand-dark mb-2">Date</label>
                <input
                    v-model="form.date"
                    type="date"
                    required
                    class="w-full px-4 py-2 border border-brand-border rounded-lg hover:border-brand-primary focus:border-brand-primary"
                />
            </div>

            <div>
                <label class="block text-sm font-semibold text-brand-dark mb-2">Name</label>
                <input
                    v-model="form.name"
                    type="text"
                    required
                    placeholder="e.g., Independence Day"
                    class="w-full px-4 py-2 border border-brand-border rounded-lg hover:border-brand-primary focus:border-brand-primary"
                />
            </div>

            <div>
                <label class="block text-sm font-semibold text-brand-dark mb-2">Type</label>
                <select
                    v-model="form.type"
                    required
                    class="w-full px-4 py-2 border border-brand-border rounded-lg hover:border-brand-primary focus:border-brand-primary"
                >
                    <option value="national_holiday">National Holiday</option>
                    <option value="collective_leave">Collective Leave (Cuti Bersama)</option>
                </select>
                <p class="mt-1 text-xs text-brand-light">
                    Cuti bersama does not require leave requests and won't deduct from employee leave balance.
                </p>
            </div>

            <div class="flex gap-3 pt-2">
                <button
                    type="button"
                    :disabled="isSubmitting"
                    class="flex-1 px-4 py-3 border border-brand-border rounded-xl text-brand-dark text-sm font-semibold hover:border-brand-primary transition-all duration-300"
                    @click="closeFormModal"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="isSubmitting"
                    class="flex-1 px-4 py-3 bg-brand-dark text-white rounded-xl text-sm font-semibold hover:bg-opacity-90 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ isSubmitting ? "Saving..." : isEditing ? "Update Holiday" : "Create Holiday" }}
                </button>
            </div>
        </form>
    </ModalWrapper>

    <ModalWrapper :show="isDeleteModalOpen" title="Delete Holiday" maxWidth="md" @close="closeDeleteModal">
        <p class="text-sm text-brand-light mb-6">
            Are you sure you want to delete
            <span class="font-semibold text-brand-dark">
                {{ selectedHoliday?.name || selectedHoliday?.description }}
            </span>
            ? This action cannot be undone.
        </p>

        <template #footer>
            <div class="flex gap-3">
                <button
                    type="button"
                    :disabled="isSubmitting"
                    class="flex-1 px-4 py-3 border border-brand-border rounded-xl text-brand-dark text-sm font-semibold hover:border-brand-primary transition-all duration-300"
                    @click="closeDeleteModal"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    :disabled="isSubmitting"
                    class="flex-1 px-4 py-3 bg-red-600 text-white rounded-xl text-sm font-semibold hover:bg-red-700 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
                    @click="confirmDelete"
                >
                    {{ isSubmitting ? "Deleting..." : "Delete" }}
                </button>
            </div>
        </template>
    </ModalWrapper>
        </div>
    </div>
</template>
