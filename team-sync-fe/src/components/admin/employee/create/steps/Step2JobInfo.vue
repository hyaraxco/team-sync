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
  CreditCard,
  MapPin,
  Calendar,
  X,
  ChevronDown,
  Search,
  SearchX,
  Users,
  ShieldCheck,
} from "lucide-vue-next";
import { ref, computed, onMounted, watch } from "vue";
import { useTeamStore } from "@/stores/team";
import { useOptionStore } from "@/stores/option";
import { usePayrollStore } from "@/stores/payroll";
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

// Payroll Settings — used to lock bank selection to the company's configured bank
const payrollStore = usePayrollStore();
const { settings: payrollSettings } = storeToRefs(payrollStore);
const loadingPayrollSettings = ref(true);

const companyBankName = computed(() => payrollSettings.value?.payroll_bank_name || null);
const companyBankCode = computed(() => payrollSettings.value?.payroll_bank_code || null);

// When company bank is configured, auto-set the employee's bank_name to match
watch(companyBankName, (bankName) => {
  if (bankName) {
    form.value.bank_name = bankName;
  }
}, { immediate: true });

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
  // Fetch payroll settings independently so it always resolves regardless of other fetch failures
  const payrollSettingsFetch = (async () => {
    try {
      if (!payrollSettings.value) {
        await payrollStore.fetchSettings();
      }
    } catch (_) { /* non-critical */ } finally {
      loadingPayrollSettings.value = false;
    }
  })();

  // Run all other fetches in parallel
  await Promise.allSettled([
    teamStore.fetchTeams(),
    optionStore.fetchEmploymentTypes(),
    optionStore.fetchJobStatuses(),
    optionStore.fetchWorkLocations(),
    optionStore.fetchSkillLevels(),
    payrollSettingsFetch,
  ]);

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
            <label class="block text-sm font-medium text-gray-700 mb-1.5"
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

        </div>
      </div>

      <!-- Tax & BPJS Identity Section -->
      <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
        <div class="flex items-center gap-3 mb-6">
          <div
            class="w-12 h-12 bg-emerald-50 rounded-[12px] flex items-center justify-center"
          >
            <ShieldCheck class="w-6 h-6 text-emerald-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-xl font-bold">Tax & BPJS Identity</h3>
            <p class="text-brand-light text-sm font-normal">
              Mandatory tax and social insurance numbers (NPWP & BPJS)
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <!-- NPWP -->
          <div class="mb-4">
            <Input
              id="npwp"
              name="npwp"
              type="text"
              v-model="form.npwp"
              label="NPWP"
              placeholder="e.g. 12.345.678.9-012.000"
              :error="errors?.npwp?.join(', ')"
            >
              <template #icon>
                <Hash class="h-5 w-5 text-gray-400" />
              </template>
            </Input>
            <p class="text-brand-light text-xs mt-1">Nomor Pokok Wajib Pajak (PPh 21)</p>
          </div>

          <!-- PTKP Status -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">PTKP Status</label>
            <select
              id="ptkp_status"
              name="ptkp_status"
              v-model="form.ptkp_status"
              class="w-full px-4 py-3 border border-[#DCDEDD] rounded-[12px] hover:border-[#0C51D9] focus:border-[#0C51D9] transition-all duration-300 bg-white"
            >
              <option value="">Select PTKP status</option>
              <option value="TK/0">TK/0 – Tidak Kawin, Tanpa Tanggungan</option>
              <option value="TK/1">TK/1 – Tidak Kawin, 1 Tanggungan</option>
              <option value="TK/2">TK/2 – Tidak Kawin, 2 Tanggungan</option>
              <option value="TK/3">TK/3 – Tidak Kawin, 3 Tanggungan</option>
              <option value="K/0">K/0 – Kawin, Tanpa Tanggungan</option>
              <option value="K/1">K/1 – Kawin, 1 Tanggungan</option>
              <option value="K/2">K/2 – Kawin, 2 Tanggungan</option>
              <option value="K/3">K/3 – Kawin, 3 Tanggungan</option>
              <option value="K/I/0">K/I/0 – Kawin, Istri Penghasilan, 0 Tanggungan</option>
              <option value="K/I/1">K/I/1 – Kawin, Istri Penghasilan, 1 Tanggungan</option>
              <option value="K/I/2">K/I/2 – Kawin, Istri Penghasilan, 2 Tanggungan</option>
              <option value="K/I/3">K/I/3 – Kawin, Istri Penghasilan, 3 Tanggungan</option>
            </select>
            <p class="text-brand-light text-xs mt-1">Digunakan untuk perhitungan PPh 21</p>
          </div>

          <!-- BPJS Ketenagakerjaan -->
          <div class="mb-4">
            <Input
              id="bpjs_ketenagakerjaan"
              name="bpjs_ketenagakerjaan"
              type="text"
              v-model="form.bpjs_ketenagakerjaan"
              label="BPJS Ketenagakerjaan"
              placeholder="e.g. 1234567890"
              :error="errors?.bpjs_ketenagakerjaan?.join(', ')"
            >
              <template #icon>
                <ShieldCheck class="h-5 w-5 text-gray-400" />
              </template>
            </Input>
            <p class="text-brand-light text-xs mt-1">No. BPJS Jamsostek (JHT, JKK, JKM, JP)</p>
          </div>

          <!-- BPJS Kesehatan -->
          <div class="mb-4">
            <Input
              id="bpjs_kesehatan"
              name="bpjs_kesehatan"
              type="text"
              v-model="form.bpjs_kesehatan"
              label="BPJS Kesehatan"
              placeholder="e.g. 0001234567890"
              :error="errors?.bpjs_kesehatan?.join(', ')"
            >
              <template #icon>
                <ShieldCheck class="h-5 w-5 text-gray-400" />
              </template>
            </Input>
            <p class="text-brand-light text-xs mt-1">No. BPJS Kesehatan / JKN</p>
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
            <!-- Skeleton: while payroll settings are being fetched -->
            <template v-if="loadingPayrollSettings">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Bank Name *
              </label>
              <div class="w-full h-[50px] bg-gray-100 border border-[#DCDEDD] rounded-[16px] animate-pulse"></div>
              <p class="text-brand-light text-xs mt-1">Loading bank configuration...</p>
            </template>

            <!-- Bank locked by company payroll settings -->
            <template v-else-if="companyBankName">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Bank Name *
              </label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                  <Building2 class="h-5 w-5 text-gray-400" />
                </div>
                <div
                  class="w-full pl-12 pr-4 py-3 border border-[#DCDEDD] rounded-[16px] bg-gray-50 text-brand-dark font-semibold cursor-not-allowed flex items-center justify-between"
                  data-testid="bank-name-locked"
                >
                  <span>{{ companyBankName }}</span>
                  <span class="ml-2 text-xs font-semibold text-blue-600 bg-blue-50 border border-blue-200 rounded-full px-2 py-0.5 whitespace-nowrap">
                    Company Default
                  </span>
                </div>
              </div>
              <p class="text-brand-light text-xs mt-1 flex items-center gap-1">
                <span class="inline-block w-1.5 h-1.5 bg-blue-500 rounded-full"></span>
                Dikunci sesuai konfigurasi Bank Payroll perusahaan
                <template v-if="companyBankCode">(Kode BI: {{ companyBankCode }})</template>
              </p>
            </template>

            <!-- Fallback: free bank selection when no company bank is configured -->
            <template v-else>
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
            </template>
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
