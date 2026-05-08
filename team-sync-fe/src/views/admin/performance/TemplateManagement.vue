<script setup>
import { ref, computed, onMounted } from "vue";
import { storeToRefs } from "pinia";
import { usePerformanceReviewStore } from "@/stores/performanceReview";
import { Plus, Edit3, Trash2, Layout, X, Info, Scale, Check } from "lucide-vue-next";
import MainCard from "@/components/common/MainCard.vue";
import { useToast } from "@/composables/useToast";

const store = usePerformanceReviewStore();
const { templates, templatesLoading, sections } = storeToRefs(store);
const toast = useToast();

const showModal = ref(false);
const editingTemplate = ref(null);
const saving = ref(false);
const formErrors = ref({});

const defaultForm = () => ({
    name: "",
    description: "",
    is_active: true,
    is_default: false,
    sections: [], // { id, weight }
});

const form = ref(defaultForm());

const openCreate = () => {
    editingTemplate.value = null;
    form.value = defaultForm();
    formErrors.value = {};
    showModal.value = true;
};

const openEdit = (template) => {
    editingTemplate.value = template;
    form.value = {
        name: template.name,
        description: template.description || "",
        is_active: template.is_active,
        is_default: template.is_default,
        sections: template.sections.map((s) => ({
            id: s.id,
            weight: parseFloat(s.pivot.weight),
        })),
    };
    formErrors.value = {};
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingTemplate.value = null;
    form.value = defaultForm();
    formErrors.value = {};
};

const addSection = (section) => {
    if (form.value.sections.some((s) => s.id === section.id)) return;
    form.value.sections.push({
        id: section.id,
        weight: 0,
    });
};

const removeSection = (sectionId) => {
    form.value.sections = form.value.sections.filter((s) => s.id !== sectionId);
};

const totalWeight = computed(() => {
    return form.value.sections.reduce((sum, s) => sum + parseFloat(s.weight || 0), 0);
});

const validateTemplateForm = () => {
    const errors = {};

    if (!String(form.value.name || "").trim()) {
        errors.name = "Template name is required";
    }

    if (!form.value.sections.length) {
        errors.sections = "Add at least one section";
    }

    if (form.value.sections.length && totalWeight.value !== 100) {
        errors.weight = "Total weight must be exactly 100%";
    }

    formErrors.value = errors;
    return Object.keys(errors).length === 0;
};

const saveTemplate = async () => {
    if (!validateTemplateForm()) {
        const firstError = Object.values(formErrors.value)[0] || "Please complete the required fields";
        toast.error(firstError);
        return;
    }

    saving.value = true;
    try {
        if (editingTemplate.value) {
            await store.updateTemplate(editingTemplate.value.id, form.value);
            toast.success("Template updated successfully");
        } else {
            await store.createTemplate(form.value);
            toast.success("Template created successfully");
        }
        closeModal();
    } catch (error) {
        const message = error.response?.data?.message || "Failed to save template";
        toast.error(message);
    } finally {
        saving.value = false;
    }
};

const deleteTemplate = async (template) => {
    if (!confirm(`Delete template "${template.name}"?`)) return;
    try {
        await store.deleteTemplate(template.id);
        toast.success("Template deleted successfully");
    } catch (error) {
        toast.error(error.response?.data?.message || "Failed to delete template");
    }
};

onMounted(() => {
    store.fetchTemplates();
    store.fetchActiveSections();
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-brand-dark">Review Templates</h1>
                <p class="text-brand-light mt-1">Configure role-specific assessment parameters and weights</p>
            </div>
            <button
                @click="openCreate"
                class="flex items-center gap-2 px-4 py-2 bg-brand-primary text-white rounded-lg hover:bg-brand-primary-dark transition-colors"
            >
                <Plus class="w-4 h-4" />
                New Template
            </button>
        </div>

        <MainCard>
            <div v-if="templatesLoading" class="flex items-center justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-primary"></div>
            </div>

            <div v-else-if="!templates.length" class="text-center py-12">
                <Layout class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                <h3 class="text-lg font-medium text-brand-dark">No templates found</h3>
                <p class="text-sm text-brand-light mt-1">Create your first performance review template.</p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-4 text-xs font-semibold text-brand-light uppercase">Name</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-brand-light uppercase">
                                Sections
                            </th>
                            <th class="text-center py-3 px-4 text-xs font-semibold text-brand-light uppercase">
                                Default
                            </th>
                            <th class="text-center py-3 px-4 text-xs font-semibold text-brand-light uppercase">
                                Status
                            </th>
                            <th class="text-right py-3 px-4 text-xs font-semibold text-brand-light uppercase">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="tpl in templates" :key="tpl.id" class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <div class="font-medium text-brand-dark">{{ tpl.name }}</div>
                                <div class="text-xs text-brand-light">{{ tpl.description }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="text-sm text-brand-dark">{{ tpl.sections_count }} sections</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <Check v-if="tpl.is_default" class="w-4 h-4 text-emerald-500 mx-auto" />
                                <span v-else class="text-gray-300">—</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span
                                    :class="tpl.is_active ? 'text-emerald-600' : 'text-gray-400'"
                                    class="text-xs font-medium"
                                >
                                    {{ tpl.is_active ? "Active" : "Inactive" }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        @click="openEdit(tpl)"
                                        class="p-1.5 text-brand-light hover:text-brand-primary rounded-lg"
                                    >
                                        <Edit3 class="w-4 h-4" />
                                    </button>
                                    <button
                                        @click="deleteTemplate(tpl)"
                                        class="p-1.5 text-brand-light hover:text-red-600 rounded-lg"
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

        <!-- Template Modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm"
        >
            <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="flex items-center justify-between p-4 border-b">
                    <h3 class="text-lg font-bold text-brand-dark">
                        {{ editingTemplate ? "Edit Template" : "New Template" }}
                    </h3>
                    <button @click="closeModal" class="p-1 hover:bg-gray-100 rounded-lg"><X class="w-5 h-5" /></button>
                </div>

                <div class="flex-1 overflow-y-auto p-6 flex gap-8">
                    <!-- Left: General Info -->
                    <div class="w-1/3 space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Template Name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-brand-primary"
                                placeholder="e.g. Senior Software Engineer"
                            />
                            <p v-if="formErrors.name" class="text-xs text-red-600 mt-1">{{ formErrors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Description</label>
                            <textarea
                                v-model="form.description"
                                rows="3"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-brand-primary"
                                placeholder="Template usage details..."
                            ></textarea>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="form.is_active" type="checkbox" class="rounded text-brand-primary" />
                                <span class="text-sm">Active</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="form.is_default" type="checkbox" class="rounded text-brand-primary" />
                                <span class="text-sm">Set as Default Template</span>
                            </label>
                        </div>

                        <div class="pt-4 border-t">
                            <h4 class="text-sm font-bold mb-2">Available Sections</h4>
                            <div class="space-y-2">
                                <button
                                    v-for="section in sections"
                                    :key="section.id"
                                    @click="addSection(section)"
                                    :disabled="form.sections.some((s) => s.id === section.id)"
                                    class="w-full text-left px-3 py-2 text-sm border rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:bg-gray-100 flex items-center justify-between group"
                                >
                                    {{ section.name }}
                                    <Plus class="w-3 h-3 text-brand-primary opacity-0 group-hover:opacity-100" />
                                </button>
                            </div>
                            <p v-if="formErrors.sections" class="text-xs text-red-600 mt-2">
                                {{ formErrors.sections }}
                            </p>
                        </div>
                    </div>

                    <!-- Right: Section Mapping & Weights -->
                    <div class="flex-1 space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold flex items-center gap-2">
                                <Scale class="w-4 h-4 text-brand-primary" />
                                Section Mapping & Weights
                            </h4>
                            <div
                                :class="totalWeight === 100 ? 'text-emerald-600' : 'text-red-600'"
                                class="text-sm font-bold"
                            >
                                Total: {{ totalWeight }}%
                            </div>
                        </div>

                        <div
                            v-if="!form.sections.length"
                            class="text-center py-12 border-2 border-dashed rounded-xl bg-gray-50"
                        >
                            <p class="text-sm text-brand-light">
                                No sections added yet. Select from available sections on the left.
                            </p>
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="mappedSection in form.sections"
                                :key="mappedSection.id"
                                class="p-4 border rounded-xl bg-white shadow-sm flex items-center gap-4"
                            >
                                <div class="flex-1">
                                    <div class="font-medium text-brand-dark">
                                        {{ sections.find((s) => s.id === mappedSection.id)?.name }}
                                    </div>
                                    <div class="text-xs text-brand-light uppercase">
                                        {{ sections.find((s) => s.id === mappedSection.id)?.topsis_category }}
                                    </div>
                                </div>
                                <div class="w-24">
                                    <div class="relative">
                                        <input
                                            v-model.number="mappedSection.weight"
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="w-full pl-3 pr-8 py-2 border rounded-lg text-right"
                                        />
                                        <span class="absolute right-3 top-2 text-gray-400">%</span>
                                    </div>
                                </div>
                                <button
                                    @click="removeSection(mappedSection.id)"
                                    class="p-2 text-gray-400 hover:text-red-600 transition-colors"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="totalWeight !== 100 && form.sections.length"
                            class="p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-center gap-3 text-amber-700 text-sm"
                        >
                            <Info class="w-4 h-4 flex-shrink-0" />
                            Weights must total exactly 100%. Current gap: {{ 100 - totalWeight }}%
                        </div>
                        <p v-if="formErrors.weight" class="text-xs text-red-600">{{ formErrors.weight }}</p>
                    </div>
                </div>

                <div class="p-4 border-t bg-gray-50 flex justify-end gap-3">
                    <button
                        @click="closeModal"
                        class="px-4 py-2 text-sm font-medium hover:bg-gray-200 rounded-lg transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        @click="saveTemplate"
                        :disabled="saving || totalWeight !== 100"
                        class="px-6 py-2 text-sm font-bold text-white bg-brand-primary hover:bg-brand-primary-dark rounded-lg disabled:opacity-50 transition-all"
                    >
                        {{ saving ? "Saving..." : editingTemplate ? "Update Template" : "Create Template" }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
