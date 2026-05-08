<template>
    <div class="attendance-settings-container min-h-screen bg-neutral-900 text-neutral-100 p-8">
        <div class="max-w-7xl mx-auto space-y-8 relative">
            <div
                class="absolute top-0 right-0 -mr-32 -mt-32 w-96 h-96 bg-purple-600/20 rounded-full blur-[120px] pointer-events-none"
            ></div>
            <div
                class="absolute bottom-0 left-0 -ml-32 w-96 h-96 bg-blue-600/20 rounded-full blur-[120px] pointer-events-none"
            ></div>

            <header
                class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-end gap-6 border-b border-white/30 pb-8"
            >
                <div class="space-y-2">
                    <h1
                        class="text-5xl font-extralight tracking-tight font-display bg-clip-text text-transparent bg-gradient-to-r from-white to-neutral-500"
                    >
                        System Configuration
                    </h1>
                    <p class="text-neutral-400 font-light tracking-wide max-w-xl">
                        Configure global attendance rules, grace periods, and manage holiday schedules across the
                        organization.
                    </p>
                </div>
            </header>

            <!-- Tabs Navigation -->
            <nav class="relative z-10 flex gap-8 border-b border-white/15" aria-label="Tabs">
                <button
                    v-for="tab in ['Attendance Policies', 'Leave Entitlements', 'Holiday Calendars']"
                    :key="tab"
                    @click="activeTab = tab"
                    class="pb-4 text-sm font-medium tracking-wider uppercase transition-all duration-300 relative group"
                    :class="activeTab === tab ? 'text-white' : 'text-neutral-500 hover:text-neutral-300'"
                >
                    {{ tab }}
                    <span
                        class="absolute bottom-0 left-0 w-full h-[2px] bg-gradient-to-r from-purple-500 to-blue-500 transition-transform duration-300 origin-left"
                        :class="activeTab === tab ? 'scale-x-100' : 'scale-x-0 group-hover:scale-x-50'"
                    ></span>
                </button>
            </nav>

            <transition name="fade" mode="out-in">
                <section
                    v-if="activeTab === 'Attendance Policies'"
                    key="policies"
                    class="relative z-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-if="policyStore.loading"
                        class="text-neutral-400 p-8 flex justify-center w-full col-span-full"
                    >
                        Loading policies...
                    </div>
                    <div
                        v-else-if="policyStore.error"
                        class="text-rose-400 p-8 flex justify-center w-full col-span-full"
                    >
                        {{ policyStore.error }}
                    </div>

                    <template v-else>
                        <div
                            v-for="policy in policyStore.policies"
                            :key="policy.id"
                            class="policy-card group p-6 rounded-2xl bg-white/[0.08] border border-white/15 hover:bg-white/[0.12] transition-all duration-500 hover:-translate-y-1 hover:shadow-2xl hover:shadow-purple-500/10"
                        >
                            <div class="flex justify-between items-start mb-6">
                                <h3 class="text-xl font-medium capitalize">
                                    {{ policy.employment_type.replace("_", " ") }}
                                </h3>
                                <span
                                    class="px-3 py-1 text-xs font-semibold uppercase tracking-wider rounded-full bg-white/10 text-neutral-300"
                                >
                                    Policy
                                </span>
                            </div>

                            <div class="space-y-4 text-sm">
                                <div class="flex justify-between items-center border-b border-white/15 pb-2">
                                    <span class="text-neutral-400 font-light">Work Hours</span>
                                    <span class="font-medium text-purple-400">
                                        {{ policy.work_start_time.substring(0, 5) }} -
                                        {{ policy.work_end_time.substring(0, 5) }}
                                    </span>
                                </div>
                                <div class="flex justify-between items-center border-b border-white/15 pb-2">
                                    <span class="text-neutral-400 font-light">Late Grace Period</span>
                                    <span class="font-medium text-rose-400">{{ policy.late_grace_minutes }} mins</span>
                                </div>
                                <div class="flex justify-between items-center border-b border-white/15 pb-2">
                                    <span class="text-neutral-400 font-light">Half Day Min</span>
                                    <span class="font-medium text-blue-400">{{ policy.half_day_min_hours }} hours</span>
                                </div>
                                <div class="flex justify-between items-center border-b border-white/15 pb-2">
                                    <span class="text-neutral-400 font-light">Required Work Days</span>
                                    <span class="font-medium text-white">
                                        {{ policy.work_days_per_week }} days/week
                                    </span>
                                </div>
                            </div>

                            <button
                                class="w-full mt-8 py-3 rounded-xl bg-white/10 hover:bg-white/10 border border-white/30 text-sm tracking-wide font-medium transition-all focus:outline-none focus:ring-2 focus:ring-purple-500/50"
                                type="button"
                                @click="openPolicyModal(policy)"
                            >
                                Edit Policy
                            </button>
                        </div>
                    </template>

                    <div
                        class="policy-card flex flex-col items-center justify-center p-6 rounded-2xl border border-dashed border-white/20 text-neutral-500 bg-white/[0.02] min-h-[300px]"
                    >
                        <svg class="w-12 h-12 mb-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M12 4v16m8-8H4"
                            ></path>
                        </svg>
                        <span class="font-medium tracking-wide text-center">
                            Custom policy creation is not available from the current API.
                        </span>
                        <span class="text-xs text-neutral-600 mt-2 text-center">
                            Edit existing employment-type policies instead.
                        </span>
                    </div>
                </section>

                <section
                    v-else-if="activeTab === 'Leave Entitlements'"
                    key="entitlements"
                    class="relative z-10 space-y-6"
                >
                    <div class="flex justify-between items-center">
                        <h2 class="text-2xl font-light">Leave Quotas & Rules</h2>
                    </div>

                    <div v-if="entitlementStore.loading" class="text-neutral-400 p-8 flex justify-center w-full">
                        Loading entitlements...
                    </div>
                    <div v-else-if="entitlementStore.error" class="text-rose-400 p-8 flex justify-center w-full">
                        {{ entitlementStore.error }}
                    </div>

                    <div v-else v-for="(group, type) in entitlementStore.groupedEntitlements" :key="type" class="mb-8">
                        <h3 class="text-xl font-medium capitalize mb-4 text-purple-300">
                            {{ type.replace("_", " ") }}
                        </h3>
                        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="entitlement in group"
                                :key="entitlement.id"
                                class="policy-card p-6 rounded-2xl bg-white/[0.08] border border-white/15 hover:bg-white/[0.12] transition-all duration-300"
                            >
                                <div class="flex justify-between items-start mb-4">
                                    <h4 class="text-lg font-medium capitalize">
                                        {{ entitlement.leave_type.replace("_", " ") }}
                                    </h4>
                                    <span
                                        v-if="!entitlement.is_eligible"
                                        class="px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider rounded bg-rose-500/20 text-rose-300 border border-rose-500/30"
                                    >
                                        Ineligible
                                    </span>
                                    <span
                                        v-else-if="entitlement.is_paid"
                                        class="px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/30"
                                    >
                                        Paid
                                    </span>
                                    <span
                                        v-else
                                        class="px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider rounded bg-amber-500/20 text-amber-300 border border-amber-500/30"
                                    >
                                        Unpaid
                                    </span>
                                </div>

                                <div class="space-y-3 text-sm">
                                    <div class="flex justify-between items-center border-b border-white/15 pb-2">
                                        <span class="text-neutral-400 font-light">Quota</span>
                                        <span
                                            class="font-medium text-white"
                                            v-if="
                                                entitlement.quota_scope === 'unlimited' ||
                                                entitlement.quota_scope === 'unpaid'
                                            "
                                        >
                                            Unlimited
                                        </span>
                                        <span class="font-medium text-white" v-else>
                                            {{ entitlement.quota_days }} days ({{ entitlement.quota_scope }})
                                        </span>
                                    </div>
                                    <div
                                        class="flex justify-between items-center border-b border-white/15 pb-2"
                                        v-if="entitlement.carry_over_max_days > 0"
                                    >
                                        <span class="text-neutral-400 font-light">Max Carry Over</span>
                                        <span class="font-medium text-blue-400">
                                            {{ entitlement.carry_over_max_days }} days
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center border-b border-white/15 pb-2">
                                        <span class="text-neutral-400 font-light">Requires Proof</span>
                                        <span
                                            class="font-medium"
                                            :class="
                                                entitlement.requires_attachment ? 'text-rose-400' : 'text-neutral-500'
                                            "
                                        >
                                            {{ entitlement.requires_attachment ? "Yes" : "No" }}
                                        </span>
                                    </div>
                                </div>

                                <button
                                    class="w-full mt-6 py-2 rounded-lg bg-white/10 hover:bg-white/10 border border-white/30 text-xs tracking-wide font-medium transition-all"
                                    type="button"
                                    @click="openEntitlementModal(entitlement)"
                                >
                                    Edit Rules
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-else-if="activeTab === 'Holiday Calendars'" key="holidays" class="relative z-10 space-y-6">
                    <div class="flex justify-between items-center">
                        <h2 class="text-2xl font-light">Upcoming Holidays</h2>
                        <button
                            class="px-6 py-2.5 rounded-full bg-white text-black font-medium text-sm hover:scale-105 transition-transform duration-300"
                            type="button"
                            @click="openHolidayModal()"
                        >
                            + Add Holiday
                        </button>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-white/30 bg-white/[0.06] backdrop-blur-md">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-white/30 text-xs uppercase tracking-widest text-neutral-500">
                                    <th class="p-4 font-medium">Holiday Name</th>
                                    <th class="p-4 font-medium">Date</th>
                                    <th class="p-4 font-medium">Type</th>
                                    <th class="p-4 font-medium text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr v-if="holidayStore.error" class="text-center text-rose-500 bg-rose-500/5">
                                    <td colspan="4" class="p-8 font-light flex items-center justify-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                            ></path>
                                        </svg>
                                        Failed to load holidays. The service might be temporarily unavailable.
                                    </td>
                                </tr>
                                <tr v-else-if="holidayStore.loading" class="text-center text-neutral-400">
                                    <td
                                        colspan="4"
                                        class="p-8 font-light italic flex items-center justify-center gap-2"
                                    >
                                        <svg
                                            class="animate-spin w-5 h-5"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                        >
                                            <circle
                                                class="opacity-25"
                                                cx="12"
                                                cy="12"
                                                r="10"
                                                stroke="currentColor"
                                                stroke-width="4"
                                            ></circle>
                                            <path
                                                class="opacity-75"
                                                fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                            ></path>
                                        </svg>
                                        Loading holidays...
                                    </td>
                                </tr>
                                <tr
                                    v-else-if="!holidayStore.paginatedHolidays?.length"
                                    class="text-center text-neutral-500"
                                >
                                    <td colspan="4" class="p-8 font-light italic">No holidays configured yet.</td>
                                </tr>
                                <tr
                                    v-else
                                    v-for="holiday in holidayStore.paginatedHolidays"
                                    :key="holiday.id"
                                    class="hover:bg-white/10 transition-colors"
                                >
                                    <td class="p-4 font-medium text-neutral-200">
                                        {{ holiday.name || holiday.description }}
                                    </td>
                                    <td class="p-4 text-neutral-400">{{ holiday.date }}</td>
                                    <td class="p-4">
                                        <span
                                            class="px-2 py-1 text-xs rounded bg-neutral-800 text-neutral-300 border border-neutral-700"
                                        >
                                            {{ formatHolidayType(holiday.type) }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <button
                                            class="text-sm text-neutral-500 hover:text-white transition-colors"
                                            type="button"
                                            @click="openHolidayModal(holiday)"
                                        >
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </transition>
        </div>

        <ModalWrapper :show="isPolicyModalOpen" title="Edit Attendance Policy" maxWidth="2xl" @close="closePolicyModal">
            <form class="space-y-5" @submit.prevent="submitPolicyForm">
                <p class="text-sm text-brand-light mb-2">
                    Editing policy for
                    <span class="font-semibold text-brand-dark">
                        {{ formatLabel(selectedPolicy?.employment_type) }}
                    </span>
                    .
                </p>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-brand-dark mb-2">Work Start Time</label>
                        <input v-model="policyForm.work_start_time" type="time" step="1" required class="form-input" />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-brand-dark mb-2">Work End Time</label>
                        <input v-model="policyForm.work_end_time" type="time" step="1" required class="form-input" />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-brand-dark mb-2">Work Days Per Week</label>
                        <input
                            v-model.number="policyForm.work_days_per_week"
                            type="number"
                            min="1"
                            max="7"
                            required
                            class="form-input"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-brand-dark mb-2">Late Grace Minutes</label>
                        <input
                            v-model.number="policyForm.late_grace_minutes"
                            type="number"
                            min="0"
                            max="120"
                            required
                            class="form-input"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-brand-dark mb-2">Half Day Minimum Hours</label>
                        <input
                            v-model.number="policyForm.half_day_min_hours"
                            type="number"
                            min="0"
                            max="12"
                            step="0.25"
                            required
                            class="form-input"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-brand-dark mb-2">Warning Absent %</label>
                        <input
                            v-model.number="policyForm.warning_absent_pct"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            required
                            class="form-input"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-brand-dark mb-3">Default Working Weekdays</label>
                    <div class="grid gap-2 sm:grid-cols-2 md:grid-cols-4">
                        <label
                            v-for="day in weekdays"
                            :key="day.value"
                            class="flex items-center gap-2 text-sm text-brand-dark border border-[#DCDEDD] rounded-lg px-3 py-2"
                        >
                            <input
                                v-model="policyForm.default_working_weekdays"
                                type="checkbox"
                                :value="day.value"
                                class="rounded"
                            />
                            {{ day.label }}
                        </label>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button
                        type="button"
                        :disabled="isSubmittingPolicy"
                        class="modal-secondary-button"
                        @click="closePolicyModal"
                    >
                        Cancel
                    </button>
                    <button type="submit" :disabled="isSubmittingPolicy" class="modal-primary-button">
                        {{ isSubmittingPolicy ? "Saving..." : "Save Policy" }}
                    </button>
                </div>
            </form>
        </ModalWrapper>

        <ModalWrapper
            :show="isEntitlementModalOpen"
            title="Edit Leave Entitlement"
            maxWidth="2xl"
            @close="closeEntitlementModal"
        >
            <form class="space-y-5" @submit.prevent="submitEntitlementForm">
                <p class="text-sm text-brand-light mb-2">
                    Editing
                    <span class="font-semibold text-brand-dark">
                        {{ formatLabel(selectedEntitlement?.leave_type) }}
                    </span>
                    for
                    <span class="font-semibold text-brand-dark">
                        {{ formatLabel(selectedEntitlement?.employment_type) }}
                    </span>
                    .
                </p>

                <div class="grid gap-3 md:grid-cols-3">
                    <label
                        class="flex items-center gap-2 text-sm text-brand-dark border border-[#DCDEDD] rounded-lg px-3 py-2"
                    >
                        <input v-model="entitlementForm.is_eligible" type="checkbox" class="rounded" />
                        Eligible
                    </label>
                    <label
                        class="flex items-center gap-2 text-sm text-brand-dark border border-[#DCDEDD] rounded-lg px-3 py-2"
                    >
                        <input v-model="entitlementForm.is_paid" type="checkbox" class="rounded" />
                        Paid Leave
                    </label>
                    <label
                        class="flex items-center gap-2 text-sm text-brand-dark border border-[#DCDEDD] rounded-lg px-3 py-2"
                    >
                        <input v-model="entitlementForm.requires_reason" type="checkbox" class="rounded" />
                        Requires Reason
                    </label>
                    <label
                        class="flex items-center gap-2 text-sm text-brand-dark border border-[#DCDEDD] rounded-lg px-3 py-2"
                    >
                        <input v-model="entitlementForm.requires_attachment" type="checkbox" class="rounded" />
                        Requires Proof
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-brand-dark mb-2">Quota Scope</label>
                        <select v-model="entitlementForm.quota_scope" class="form-input">
                            <option value="annual">Annual</option>
                            <option value="per_occurrence">Per Occurrence</option>
                            <option value="unlimited">Unlimited</option>
                            <option value="unpaid">Unpaid</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-brand-dark mb-2">Quota Days</label>
                        <input
                            v-model.number="entitlementForm.quota_days"
                            type="number"
                            min="0"
                            step="0.5"
                            class="form-input"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-brand-dark mb-2">Carry Over Max Days</label>
                        <input
                            v-model.number="entitlementForm.carry_over_max_days"
                            type="number"
                            min="0"
                            class="form-input"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-brand-dark mb-2">Max Attachment Size (KB)</label>
                        <input
                            v-model.number="entitlementForm.max_attachment_size_kb"
                            type="number"
                            min="0"
                            class="form-input"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-brand-dark mb-2">Allowed MIME Types</label>
                    <input
                        v-model="allowedMimeTypesInput"
                        type="text"
                        placeholder="image/jpeg, image/png, application/pdf"
                        class="form-input"
                    />
                    <p class="mt-1 text-xs text-gray-500">
                        Separate MIME types with commas. Leave blank for no restriction.
                    </p>
                </div>

                <div class="flex gap-3 pt-2">
                    <button
                        type="button"
                        :disabled="isSubmittingEntitlement"
                        class="modal-secondary-button"
                        @click="closeEntitlementModal"
                    >
                        Cancel
                    </button>
                    <button type="submit" :disabled="isSubmittingEntitlement" class="modal-primary-button">
                        {{ isSubmittingEntitlement ? "Saving..." : "Save Rules" }}
                    </button>
                </div>
            </form>
        </ModalWrapper>

        <ModalWrapper
            :show="isHolidayModalOpen"
            :title="selectedHoliday ? 'Edit Holiday' : 'Add Holiday'"
            maxWidth="md"
            @close="closeHolidayModal"
        >
            <form class="space-y-4" @submit.prevent="submitHolidayForm">
                <div>
                    <label class="block text-sm font-semibold text-brand-dark mb-2">Date</label>
                    <input v-model="holidayForm.date" type="date" required class="form-input" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-brand-dark mb-2">Name</label>
                    <input
                        v-model="holidayForm.name"
                        type="text"
                        required
                        placeholder="e.g., Independence Day"
                        class="form-input"
                    />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-brand-dark mb-2">Type</label>
                    <select v-model="holidayForm.type" required class="form-input">
                        <option value="national_holiday">National Holiday</option>
                        <option value="collective_leave">Collective Leave (Cuti Bersama)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-brand-dark mb-2">Applies To</label>
                    <input
                        v-model="holidayAppliesToInput"
                        type="text"
                        placeholder="Optional, comma separated"
                        class="form-input"
                    />
                    <p class="mt-1 text-xs text-gray-500">Leave blank to apply company-wide.</p>
                </div>

                <div class="flex gap-3 pt-2">
                    <button
                        type="button"
                        :disabled="isSubmittingHoliday"
                        class="modal-secondary-button"
                        @click="closeHolidayModal"
                    >
                        Cancel
                    </button>
                    <button type="submit" :disabled="isSubmittingHoliday" class="modal-primary-button">
                        {{ isSubmittingHoliday ? "Saving..." : selectedHoliday ? "Update Holiday" : "Create Holiday" }}
                    </button>
                </div>
            </form>
        </ModalWrapper>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import ModalWrapper from "@/components/common/ModalWrapper.vue";
import { useHolidayCalendarStore } from "@/stores/holidayCalendar";
import { useAttendancePolicyStore } from "@/stores/attendancePolicy";
import { useLeaveEntitlementStore } from "@/stores/leaveEntitlement";
import { useToast } from "@/composables/useToast";

const activeTab = ref("Attendance Policies");
const holidayStore = useHolidayCalendarStore();
const policyStore = useAttendancePolicyStore();
const entitlementStore = useLeaveEntitlementStore();
const toast = useToast();

const weekdays = [
    { value: "monday", label: "Monday" },
    { value: "tuesday", label: "Tuesday" },
    { value: "wednesday", label: "Wednesday" },
    { value: "thursday", label: "Thursday" },
    { value: "friday", label: "Friday" },
    { value: "saturday", label: "Saturday" },
    { value: "sunday", label: "Sunday" },
];

const selectedPolicy = ref(null);
const selectedEntitlement = ref(null);
const selectedHoliday = ref(null);
const isPolicyModalOpen = ref(false);
const isEntitlementModalOpen = ref(false);
const isHolidayModalOpen = ref(false);
const isSubmittingPolicy = ref(false);
const isSubmittingEntitlement = ref(false);
const isSubmittingHoliday = ref(false);
const allowedMimeTypesInput = ref("");
const holidayAppliesToInput = ref("");

const policyForm = ref({
    work_start_time: "",
    work_end_time: "",
    work_days_per_week: 5,
    default_working_weekdays: [],
    late_grace_minutes: 0,
    half_day_min_hours: 4,
    warning_absent_pct: 0,
});

const entitlementForm = ref({
    is_eligible: true,
    is_paid: false,
    quota_scope: "annual",
    quota_days: 0,
    carry_over_max_days: 0,
    requires_attachment: false,
    requires_reason: false,
    allowed_mime_types: [],
    max_attachment_size_kb: null,
});

const holidayForm = ref({
    date: "",
    name: "",
    type: "national_holiday",
    applies_to: [],
});

const normalizeTimeForInput = (value) => (value ? String(value).slice(0, 8) : "");
const formatLabel = (value) => String(value || "-").replaceAll("_", " ");
const formatHolidayType = (type) => (type === "collective_leave" ? "Collective Leave" : "National Holiday");
const commaSeparatedToArray = (value) =>
    String(value || "")
        .split(",")
        .map((item) => item.trim())
        .filter(Boolean);

const openPolicyModal = (policy) => {
    selectedPolicy.value = policy;
    policyForm.value = {
        work_start_time: normalizeTimeForInput(policy.work_start_time),
        work_end_time: normalizeTimeForInput(policy.work_end_time),
        work_days_per_week: Number(policy.work_days_per_week || 5),
        default_working_weekdays: Array.isArray(policy.default_working_weekdays)
            ? [...policy.default_working_weekdays]
            : [],
        late_grace_minutes: Number(policy.late_grace_minutes || 0),
        half_day_min_hours: Number(policy.half_day_min_hours || 0),
        warning_absent_pct: Number(policy.warning_absent_pct || 0),
    };
    isPolicyModalOpen.value = true;
};

const closePolicyModal = () => {
    isPolicyModalOpen.value = false;
    selectedPolicy.value = null;
};

const submitPolicyForm = async () => {
    if (!selectedPolicy.value?.id) return;

    isSubmittingPolicy.value = true;
    try {
        await policyStore.updatePolicy(selectedPolicy.value.id, policyForm.value);
        toast.success("Policy updated", "Attendance policy has been updated successfully.");
        closePolicyModal();
    } catch (error) {
        toast.error(
            "Failed to update policy",
            policyStore.error || error?.response?.data?.message || "Failed to save attendance policy.",
        );
    } finally {
        isSubmittingPolicy.value = false;
    }
};

const openEntitlementModal = (entitlement) => {
    selectedEntitlement.value = entitlement;
    entitlementForm.value = {
        is_eligible: Boolean(entitlement.is_eligible),
        is_paid: Boolean(entitlement.is_paid),
        quota_scope: entitlement.quota_scope || "annual",
        quota_days: entitlement.quota_days === null ? null : Number(entitlement.quota_days || 0),
        carry_over_max_days:
            entitlement.carry_over_max_days === null ? null : Number(entitlement.carry_over_max_days || 0),
        requires_attachment: Boolean(entitlement.requires_attachment),
        requires_reason: Boolean(entitlement.requires_reason),
        allowed_mime_types: Array.isArray(entitlement.allowed_mime_types) ? [...entitlement.allowed_mime_types] : [],
        max_attachment_size_kb:
            entitlement.max_attachment_size_kb === null ? null : Number(entitlement.max_attachment_size_kb || 0),
    };
    allowedMimeTypesInput.value = entitlementForm.value.allowed_mime_types.join(", ");
    isEntitlementModalOpen.value = true;
};

const closeEntitlementModal = () => {
    isEntitlementModalOpen.value = false;
    selectedEntitlement.value = null;
    allowedMimeTypesInput.value = "";
};

const submitEntitlementForm = async () => {
    if (!selectedEntitlement.value?.id) return;

    isSubmittingEntitlement.value = true;
    try {
        await entitlementStore.updateEntitlement(selectedEntitlement.value.id, {
            ...entitlementForm.value,
            allowed_mime_types: commaSeparatedToArray(allowedMimeTypesInput.value),
        });
        toast.success("Entitlement updated", "Leave entitlement rules have been updated successfully.");
        closeEntitlementModal();
    } catch (error) {
        toast.error(
            "Failed to update entitlement",
            entitlementStore.error || error?.response?.data?.message || "Failed to save leave entitlement.",
        );
    } finally {
        isSubmittingEntitlement.value = false;
    }
};

const openHolidayModal = (holiday = null) => {
    selectedHoliday.value = holiday;
    holidayForm.value = {
        date: holiday?.date || "",
        name: holiday?.name || holiday?.description || "",
        type: holiday?.type || "national_holiday",
        applies_to: Array.isArray(holiday?.applies_to) ? [...holiday.applies_to] : [],
    };
    holidayAppliesToInput.value = holidayForm.value.applies_to.join(", ");
    isHolidayModalOpen.value = true;
};

const closeHolidayModal = () => {
    isHolidayModalOpen.value = false;
    selectedHoliday.value = null;
    holidayAppliesToInput.value = "";
};

const submitHolidayForm = async () => {
    isSubmittingHoliday.value = true;
    const payload = {
        ...holidayForm.value,
        applies_to: commaSeparatedToArray(holidayAppliesToInput.value),
    };

    try {
        if (selectedHoliday.value?.id) {
            await holidayStore.updateHoliday(selectedHoliday.value.id, payload);
            toast.success("Holiday updated", "Holiday has been updated successfully.");
        } else {
            await holidayStore.createHoliday(payload);
            toast.success("Holiday created", "Holiday has been added successfully.");
        }
        closeHolidayModal();
        await holidayStore.fetchAllPaginated({
            page: holidayStore.meta.current_page || 1,
            row_per_page: holidayStore.meta.per_page || 10,
        });
    } catch (error) {
        toast.error(
            "Failed to save holiday",
            holidayStore.error || error?.response?.data?.message || "Failed to save holiday.",
        );
    } finally {
        isSubmittingHoliday.value = false;
    }
};

onMounted(async () => {
    try {
        await Promise.all([
            holidayStore.fetchAllPaginated(),
            policyStore.fetchPolicies(),
            entitlementStore.fetchEntitlements(),
        ]);
    } catch (error) {
        toast.error(
            "Failed to load attendance settings",
            policyStore.error ||
                entitlementStore.error ||
                holidayStore.error ||
                error?.response?.data?.message ||
                "Failed to load settings data.",
        );
    }
});
</script>

<style scoped>
.font-display {
    font-family: "Outfit", "Inter", sans-serif;
}

.form-input {
    width: 100%;
    border-radius: 8px;
    border: 1px solid #dcdedd;
    padding: 0.5rem 1rem;
    color: #0c1c3c;
    outline: none;
}

.form-input:focus {
    border-color: #0c51d9;
}

.modal-primary-button {
    flex: 1;
    border-radius: 12px;
    background-color: #0c1c3c;
    padding: 0.75rem 1rem;
    color: white;
    font-size: 0.875rem;
    font-weight: 600;
    transition: opacity 0.3s ease;
}

.modal-primary-button:disabled,
.modal-secondary-button:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.modal-secondary-button {
    flex: 1;
    border-radius: 12px;
    border: 1px solid #dcdedd;
    padding: 0.75rem 1rem;
    color: #0c1c3c;
    font-size: 0.875rem;
    font-weight: 600;
    transition: border-color 0.3s ease;
}

.modal-secondary-button:hover:not(:disabled) {
    border-color: #0c51d9;
}

.fade-enter-active,
.fade-leave-active {
    transition:
        opacity 0.4s ease,
        transform 0.4s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    transform: translateY(10px);
}
</style>
