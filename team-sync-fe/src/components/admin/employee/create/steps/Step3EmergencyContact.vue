<script setup lang="ts">
import { computed } from "vue";
import {
  User,
  Phone,
  MapPin,
  FileText,
  ChevronRight,
  Globe,
  Mail,
} from "lucide-vue-next";
import { Input, TextArea, Select } from "@/components/common/form";
import RightSidebarStep3 from "@/components/admin/employee/create/RightSidebarStep3.vue";

interface Props {
  modelValue: {
    emergency_contact_name: string;
    emergency_contact_relationship: string;
    emergency_contact_phone: string;
    emergency_contact_email: string;
    additional_notes: string;
    preferred_language: string;
  };
  errors?: any;
}

const props = defineProps<Props>();
const emit = defineEmits(["update:modelValue"]);

const form = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});
</script>

<template>
  <div class="flex flex-col 2xl:flex-row gap-5 items-stretch 2xl:items-start pr-0">
    <div class="flex-1 w-full space-y-6">
      <!-- Emergency Contact Section -->
      <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
        <div class="flex items-center gap-3 mb-6">
          <div
            class="w-12 h-12 bg-red-50 rounded-[12px] flex items-center justify-center"
          >
            <Phone class="w-6 h-6 text-red-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-xl font-bold">Emergency Contact</h3>
            <p class="text-brand-light text-sm font-normal">
              Person to contact in case of emergency
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <!-- Emergency Contact Name -->
          <div class="mb-4">
            <Input
              id="emergency_contact_name"
              name="emergency_contact_name"
              v-model="form.emergency_contact_name"
              label="Full Name *"
              placeholder="Emergency contact name"
              :error="
                props.errors?.['emergency_contacts.0.full_name']?.join(', ')
              "
              required
            >
              <template #icon>
                <User class="w-5 h-5 text-gray-400" />
              </template>
            </Input>
          </div>

          <!-- Emergency Contact Relationship -->
          <div class="mb-4">
            <Input
              id="emergency_contact_relationship"
              name="emergency_contact_relationship"
              v-model="form.emergency_contact_relationship"
              label="Relationship *"
              placeholder="e.g., Spouse, Parent, Sibling"
              :error="
                props.errors?.['emergency_contacts.0.relationship']?.join(', ')
              "
              required
            >
              <template #icon>
                <User class="w-5 h-5 text-gray-400" />
              </template>
            </Input>
          </div>

          <!-- Emergency Contact Phone -->
          <div class="mb-4">
            <Input
              id="emergency_contact_phone"
              name="emergency_contact_phone"
              v-model="form.emergency_contact_phone"
              label="Phone Number *"
              placeholder="+1 (555) 123-4567"
              :error="props.errors?.['emergency_contacts.0.phone']?.join(', ')"
              required
            >
              <template #icon>
                <Phone class="w-5 h-5 text-gray-400" />
              </template>
            </Input>
          </div>

          <!-- Emergency Contact Email -->
          <div class="mb-4">
            <Input
              id="emergency_contact_email"
              name="emergency_contact_email"
              v-model="form.emergency_contact_email"
              label="Email Address"
              placeholder="emergency@email.com"
              type="email"
              :error="props.errors?.['emergency_contacts.0.email']?.join(', ')"
            >
              <template #icon>
                <Mail class="w-5 h-5 text-gray-400" />
              </template>
            </Input>
          </div>
        </div>
      </div>

      <!-- Additional Information Section -->
      <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
        <div class="flex items-center gap-3 mb-6">
          <div
            class="w-12 h-12 bg-orange-50 rounded-[12px] flex items-center justify-center"
          >
            <FileText class="w-6 h-6 text-orange-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-xl font-bold">
              Additional Information
            </h3>
            <p class="text-brand-light text-sm font-normal">
              Optional details and preferences
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <!-- Preferred Language -->
          <div class="mb-4">
            <Select
              id="preferred_language"
              name="preferred_language"
              v-model="form.preferred_language"
              label="Preferred Language"
              placeholder="Select language"
              :error="props.errors?.preferred_language?.join(', ')"
              :options="[
                { value: 'en', label: 'English' },
                { value: 'id', label: 'Indonesian' },
                { value: 'es', label: 'Spanish' },
                { value: 'fr', label: 'French' },
              ]"
            >
              <template #icon>
                <Globe class="w-5 h-5 text-gray-400" />
              </template>
            </Select>
          </div>

          <!-- Additional Notes -->
          <div class="lg:col-span-2 mb-4">
            <TextArea
              id="additional_notes"
              name="additional_notes"
              v-model="form.additional_notes"
              label="Additional Notes"
              placeholder="Any additional information about the employee..."
              :error="props.errors?.additional_notes?.join(', ')"
              rows="4"
            >
              <template #icon>
                <FileText class="w-5 h-5 text-gray-400" />
              </template>
            </TextArea>
          </div>
        </div>
      </div>
    </div>

    <RightSidebarStep3 />
  </div>
</template>
