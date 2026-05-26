<script setup>
import { ref, computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { usePerformanceReviewStore } from "@/stores/performanceReview";
import { Plus, Edit3, Trash2, Settings, X, Award, AlertTriangle } from "lucide-vue-next";
import MainCard from "@/components/common/MainCard.vue";
import ConfirmationModal from "@/components/common/ConfirmationModal.vue";
import { useToast } from "@/composables/useToast";

const store = usePerformanceReviewStore();
const { outcomeRules, outcomeRulesLoading } = storeToRefs(store);
const toast = useToast();

const showModal = ref(false);
const editingRule = ref(null);
const saving = ref(false);

// Confirmation dialog state
const showConfirmDialog = ref(false);
const confirmTitle = ref("");
const confirmMessage = ref("");
const confirmAction = ref(null);

const defaultForm = () => ({
    label: "",
    min_rating: 1.0,
    max_rating: 5.0,
    bonus_months: 0,
    salary_increase_pct: 0,
    promotion_eligible: false,
    pip_required: false,
    description: "",
    is_active: true,
});

const form = ref(defaultForm());

const sortedRules = computed(() => [...(outcomeRules.value || [])].sort((a, b) => a.min_rating - b.min_rating));

const openCreate = () => {
    editingRule.value = null;
    form.value = defaultForm();
    showModal.value = true;
};

const openEdit = (rule) => {
    editingRule.value = rule;
    form.value = {
        label: rule.label,
        min_rating: rule.min_rating,
        max_rating: rule.max_rating,
        bonus_months: rule.bonus_months,
        salary_increase_pct: rule.salary_increase_pct,
        promotion_eligible: rule.promotion_eligible,
        pip_required: rule.pip_required,
        description: rule.description || "",
        is_active: rule.is_active,
    };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingRule.value = null;
    form.value = defaultForm();
};

const extractErrorMessage = (error) => {
    const data = error.response?.data;
    if (data?.errors) {
        return Object.values(data.errors).flat().join(". ");
    }
    return data?.message || error.message;
};

const saveRule = async () => {
    saving.value = true;
    try {
        if (editingRule.value) {
            await store.updateOutcomeRule(editingRule.value.id, form.value);
            toast.success("Rule updated successfully");
        } else {
            await store.createOutcomeRule(form.value);
            toast.success("Rule created successfully");
        }
        closeModal();
    } catch (error) {
        toast.error(extractErrorMessage(error));
    } finally {
        saving.value = false;
    }
};

const deleteRule = async (rule) => {
    confirmTitle.value = "Delete Rule";
    confirmMessage.value = `Delete rule "${rule.label}"? This cannot be undone.`;
    confirmAction.value = async () => {
        try {
            await store.deleteOutcomeRule(rule.id);
            toast.success("Rule deleted successfully");
        } catch (error) {
            toast.error(extractErrorMessage(error));
        }
    };
    showConfirmDialog.value = true;
};

onMounted(() => {
    store.fetchOutcomeRules();
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-brand-dark">Performance Outcome Rules</h1>
                <p class="text-brand-light mt-1">Configure automatic outcomes based on review ratings</p>
            </div>
            <button
                @click="openCreate"
                class="flex items-center gap-2 px-4 py-2 bg-brand-primary text-white rounded-lg hover:bg-brand-primary-dark transition-colors"
            >
                <Plus class="w-4 h-4" />
                Add Rule
            </button>
        </div>

        <MainCard>
            <div v-if="outcomeRulesLoading" class="flex items-center justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-primary"></div>
            </div>

            <div v-else-if="!sortedRules.length" class="text-center py-12">
                <Settings class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                <h3 class="text-lg font-medium text-brand-dark">No outcome rules configured</h3>
                <p class="text-sm text-brand-light mt-1 max-w-sm mx-auto">
                    Add rules to map review ratings to outcomes (bonus, salary increase, promotion).
                </p>
                <button
                    @click="openCreate"
                    class="mt-4 px-4 py-2 bg-brand-primary text-white rounded-lg hover:bg-brand-primary-dark transition-colors text-sm"
                >
                    Create first rule
                </button>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th
                                class="text-left py-3 px-4 text-xs font-semibold text-brand-light uppercase tracking-wider"
                            >
                                Label
                            </th>
                            <th
                                class="text-left py-3 px-4 text-xs font-semibold text-brand-light uppercase tracking-wider"
                            >
                                Rating Range
                            </th>
                            <th
                                class="text-left py-3 px-4 text-xs font-semibold text-brand-light uppercase tracking-wider"
                            >
                                Bonus
                            </th>
                            <th
                                class="text-left py-3 px-4 text-xs font-semibold text-brand-light uppercase tracking-wider"
                            >
                                Salary Increase
                            </th>
                            <th
                                class="text-center py-3 px-4 text-xs font-semibold text-brand-light uppercase tracking-wider"
                            >
                                Promotion
                            </th>
                            <th
                                class="text-center py-3 px-4 text-xs font-semibold text-brand-light uppercase tracking-wider"
                            >
                                PIP
                            </th>
                            <th
                                class="text-center py-3 px-4 text-xs font-semibold text-brand-light uppercase tracking-wider"
                            >
                                Active
                            </th>
                            <th
                                class="text-right py-3 px-4 text-xs font-semibold text-brand-light uppercase tracking-wider"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="rule in sortedRules"
                            :key="rule.id"
                            class="border-b border-gray-100 hover:bg-gray-50"
                        >
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2">
                                    <Award class="w-4 h-4 text-brand-primary" />
                                    <span class="text-sm font-medium text-brand-dark">{{ rule.label }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="text-sm text-brand-dark font-mono">
                                    {{ rule.min_rating.toFixed(2) }} — {{ rule.max_rating.toFixed(2) }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="text-sm text-brand-dark">{{ rule.bonus_months }} mo</span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="text-sm text-brand-dark">{{ rule.salary_increase_pct }}%</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span
                                    v-if="rule.promotion_eligible"
                                    class="inline-block px-2 py-0.5 rounded text-xs bg-purple-100 text-purple-700 border border-purple-200"
                                >
                                    Yes
                                </span>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span
                                    v-if="rule.pip_required"
                                    class="inline-block px-2 py-0.5 rounded text-xs bg-red-100 text-red-700 border border-red-200"
                                >
                                    <AlertTriangle class="w-3 h-3 inline -mt-0.5" />
                                    Required
                                </span>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span
                                    class="inline-block w-2.5 h-2.5 rounded-full"
                                    :class="rule.is_active ? 'bg-emerald-500' : 'bg-gray-300'"
                                ></span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        @click="openEdit(rule)"
                                        title="Edit rule"
                                        class="p-1.5 text-brand-light hover:text-brand-primary hover:bg-gray-100 rounded-lg transition-colors"
                                    >
                                        <Edit3 class="w-4 h-4" />
                                    </button>
                                    <button
                                        @click="deleteRule(rule)"
                                        title="Delete rule"
                                        class="p-1.5 text-brand-light hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </MainCard>

        <!-- Add/Edit Modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm"
        >
            <div class="bg-white rounded-xl shadow-md w-full max-w-lg overflow-hidden">
                <div class="flex items-center justify-between p-4 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-brand-dark">
                        {{ editingRule ? "Edit Rule" : "Add Outcome Rule" }}
                    </h3>
                    <button @click="closeModal" class="p-1 hover:bg-gray-100 rounded-lg text-gray-500 min-w-6 min-h-6 flex items-center justify-center">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="saveRule" class="p-4 space-y-4 max-h-[70vh] overflow-y-auto">
                    <div>
                        <label class="block text-sm font-medium text-brand-dark mb-1">Label</label>
                        <input
                            v-model="form.label"
                            type="text"
                            required
                            placeholder="e.g. Outstanding"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-brand-primary focus:border-brand-primary"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-brand-dark mb-1">Min Rating</label>
                            <input
                                v-model.number="form.min_rating"
                                type="number"
                                step="0.01"
                                min="1.00"
                                max="5.00"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-brand-primary focus:border-brand-primary"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-brand-dark mb-1">Max Rating</label>
                            <input
                                v-model.number="form.max_rating"
                                type="number"
                                step="0.01"
                                min="1.00"
                                max="5.00"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-brand-primary focus:border-brand-primary"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-brand-dark mb-1">Bonus (months)</label>
                            <input
                                v-model.number="form.bonus_months"
                                type="number"
                                step="0.5"
                                min="0"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-brand-primary focus:border-brand-primary"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-brand-dark mb-1">Salary Increase (%)</label>
                            <input
                                v-model.number="form.salary_increase_pct"
                                type="number"
                                step="0.5"
                                min="0"
                                max="100"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-brand-primary focus:border-brand-primary"
                            />
                        </div>
                    </div>

                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.promotion_eligible"
                                type="checkbox"
                                class="rounded border-gray-300 text-brand-primary focus:ring-brand-primary"
                            />
                            <span class="text-sm text-brand-dark">Promotion Eligible</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.pip_required"
                                type="checkbox"
                                class="rounded border-gray-300 text-red-600 focus:ring-red-500"
                            />
                            <span class="text-sm text-brand-dark">PIP Required</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-gray-300 text-brand-primary focus:ring-brand-primary"
                            />
                            <span class="text-sm text-brand-dark">Active</span>
                        </label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-brand-dark mb-1">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            placeholder="Optional description..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-brand-primary focus:border-brand-primary"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                        <button
                            type="button"
                            @click="closeModal"
                            class="px-4 py-2 text-sm font-medium text-brand-dark hover:bg-gray-200 rounded-lg transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="saving || !form.label"
                            class="px-4 py-2 text-sm font-medium text-white bg-brand-primary hover:bg-brand-primary-dark rounded-lg transition-colors disabled:opacity-50"
                        >
                            {{ saving ? "Saving..." : editingRule ? "Update Rule" : "Create Rule" }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Confirmation Dialog -->
    <ConfirmationModal
        :show="showConfirmDialog"
        :title="confirmTitle"
        :message="confirmMessage"
        confirm-text="Delete"
        cancel-text="Cancel"
        type="danger"
        @confirm="async () => { if (confirmAction) await confirmAction(); showConfirmDialog = false; }"
        @cancel="showConfirmDialog = false"
    />
</template>
