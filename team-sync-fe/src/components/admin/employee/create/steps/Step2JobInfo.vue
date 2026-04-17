<script setup lang="ts">
import { Input, Select } from "@/components/common/form";
import {
  User,
  Hash,
  Building2,
  Briefcase,
  UserCheck,
  Clock,
  CalendarPlus,
  DollarSign,
  PlayCircle,
  Zap,
  Award,
  Crown,
  CreditCard,
  Wallet,
  MapPin,
  Calendar,
  X,
  ChevronDown,
  Search,
  SearchX,
  Users,
} from "lucide-vue-next";
import { ref, computed, onMounted, watch } from "vue";
import { useTeamStore } from "@/stores/team";
import { useOptionStore } from "@/stores/option";
import { storeToRefs } from "pinia";
import RightSidebarStep2 from "@/components/admin/employee/create/RightSidebarStep2.vue";
import EmptyState from "@/components/common/EmptyState.vue";

interface Props {
  modelValue: any;
  errors?: any;
}

interface TeamOption {
  id: number | string;
  name: string;
  members_count?: number;
}

const props = defineProps<Props>();
const emit = defineEmits(["update:modelValue"]);

const form = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

// Team store
const teamStore = useTeamStore();
const { teams } = storeToRefs(teamStore);

// Option store
const optionStore = useOptionStore();
const { employmentTypes, jobStatuses, workLocations, skillLevels } =
  storeToRefs(optionStore);

const teamOptions = computed<TeamOption[]>(() => {
  return Array.isArray(teams.value) ? (teams.value as TeamOption[]) : [];
});

// Team modal
const teamModal = ref(false);
const searchTeam = ref("");
const selectedTeam = ref<TeamOption | null>(null);

const filteredTeams = computed(() => {
  if (!searchTeam.value) return teamOptions.value;
  return teamOptions.value.filter((team) =>
    team.name.toLowerCase().includes(searchTeam.value.toLowerCase()),
  );
});

const normalizeId = (value: any) => String(value ?? "");

const syncSelectedTeam = () => {
  const currentTeamId = normalizeId(form.value.team_id);

  if (!currentTeamId) {
    selectedTeam.value = null;
    return;
  }

  selectedTeam.value =
    teamOptions.value.find((team) => normalizeId(team.id) === currentTeamId) ||
    null;
};

const handleSelectTeam = (team: TeamOption) => {
  selectedTeam.value = team;
  form.value.team_id = normalizeId(team.id);
  teamModal.value = false;
};

const handleRemoveTeam = () => {
  selectedTeam.value = null;
  form.value.team_id = "";
};

onMounted(async () => {
  await teamStore.fetchTeams();
  await optionStore.fetchEmploymentTypes();
  await optionStore.fetchJobStatuses();
  await optionStore.fetchWorkLocations();
  await optionStore.fetchSkillLevels();
  if (form.value.monthly_salary) {
    form.value.monthly_salary = formatRupiah(form.value.monthly_salary);
  }
});

const parseSalaryNumber = (value: any): number | null => {
  if (value === null || value === undefined) return null;

  const raw = String(value).trim();
  if (!raw) return null;

  if (/^\d+\.\d{1,2}$/.test(raw)) {
    const parsed = Number(raw);
    return Number.isFinite(parsed) ? Math.trunc(parsed) : null;
  }

  if (/^\d{1,3}(\.\d{3})+(,\d+)?$/.test(raw)) {
    const parsed = Number(raw.replace(/\./g, "").replace(",", "."));
    return Number.isFinite(parsed) ? Math.trunc(parsed) : null;
  }

  const digits = raw.replace(/[^0-9]/g, "");
  if (!digits) return null;

  const parsed = parseInt(digits, 10);
  return Number.isFinite(parsed) ? parsed : null;
};

const formatRupiah = (value: any) => {
  const parsed = parseSalaryNumber(value);
  if (parsed === null) return "";

  return new Intl.NumberFormat("id-ID").format(parsed);
};

const formattingSalary = ref(false);
watch(
  () => form.value.monthly_salary,
  (val) => {
    if (formattingSalary.value) return;
    const formatted = formatRupiah(val);
    if (formatted !== val) {
      formattingSalary.value = true;
      form.value.monthly_salary = formatted;
      formattingSalary.value = false;
    }
  },
);

watch(
  [() => form.value.team_id, teamOptions],
  () => {
    syncSelectedTeam();
  },
  { immediate: true },
);
</script>

<template>
  <div class="flex flex-col 2xl:flex-row gap-5 items-stretch 2xl:items-start pr-0">
    <div class="flex-1 w-full space-y-6">
      <!-- Job Information Section -->
      <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
        <div class="flex items-center gap-3 mb-6">
          <div
            class="w-12 h-12 bg-green-50 rounded-[12px] flex items-center justify-center"
          >
            <Briefcase class="w-6 h-6 text-green-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-xl font-bold">Job Information</h3>
            <p class="text-brand-light text-sm font-normal">
              Role, department, and employment details
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <div class="mb-4">
            <Input
              id="job_title"
              name="job_title"
              type="text"
              v-model="form.job_title"
              label="Job Title *"
              placeholder="e.g. Senior Developer"
              :error="errors?.job_title?.join(', ')"
              required
            >
              <template #icon>
                <Briefcase class="h-5 w-5 text-gray-400" />
              </template>
            </Input>
          </div>

          <!-- Role -->
          <div class="mb-4">
            <Select
              id="role"
              name="role"
              v-model="form.role"
              label="Role *"
              placeholder="Select role"
              :options="[
                { value: 'manager', label: 'Manager' },
                { value: 'hr', label: 'HR' },
                { value: 'finance', label: 'Finance' },
                { value: 'employee', label: 'Employee' },
              ]"
              :error="
                errors?.roles?.join(', ') || errors?.['roles.0']?.join(', ')
              "
              required
            >
              <template #icon>
                <Crown class="h-5 w-5 text-gray-400" />
              </template>
            </Select>
          </div>

          <!-- Team -->
          <div class="mb-4">
            <label class="block text-brand-dark text-base font-semibold mb-1"
              >Team</label
            >
            <div class="relative">
              <div
                class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"
              >
                <Building2 class="h-5 w-5 text-gray-400" />
              </div>
              <div
                @click="teamModal = true"
                class="w-full pl-12 pr-10 py-3 border border-[#DCDEDD] rounded-[16px] hover:border-[#0C51D9] hover:border-2 focus:border-[#0C51D9] focus:border-2 focus:bg-white transition-all duration-300 cursor-pointer bg-white"
              >
                <span
                  :class="[selectedTeam ? 'text-brand-dark' : 'text-gray-500']"
                >
                  {{ selectedTeam ? selectedTeam.name : "Select team" }}
                </span>
              </div>
              <div
                class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none"
              >
                <ChevronDown class="h-4 w-4 text-gray-400" />
              </div>
            </div>

            <!-- Selected Team Display -->
            <div
              v-if="selectedTeam"
              class="mt-3 p-3 bg-gray-50 rounded-[12px] border border-gray-200"
            >
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 relative flex items-center justify-center rounded-[8px] overflow-hidden"
                >
                  <div
                    class="w-full h-full absolute bg-gradient-to-br from-primary-500 to-primary-600 rounded-[8px]"
                  ></div>
                  <Building2 class="w-5 h-5 text-white relative z-10" />
                </div>
                <div class="flex-1">
                  <p class="text-brand-dark text-base font-semibold">
                    {{ selectedTeam.name }}
                  </p>
                  <p class="text-brand-light text-xs font-normal">
                    {{ selectedTeam.members_count || 0 }} members
                  </p>
                </div>
                <button
                  type="button"
                  @click="handleRemoveTeam"
                  class="text-gray-400 hover:text-gray-600 transition-colors"
                >
                  <X class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <div class="mb-4">
            <Input
              id="years_experience"
              name="years_experience"
              type="number"
              v-model="form.years_experience"
              label="Years of Experience *"
              placeholder="e.g. 3"
              :error="errors?.years_experience?.join(', ')"
              required
            >
              <template #icon>
                <Calendar class="h-5 w-5 text-gray-400" />
              </template>
            </Input>
          </div>

          <div class="mb-4">
            <Select
              id="status"
              name="status"
              v-model="form.status"
              label="Status *"
              placeholder="Select status"
              :options="jobStatuses"
              :error="errors?.status?.join(', ')"
              required
            >
              <template #icon>
                <UserCheck class="h-5 w-5 text-gray-400" />
              </template>
            </Select>
          </div>

          <div class="mb-4">
            <Select
              id="employment_type"
              name="employment_type"
              v-model="form.employment_type"
              label="Employment Type *"
              placeholder="Select employment type"
              :options="employmentTypes"
              :error="errors?.employment_type?.join(', ')"
              required
            >
              <template #icon>
                <Clock class="h-5 w-5 text-gray-400" />
              </template>
            </Select>
          </div>

          <div class="mb-4">
            <Select
              id="work_location"
              name="work_location"
              v-model="form.work_location"
              label="Work Location *"
              placeholder="Select work location"
              :options="workLocations"
              :error="errors?.work_location?.join(', ')"
              required
            >
              <template #icon>
                <MapPin class="h-5 w-5 text-gray-400" />
              </template>
            </Select>
          </div>

          <div class="mb-4">
            <Input
              id="start_date"
              name="start_date"
              type="date"
              v-model="form.start_date"
              label="Start Date *"
              :error="errors?.start_date?.join(', ')"
              required
            >
              <template #icon>
                <CalendarPlus class="h-5 w-5 text-gray-400" />
              </template>
            </Input>
          </div>

          <div class="mb-4">
            <Input
              id="monthly_salary"
              name="monthly_salary"
              type="text"
              v-model="form.monthly_salary"
              label="Monthly Salary *"
              placeholder="Rp 50.000"
              :error="errors?.monthly_salary?.join(', ')"
              required
            >
              <template #icon> Rp </template>
            </Input>
          </div>

          <!-- Skill Level (Full Width) -->
          <div class="lg:col-span-2 mb-4">
            <label class="block text-brand-dark text-base font-semibold mb-1"
              >Skill Level *</label
            >
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Beginner Option -->
              <label
                class="group card flex items-center justify-between w-full min-h-[60px] rounded-[16px] border border-[#DCDEDD] p-4 has-[:checked]:ring-2 has-[:checked]:ring-[#0C51D9] has-[:checked]:ring-offset-2 transition-all duration-300 cursor-pointer"
              >
                <div class="flex items-center gap-3">
                  <div
                    class="w-10 h-10 bg-green-50 rounded-[12px] flex items-center justify-center"
                  >
                    <PlayCircle class="w-5 h-5 text-green-600" />
                  </div>
                  <div class="flex flex-col">
                    <p class="text-brand-dark text-base font-semibold">
                      Beginner
                    </p>
                  </div>
                </div>
                <div
                  class="relative flex items-center justify-center w-fit h-8 shrink-0 rounded-xl border border-[#DCDEDD] py-2 px-3 gap-2"
                >
                  <input
                    type="radio"
                    name="skill_level"
                    value="beginner"
                    class="hidden"
                    v-model="form.skill_level"
                  />
                  <div
                    class="flex size-[18px] rounded-full shadow-sm border border-[#DCDEDD] group-has-[:checked]:border-[5px] group-has-[:checked]:border-[#0C51D9] transition-all duration-300"
                  ></div>
                  <p
                    class="text-xs font-semibold after:content-['Select'] group-has-[:checked]:after:content-['Selected']"
                  ></p>
                </div>
              </label>

              <!-- Intermediate Option -->
              <label
                class="group card flex items-center justify-between w-full min-h-[60px] rounded-[16px] border border-[#DCDEDD] p-4 has-[:checked]:ring-2 has-[:checked]:ring-[#0C51D9] has-[:checked]:ring-offset-2 transition-all duration-300 cursor-pointer"
              >
                <div class="flex items-center gap-3">
                  <div
                    class="w-10 h-10 bg-yellow-50 rounded-[12px] flex items-center justify-center"
                  >
                    <Zap class="w-5 h-5 text-yellow-600" />
                  </div>
                  <div class="flex flex-col">
                    <p class="text-brand-dark text-base font-semibold">
                      Intermediate
                    </p>
                  </div>
                </div>
                <div
                  class="relative flex items-center justify-center w-fit h-8 shrink-0 rounded-xl border border-[#DCDEDD] py-2 px-3 gap-2"
                >
                  <input
                    type="radio"
                    name="skill_level"
                    value="intermediate"
                    class="hidden"
                    v-model="form.skill_level"
                  />
                  <div
                    class="flex size-[18px] rounded-full shadow-sm border border-[#DCDEDD] group-has-[:checked]:border-[5px] group-has-[:checked]:border-[#0C51D9] transition-all duration-300"
                  ></div>
                  <p
                    class="text-xs font-semibold after:content-['Select'] group-has-[:checked]:after:content-['Selected']"
                  ></p>
                </div>
              </label>

              <!-- Advanced Option -->
              <label
                class="group card flex items-center justify-between w-full min-h-[60px] rounded-[16px] border border-[#DCDEDD] p-4 has-[:checked]:ring-2 has-[:checked]:ring-[#0C51D9] has-[:checked]:ring-offset-2 transition-all duration-300 cursor-pointer"
              >
                <div class="flex items-center gap-3">
                  <div
                    class="w-10 h-10 bg-purple-50 rounded-[12px] flex items-center justify-center"
                  >
                    <Award class="w-5 h-5 text-purple-600" />
                  </div>
                  <div class="flex flex-col">
                    <p class="text-brand-dark text-base font-semibold">
                      Advanced
                    </p>
                  </div>
                </div>
                <div
                  class="relative flex items-center justify-center w-fit h-8 shrink-0 rounded-xl border border-[#DCDEDD] py-2 px-3 gap-2"
                >
                  <input
                    type="radio"
                    name="skill_level"
                    value="advanced"
                    class="hidden"
                    v-model="form.skill_level"
                  />
                  <div
                    class="flex size-[18px] rounded-full shadow-sm border border-[#DCDEDD] group-has-[:checked]:border-[5px] group-has-[:checked]:border-[#0C51D9] transition-all duration-300"
                  ></div>
                  <p
                    class="text-xs font-semibold after:content-['Select'] group-has-[:checked]:after:content-['Selected']"
                  ></p>
                </div>
              </label>

              <!-- Expert Option -->
              <label
                class="group card flex items-center justify-between w-full min-h-[60px] rounded-[16px] border border-[#DCDEDD] p-4 has-[:checked]:ring-2 has-[:checked]:ring-[#0C51D9] has-[:checked]:ring-offset-2 transition-all duration-300 cursor-pointer"
              >
                <div class="flex items-center gap-3">
                  <div
                    class="w-10 h-10 bg-red-50 rounded-[12px] flex items-center justify-center"
                  >
                    <Crown class="w-5 h-5 text-red-600" />
                  </div>
                  <div class="flex flex-col">
                    <p class="text-brand-dark text-base font-semibold">
                      Expert
                    </p>
                  </div>
                </div>
                <div
                  class="relative flex items-center justify-center w-fit h-8 shrink-0 rounded-xl border border-[#DCDEDD] py-2 px-3 gap-2"
                >
                  <input
                    type="radio"
                    name="skill_level"
                    value="expert"
                    class="hidden"
                    v-model="form.skill_level"
                  />
                  <div
                    class="flex size-[18px] rounded-full shadow-sm border border-[#DCDEDD] group-has-[:checked]:border-[5px] group-has-[:checked]:border-[#0C51D9] transition-all duration-300"
                  ></div>
                  <p
                    class="text-xs font-semibold after:content-['Select'] group-has-[:checked]:after:content-['Selected']"
                  ></p>
                </div>
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- Bank Information Section -->
      <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
        <div class="flex items-center gap-3 mb-6">
          <div
            class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center"
          >
            <CreditCard class="w-6 h-6 text-blue-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-xl font-bold">Bank Information</h3>
            <p class="text-brand-light text-sm font-normal">
              Employee banking details for payroll processing
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <div class="mb-4">
            <Select
              id="bank_name"
              name="bank_name"
              v-model="form.bank_name"
              label="Bank Name *"
              placeholder="Select bank"
              :options="[
                { value: 'bca', label: 'Bank Central Asia (BCA)' },
                { value: 'mandiri', label: 'Bank Mandiri' },
                { value: 'bni', label: 'Bank Negara Indonesia (BNI)' },
                { value: 'bri', label: 'Bank Rakyat Indonesia (BRI)' },
                { value: 'cimb', label: 'CIMB Niaga' },
                { value: 'danamon', label: 'Bank Danamon' },
                { value: 'permata', label: 'Bank Permata' },
                { value: 'maybank', label: 'Maybank Indonesia' },
                { value: 'ocbc', label: 'OCBC NISP' },
                { value: 'panin', label: 'Panin Bank' },
              ]"
              :error="errors?.bank_name?.join(', ')"
              required
            >
              <template #icon>
                <Building2 class="h-5 w-5 text-gray-400" />
              </template>
            </Select>
          </div>

          <div class="mb-4">
            <Input
              id="account_number"
              name="account_number"
              type="text"
              v-model="form.account_number"
              label="Account Number *"
              placeholder="e.g. 1234567890"
              :error="errors?.account_number?.join(', ')"
              required
            >
              <template #icon>
                <Hash class="h-5 w-5 text-gray-400" />
              </template>
            </Input>
            <p class="text-brand-light text-xs font-normal mt-1">
              Enter only numbers (8-20 digits)
            </p>
          </div>

          <div class="mb-4 lg:col-span-2">
            <Input
              id="account_holder_name"
              name="account_holder_name"
              type="text"
              v-model="form.account_holder_name"
              label="Account Holder Name *"
              placeholder="e.g. John Doe Smith"
              :error="errors?.account_holder_name?.join(', ')"
              required
            >
              <template #icon>
                <User class="h-5 w-5 text-gray-400" />
              </template>
            </Input>
            <p class="text-brand-light text-xs font-normal mt-1">
              Name should match the bank account holder exactly
            </p>
          </div>

          <div class="mb-4">
            <Input
              id="bank_branch"
              name="bank_branch"
              type="text"
              v-model="form.bank_branch"
              label="Bank Branch (Optional)"
              placeholder="e.g. Jakarta Pusat"
              :error="errors?.bank_branch?.join(', ')"
            >
              <template #icon>
                <MapPin class="h-5 w-5 text-gray-400" />
              </template>
            </Input>
          </div>

          <div class="mb-4">
            <Select
              id="account_type"
              name="account_type"
              v-model="form.account_type"
              label="Account Type *"
              placeholder="Select account type"
              :options="[
                { value: 'savings', label: 'Savings Account' },
                { value: 'checking', label: 'Checking Account' },
                { value: 'current', label: 'Current Account' },
              ]"
              :error="errors?.account_type?.join(', ')"
              required
            >
              <template #icon>
                <Wallet class="h-5 w-5 text-gray-400" />
              </template>
            </Select>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Sidebar Tips -->
    <RightSidebarStep2 />
  </div>

  <!-- Team Selection Modal -->
  <div
    class="fixed inset-0 backdrop-blur-sm z-50 flex items-center justify-center"
    v-if="teamModal"
    @click="teamModal = false"
  >
    <div
      @click.stop
      class="bg-white rounded-[20px] border border-[#DCDEDD] w-full max-w-4xl mx-4 max-h-[80vh] overflow-hidden"
    >
      <!-- Modal Header -->
      <div class="p-6 border-b border-[#DCDEDD]">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div
              class="w-12 h-12 bg-green-50 rounded-[12px] flex items-center justify-center"
            >
              <Users class="w-6 h-6 text-green-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-xl font-bold">Select Team</h3>
              <p class="text-brand-light text-sm font-normal">
                Choose the team for this employee
              </p>
            </div>
          </div>
          <button
            type="button"
            @click="teamModal = false"
            class="w-10 h-10 rounded-full border border-[#DCDEDD] flex items-center justify-center hover:border-[#0C51D9] hover:border-2 transition-all duration-200"
          >
            <X class="w-5 h-5 text-gray-600" />
          </button>
        </div>
      </div>

      <!-- Search Bar -->
      <div class="p-6 border-b border-[#DCDEDD]">
        <div class="relative">
          <div
            class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"
          >
            <Search class="h-5 w-5 text-gray-400" />
          </div>
          <input
            type="text"
            class="w-full pl-12 pr-4 py-3 border border-[#DCDEDD] rounded-[16px] hover:border-[#0C51D9] hover:border-2 focus:border-[#0C51D9] focus:border-2 focus:bg-white transition-all duration-300 font-semibold"
            placeholder="Search teams..."
            v-model="searchTeam"
          />
        </div>
      </div>

      <!-- Teams List -->
      <div class="p-6 overflow-y-auto max-h-96">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            class="border border-[#DCDEDD] rounded-[16px] hover:border-[#0C51D9] hover:border-2 hover:shadow-lg transition-all duration-300 p-4 cursor-pointer"
            v-for="team in filteredTeams"
            :key="team.id"
            @click="handleSelectTeam(team)"
          >
            <div class="flex items-center gap-4">
              <div
                class="w-14 h-14 relative flex items-center justify-center rounded-[12px] overflow-hidden"
              >
                <div
                  class="w-full h-full absolute bg-gradient-to-br from-primary-500 to-primary-600 rounded-[12px]"
                ></div>
                <Building2 class="w-6 h-6 text-white relative z-10" />
              </div>
              <div class="flex-1">
                <h4 class="text-brand-dark text-base font-bold">
                  {{ team.name }}
                </h4>
                <p class="text-brand-light text-sm font-normal">
                  {{ team.members_count || 0 }} members
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- No Results Message -->
        <EmptyState
          v-if="filteredTeams.length === 0"
          icon="SearchX"
          title="No teams found"
          subtitle="Try adjusting your search terms"
        />
      </div>
    </div>
  </div>
</template>
