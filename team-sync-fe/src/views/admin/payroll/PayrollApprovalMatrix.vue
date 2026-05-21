<script setup>
import { computed, onMounted, ref } from "vue";
import { ShieldCheck, Plus, Pencil, Trash2 } from "lucide-vue-next";
import { usePayrollStore } from "@/stores/payroll";
import { useToast } from "@/composables/useToast";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import EmptyState from "@/components/common/EmptyState.vue";
import { formatRupiah } from "@/utils/formatUtils";

const payrollStore = usePayrollStore();
const toast = useToast();

const policies = ref([]);
const loading = ref(false);
const isSubmitting = ref(false);
const isFormOpen = ref(false);
const isDeleteOpen = ref(false);
const selectedPolicy = ref(null);

const form = ref({
    name: "",
    min_amount: 0,
    max_amount: "",
    required_role: "",
    approval_order: 1,
    is_active: true,
});

const isEditing = computed(() => Boolean(selectedPolicy.value));
const sortedPolicies = computed(() =>
    [...policies.value].sort((a, b) => Number(a.approval_order || 0) - Number(b.approval_order || 0)),
);

const resetForm = () => {
    selectedPolicy.value = null;
    form.value = {
        name: "",
        min_amount: 0,
        max_amount: "",
        required_role: "",
        approval_order: Math.max(1, policies.value.length + 1),
        is_active: true,
    };
};

const loadPolicies = async () => {
    loading.value = true;
    try {
        policies.value = await payrollStore.fetchApprovalPolicies();
    } catch (error) {
        toast.error(
            "Failed to load approval policies",
            payrollStore.error || error?.response?.data?.message || "Please try again.",
        );
    } finally {
        loading.value = false;
    }
};

const openCreate = () => {
    resetForm();
    isFormOpen.value = true;
};

const openEdit = (policy) => {
    selectedPolicy.value = policy;
    form.value = {
        name: policy.name || "",
        min_amount: Number(policy.min_amount || 0),
        max_amount: policy.max_amount ?? "",
        required_role: policy.required_role || "",
        approval_order: Number(policy.approval_order || 1),
        is_active: policy.is_active !== false,
    };
    isFormOpen.value = true;
};

const closeForm = () => {
    isFormOpen.value = false;
    resetForm();
};

const buildPayload = () => ({
    name: form.value.name.trim(),
    min_amount: Number(form.value.min_amount || 0),
    max_amount: form.value.max_amount === "" || form.value.max_amount === null ? null : Number(form.value.max_amount),
    required_role: form.value.required_role.trim(),
    approval_order: Number(form.value.approval_order || 1),
    is_active: Boolean(form.value.is_active),
});

const submitForm = async () => {
    isSubmitting.value = true;
    try {
        if (isEditing.value) {
            await payrollStore.updateApprovalPolicy(selectedPolicy.value.id, buildPayload());
            toast.success("Approval policy updated", "The approval matrix has been updated.");
        } else {
            await payrollStore.createApprovalPolicy(buildPayload());
            toast.success("Approval policy created", "The approval step is now active for matching payrolls.");
        }
        closeForm();
        await loadPolicies();
    } catch (error) {
        toast.error(
            "Failed to save approval policy",
            payrollStore.error || error?.response?.data?.message || "Please check the form and try again.",
        );
    } finally {
        isSubmitting.value = false;
    }
};

const openDelete = (policy) => {
    selectedPolicy.value = policy;
    isDeleteOpen.value = true;
};

const closeDelete = () => {
    isDeleteOpen.value = false;
    selectedPolicy.value = null;
};

const confirmDelete = async () => {
    if (!selectedPolicy.value?.id) {
        return;
    }

    isSubmitting.value = true;
    try {
        await payrollStore.deleteApprovalPolicy(selectedPolicy.value.id);
        toast.success("Approval policy deleted", "The approval step has been removed.");
        closeDelete();
        await loadPolicies();
    } catch (error) {
        toast.error(
            "Failed to delete approval policy",
            payrollStore.error || error?.response?.data?.message || "Please try again.",
        );
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(loadPolicies);
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-brand-dark text-[32px] font-bold leading-tight flex items-center gap-3">
                    <ShieldCheck class="w-8 h-8 text-blue-600" />
                    Matriks Persetujuan Payroll
                </h1>
                <p class="text-brand-light text-base font-normal mt-2">
                    Configure threshold-based approval steps for payroll batches before payment.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl bg-brand-dark px-4 py-3 text-sm font-semibold text-white hover:bg-opacity-90"
                @click="openCreate"
            >
                <Plus class="w-4 h-4" />
                Add Policy
            </button>
        </div>

        <div class="bg-white border border-brand-border rounded-2xl p-6">
            <div v-if="loading" class="py-12 text-center text-brand-light">Loading approval policies...</div>
            <EmptyState
                v-else-if="sortedPolicies.length === 0"
                icon="ShieldCheck"
                title="No approval policies configured"
                subtitle="Create a policy to require one or more role-based approvals for matching payroll totals."
            />
            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E5E7EB] text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-brand-dark">Order</th>
                            <th class="px-4 py-3 text-left font-semibold text-brand-dark">Policy</th>
                            <th class="px-4 py-3 text-left font-semibold text-brand-dark">Amount Range</th>
                            <th class="px-4 py-3 text-left font-semibold text-brand-dark">Required Role</th>
                            <th class="px-4 py-3 text-left font-semibold text-brand-dark">Status</th>
                            <th class="px-4 py-3 text-right font-semibold text-brand-dark">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F1F5F9] bg-white">
                        <tr v-for="policy in sortedPolicies" :key="policy.id">
                            <td class="px-4 py-3 font-semibold text-brand-dark">{{ policy.approval_order }}</td>
                            <td class="px-4 py-3 text-brand-dark">{{ policy.name }}</td>
                            <td class="px-4 py-3 text-brand-dark">
                                {{ formatRupiah(policy.min_amount || 0) }} —
                                {{ policy.max_amount ? formatRupiah(policy.max_amount) : "No maximum" }}
                            </td>
                            <td class="px-4 py-3 text-brand-dark">{{ policy.required_role }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="
                                        policy.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'
                                    "
                                >
                                    {{ policy.is_active ? "Active" : "Inactive" }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    class="mr-2 inline-flex items-center gap-1 rounded-lg border border-brand-border px-3 py-2 text-xs font-semibold"
                                    @click="openEdit(policy)"
                                >
                                    <Pencil class="w-3.5 h-3.5" />
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600"
                                    @click="openDelete(policy)"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                    Delete
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <ModalWrapper
            :show="isFormOpen"
            :title="isEditing ? 'Edit Approval Policy' : 'Add Approval Policy'"
            maxWidth="md"
            @close="closeForm"
        >
            <form class="space-y-4" @submit.prevent="submitForm">
                <div>
                    <label class="block text-sm font-semibold text-brand-dark mb-2">Policy Name</label>
                    <input
                        v-model="form.name"
                        required
                        type="text"
                        class="w-full rounded-lg border border-brand-border px-4 py-3"
                    />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-brand-dark mb-2">Minimum Amount</label>
                        <input
                            v-model="form.min_amount"
                            required
                            min="0"
                            type="number"
                            class="w-full rounded-lg border border-brand-border px-4 py-3"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-brand-dark mb-2">Maximum Amount</label>
                        <input
                            v-model="form.max_amount"
                            min="0"
                            type="number"
                            placeholder="No maximum"
                            class="w-full rounded-lg border border-brand-border px-4 py-3"
                        />
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-brand-dark mb-2">Required Role</label>
                        <input
                            v-model="form.required_role"
                            required
                            type="text"
                            placeholder="finance-manager"
                            class="w-full rounded-lg border border-brand-border px-4 py-3"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-brand-dark mb-2">Approval Order</label>
                        <input
                            v-model="form.approval_order"
                            required
                            min="1"
                            type="number"
                            class="w-full rounded-lg border border-brand-border px-4 py-3"
                        />
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-brand-dark">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-brand-border" />
                    Active policy
                </label>
                <div class="flex justify-end gap-3 pt-2">
                    <button
                        type="button"
                        class="rounded-lg border border-brand-border px-4 py-3 text-sm font-semibold"
                        @click="closeForm"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="rounded-lg bg-brand-dark px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {{ isSubmitting ? "Saving..." : "Save Policy" }}
                    </button>
                </div>
            </form>
        </ModalWrapper>

        <ModalWrapper :show="isDeleteOpen" title="Delete Approval Policy" maxWidth="md" @close="closeDelete">
            <p class="text-sm text-brand-light mb-6">
                Delete
                <span class="font-semibold text-brand-dark">{{ selectedPolicy?.name }}</span>
                ? Existing payroll approval records stay unchanged, but future payrolls will not use this policy.
            </p>
            <template #footer>
                <div class="flex gap-3">
                    <button
                        type="button"
                        class="flex-1 rounded-lg border border-brand-border px-4 py-3 text-sm font-semibold"
                        @click="closeDelete"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="isSubmitting"
                        class="flex-1 rounded-lg bg-red-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                        @click="confirmDelete"
                    >
                        {{ isSubmitting ? "Deleting..." : "Delete" }}
                    </button>
                </div>
            </template>
        </ModalWrapper>
    </div>
</template>
