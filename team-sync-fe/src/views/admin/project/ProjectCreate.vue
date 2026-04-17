<script setup>
import { Input, Select, TextArea } from "@/components/common/form";
import RightSidebar from "@/components/admin/employee/create/RightSidebar.vue";
import {
  Briefcase,
  Image,
  Upload,
  ImagePlus,
  Eye,
  X,
  Calendar,
  Tag,
  FileText,
  Crown,
  UserCheck,
  ChevronDown,
  Users,
  Code,
  Check,
  Settings,
  DollarSign,
  PlayCircle,
  ClipboardList,
  PauseCircle,
  Plus,
  Minus,
  Flag,
  Zap,
  Search,
  SearchX,
} from "lucide-vue-next";
import { onMounted, ref, watch } from "vue";
import { debounce } from "lodash";
import { useProjectStore } from "@/stores/project";
import { useTeamStore } from "@/stores/team";
import { useEmployeeStore } from "@/stores/employee";
import { storeToRefs } from "pinia";
import router from "@/router";

const projectStore = useProjectStore();
const { loading, error, success } = storeToRefs(projectStore);
const { createProject } = projectStore;

const teamStore = useTeamStore();
const { teams } = storeToRefs(teamStore);
const { fetchTeams } = teamStore;

const employeeStore = useEmployeeStore();
const { employees } = storeToRefs(employeeStore);
const { fetchEmployees } = employeeStore;

const form = ref({
  name: "",
  type: "",
  priority: "",
  status: "",
  start_date: "",
  end_date: "",
  description: "",
  photo: "",
  photo_url: "",
  budget: "",
  project_leader_id: "",
  teams: [],
});

const projectPhotoInput = ref(null);
const leaderModal = ref(false);
const searchLeader = ref("");
const selectedLeader = ref(null);

const formatRupiahInput = (value) => {
  const digits = String(value ?? "").replace(/[^0-9]/g, "");
  if (!digits) return "";
  return new Intl.NumberFormat("id-ID").format(parseInt(digits));
};

const formattingBudget = ref(false);

const handleSubmit = async () => {
  const payload = { ...form.value };
  payload.budget =
    parseInt(String(form.value.budget).replace(/[^0-9]/g, "")) || 0;
  await createProject(payload);

  if (success.value) {
    router.push({ name: "admin.projects" });
  }
};

const handleProjectPhotoSelect = (e) => {
  const file = e.target.files[0];
  if (file) {
    form.value.photo = file;
    form.value.photo_url = URL.createObjectURL(file);
  }
};

const handleSelectLeader = (employee) => {
  selectedLeader.value = employee;
  form.value.project_leader_id = employee.id;
  leaderModal.value = false;
};

const handleRemoveLeader = () => {
  selectedLeader.value = null;
  form.value.project_leader_id = "";
};

onMounted(async () => {
  await fetchTeams();
  await fetchEmployees({
    limit: 6,
  });
  if (form.value.budget) {
    form.value.budget = formatRupiahInput(form.value.budget);
  }
});

watch(
  searchLeader,
  debounce(() => {
    fetchEmployees({
      limit: 6,
      search: searchLeader.value,
    });
  }, 300),
  { deep: true }
);

watch(
  () => form.value.budget,
  (val) => {
    if (formattingBudget.value) return;
    const formatted = formatRupiahInput(val);
    if (formatted !== val) {
      formattingBudget.value = true;
      form.value.budget = formatted;
      formattingBudget.value = false;
    }
  }
);
</script>

<template>
  <div class="flex gap-5 items-start">
    <!-- Form Section -->
    <div class="flex-1">
      <form class="space-y-6" @submit.prevent="handleSubmit">
        <!-- Project Information Section -->
        <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
          <div class="flex items-center gap-3 mb-6">
            <div
              class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center"
            >
              <Briefcase class="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-xl font-bold">
                Project Information
              </h3>
              <p class="text-brand-light text-sm font-normal">
                Basic project details and description
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- Project Photo -->
            <div class="md:col-span-2 mb-4">
              <label class="block text-brand-dark text-base font-semibold mb-1"
                >Project Photo</label
              >
              <div class="flex items-start gap-4">
                <div class="w-64 h-40">
                  <!-- Photo Container with Default Background -->
                  <div class="relative w-64 h-40">
                    <!-- Default background -->
                    <div
                      class="w-64 h-40 absolute bg-gray-50 rounded-[16px] border-2 border-dashed border-[#DCDEDD]"
                    ></div>

                    <div
                      class="w-64 h-40 relative z-10 flex items-center justify-center rounded-[16px] overflow-hidden"
                    >
                      <img
                        :src="form.photo_url"
                        alt="Project Photo"
                        class="w-64 h-40 object-cover rounded-[16px]"
                        v-if="form.photo_url"
                      />
                      <div
                        v-else
                        class="flex flex-col items-center justify-center text-gray-400"
                      >
                        <Image class="w-10 h-10 mb-2" />
                        <span class="text-sm font-medium">Project Photo</span>
                      </div>
                    </div>

                    <!-- Upload overlay (shown on hover) - only when no photo uploaded -->
                    <div
                      class="absolute inset-0 rounded-[16px] flex items-center justify-center transition-all duration-300 cursor-pointer z-20 group"
                    >
                      <div
                        class="absolute inset-0 bg-black opacity-0 group-hover:opacity-30 rounded-[16px] transition-opacity duration-300"
                      ></div>
                      <Upload
                        class="w-10 h-10 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300 relative z-10"
                        @click="projectPhotoInput.click()"
                      />
                    </div>
                  </div>
                </div>
                <div class="flex flex-col gap-2">
                  <input
                    type="file"
                    accept="image/*"
                    class="hidden"
                    ref="projectPhotoInput"
                    @change="handleProjectPhotoSelect"
                  />
                  <button
                    type="button"
                    class="border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-4 py-2 flex items-center gap-2"
                  >
                    <ImagePlus class="w-4 h-4 text-gray-600" />
                    <span class="text-brand-dark text-base font-semibold"
                      >Select Photo</span
                    >
                  </button>
                  <button
                    type="button"
                    class="hidden border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-4 py-2 flex items-center gap-2"
                  >
                    <Eye class="w-4 h-4 text-gray-600" />
                    <span class="text-brand-dark text-base font-semibold"
                      >Preview Photo</span
                    >
                  </button>
                  <button
                    type="button"
                    class="border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-4 py-2 flex items-center gap-2"
                  >
                    <X class="w-4 h-4 text-gray-600" />
                    <span class="text-brand-dark text-base font-semibold"
                      >Remove Photo</span
                    >
                  </button>
                  <p class="text-brand-light text-xs">
                    JPG, PNG up to 2MB (recommended: 640x424px)
                  </p>
                </div>
              </div>
            </div>

            <!-- Project Name -->
            <div class="md:col-span-2 mb-4">
              <Input
                id="name"
                name="name"
                type="text"
                v-model="form.name"
                label="Project Name *"
                placeholder="Enter project name"
                :required="true"
              >
                <template #icon>
                  <Briefcase class="h-5 w-5 text-gray-400" />
                </template>
              </Input>
            </div>

            <!-- Priority -->
            <div class="md:col-span-2 mb-4">
              <label class="block text-brand-dark text-base font-semibold mb-1"
                >Priority *</label
              >
              <div class="grid grid-cols-2 gap-4">
                <!-- Low Priority -->
                <label
                  class="group card flex items-center justify-between w-full min-h-[60px] rounded-[16px] border border-[#DCDEDD] p-4 has-[:checked]:ring-2 has-[:checked]:ring-[#0C51D9] has-[:checked]:ring-offset-2 transition-all duration-300 cursor-pointer"
                >
                  <div class="flex items-center gap-3">
                    <div
                      class="w-10 h-10 bg-gray-50 rounded-[12px] flex items-center justify-center"
                    >
                      <Minus class="w-5 h-5 text-gray-600" />
                    </div>
                    <div class="flex flex-col">
                      <p class="text-brand-dark text-base font-semibold">Low</p>
                    </div>
                  </div>
                  <div
                    class="relative flex items-center justify-center w-fit h-8 shrink-0 rounded-xl border border-[#DCDEDD] py-2 px-3 gap-2"
                  >
                    <input
                      type="radio"
                      name="project_priority"
                      value="low"
                      class="hidden"
                      v-model="form.priority"
                    />
                    <div
                      class="flex size-[18px] rounded-full shadow-sm border border-[#DCDEDD] group-has-[:checked]:border-[5px] group-has-[:checked]:border-[#0C51D9] transition-all duration-300"
                    ></div>
                    <p
                      class="text-xs font-semibold after:content-['Select'] group-has-[:checked]:after:content-['Selected']"
                    ></p>
                  </div>
                </label>

                <!-- Medium Priority -->
                <label
                  class="group card flex items-center justify-between w-full min-h-[60px] rounded-[16px] border border-[#DCDEDD] p-4 has-[:checked]:ring-2 has-[:checked]:ring-[#0C51D9] has-[:checked]:ring-offset-2 transition-all duration-300 cursor-pointer"
                >
                  <div class="flex items-center gap-3">
                    <div
                      class="w-10 h-10 bg-yellow-50 rounded-[12px] flex items-center justify-center"
                    >
                      <Flag class="w-5 h-5 text-yellow-600" />
                    </div>
                    <div class="flex flex-col">
                      <p class="text-brand-dark text-base font-semibold">
                        Medium
                      </p>
                    </div>
                  </div>
                  <div
                    class="relative flex items-center justify-center w-fit h-8 shrink-0 rounded-xl border border-[#DCDEDD] py-2 px-3 gap-2"
                  >
                    <input
                      type="radio"
                      name="project_priority"
                      value="medium"
                      class="hidden"
                      v-model="form.priority"
                    />
                    <div
                      class="flex size-[18px] rounded-full shadow-sm border border-[#DCDEDD] group-has-[:checked]:border-[5px] group-has-[:checked]:border-[#0C51D9] transition-all duration-300"
                    ></div>
                    <p
                      class="text-xs font-semibold after:content-['Select'] group-has-[:checked]:after:content-['Selected']"
                    ></p>
                  </div>
                </label>

                <!-- High Priority -->
                <label
                  class="group card flex items-center justify-between w-full min-h-[60px] rounded-[16px] border border-[#DCDEDD] p-4 has-[:checked]:ring-2 has-[:checked]:ring-[#0C51D9] has-[:checked]:ring-offset-2 transition-all duration-300 cursor-pointer"
                >
                  <div class="flex items-center gap-3">
                    <div
                      class="w-10 h-10 bg-red-50 rounded-[12px] flex items-center justify-center"
                    >
                      <Zap class="w-5 h-5 text-red-600" />
                    </div>
                    <div class="flex flex-col">
                      <p class="text-brand-dark text-base font-semibold">
                        High
                      </p>
                    </div>
                  </div>
                  <div
                    class="relative flex items-center justify-center w-fit h-8 shrink-0 rounded-xl border border-[#DCDEDD] py-2 px-3 gap-2"
                  >
                    <input
                      type="radio"
                      name="project_priority"
                      value="high"
                      class="hidden"
                      v-model="form.priority"
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

            <div
              class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-5 mb-4"
            >
              <!-- Project Type -->
              <div>
                <Select
                  id="type"
                  name="type"
                  v-model="form.type"
                  label="Project Type *"
                  placeholder="Select project type"
                  :options="[
                    { value: 'web_development', label: 'Web Development' },
                    { value: 'mobile_app', label: 'Mobile App' },
                    { value: 'design', label: 'Design' },
                    { value: 'marketing', label: 'Marketing' },
                    { value: 'research', label: 'Research' },
                    { value: 'infrastructure', label: 'Infrastructure' },
                  ]"
                  :required="true"
                >
                  <template #icon>
                    <Tag class="h-5 w-5 text-gray-400" />
                  </template>
                </Select>
              </div>

              <!-- Start Date -->
              <div>
                <Input
                  id="start_date"
                  name="start_date"
                  type="date"
                  v-model="form.start_date"
                  label="Start Date *"
                  :required="true"
                >
                  <template #icon>
                    <Calendar class="h-5 w-5 text-gray-400" />
                  </template>
                </Input>
              </div>

              <!-- End Date -->
              <div>
                <Input
                  id="end_date"
                  name="end_date"
                  type="date"
                  v-model="form.end_date"
                  label="End Date *"
                  :required="true"
                >
                  <template #icon>
                    <Calendar class="h-5 w-5 text-gray-400" />
                  </template>
                </Input>
              </div>
            </div>

            <!-- Project Description -->
            <div class="md:col-span-2">
              <TextArea
                id="description"
                name="description"
                v-model="form.description"
                label="Project Description"
                placeholder="Describe the project's objectives and scope..."
                :rows="4"
              >
                <template #icon>
                  <FileText class="h-5 w-5 text-gray-400" />
                </template>
              </TextArea>
            </div>
          </div>
        </div>

        <!-- Project Leader Section -->
        <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
          <div class="flex items-center gap-3 mb-6">
            <div
              class="w-12 h-12 bg-green-50 rounded-[12px] flex items-center justify-center"
            >
              <Crown class="w-6 h-6 text-green-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-xl font-bold">Project Leader</h3>
              <p class="text-brand-light text-sm font-normal">
                Assign a project leader to manage this project
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- Project Leader Selection -->
            <div class="md:col-span-2">
              <label class="block text-brand-dark text-base font-semibold mb-1"
                >Select Project Leader</label
              >
              <button
                type="button"
                @click="leaderModal = true"
                class="w-full border border-[#DCDEDD] rounded-[16px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-4 py-3 flex items-center gap-3 text-left"
              >
                <UserCheck class="w-5 h-5 text-gray-400" />
                <span class="text-[#0D2929] font-normal flex-1">
                  {{ selectedLeader?.user?.name || "Select project leader" }}
                </span>
                <ChevronDown class="w-4 h-4 text-gray-400" />
              </button>
              <input
                type="hidden"
                id="selectedLeaderValue"
                name="project_leader"
                v-model="form.project_leader_id"
              />

              <!-- Selected Leader Info -->
              <div
                class="mt-3 p-4 bg-gray-50 rounded-[12px] border border-[#DCDEDD]"
                v-if="selectedLeader"
              >
                <div class="flex items-center gap-3">
                  <img
                    :src="selectedLeader?.user?.profile_photo"
                    alt="Leader Photo"
                    class="w-12 h-12 rounded-full object-cover"
                    v-if="selectedLeader?.user?.profile_photo"
                  />
                  <div
                    class="w-12 h-12 rounded-[12px] flex items-center justify-center bg-gray-100"
                    v-else
                  >
                    <UserCheck class="w-5 h-5 text-gray-400" />
                  </div>
                  <div class="flex-1">
                    <h4 class="text-brand-dark text-base font-semibold">
                      {{ selectedLeader?.user?.name }}
                    </h4>
                    <p class="text-brand-light text-sm">
                      {{ selectedLeader?.job_information?.job_title }}
                    </p>
                  </div>
                  <button
                    type="button"
                    @click="handleRemoveLeader"
                    class="text-gray-400 hover:text-gray-600 transition-colors"
                  >
                    <X class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Team Assignment Section -->
        <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
          <div class="flex items-center gap-3 mb-6">
            <div
              class="w-12 h-12 bg-purple-50 rounded-[12px] flex items-center justify-center"
            >
              <Users class="w-6 h-6 text-purple-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-xl font-bold">Team Assignment</h3>
              <p class="text-brand-light text-sm font-normal">
                Select teams that will work on this project
              </p>
            </div>
          </div>

          <div class="space-y-4">
            <!-- Available Teams -->
            <div>
              <label class="block text-brand-dark text-base font-semibold mb-3"
                >Available Teams</label
              >
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Product Development Team -->
                <label
                  class="group team-card flex items-center justify-between w-full min-h-[70px] rounded-[16px] border border-[#DCDEDD] p-4 has-[:checked]:ring-2 has-[:checked]:ring-[#0C51D9] has-[:checked]:ring-offset-2 transition-all duration-300 cursor-pointer hover:border-[#0C51D9] hover:border-2"
                  v-for="(team, index) in teams"
                  :key="index"
                >
                  <div class="flex items-center gap-3">
                    <div
                      class="w-12 h-12 relative flex items-center justify-center rounded-[12px] overflow-hidden"
                    >
                      <!-- Main blue background -->
                      <div
                        class="w-full h-full absolute bg-gradient-to-br from-primary-500 to-primary-600 rounded-[12px]"
                      ></div>
                      <!-- Lucide icon -->
                      <Code class="w-5 h-5 text-white relative z-10" />
                    </div>
                    <div class="flex flex-col">
                      <p class="text-brand-dark text-base font-semibold">
                        {{ team.name }}
                      </p>
                      <p class="text-brand-light text-sm">
                        {{ team.members_count }} members • Active
                      </p>
                    </div>
                  </div>
                  <div
                    class="relative flex items-center justify-center w-fit h-8 shrink-0 rounded-xl border border-[#DCDEDD] py-2 px-3 gap-2"
                  >
                    <input
                      type="checkbox"
                      name="assigned_teams[]"
                      :value="team.id"
                      class="hidden"
                      v-model="form.teams"
                    />
                    <div
                      class="flex size-[18px] rounded border border-[#DCDEDD] group-has-[:checked]:bg-[#0C51D9] group-has-[:checked]:border-[#0C51D9] transition-all duration-300 items-center justify-center"
                    >
                      <Check
                        class="w-3 h-3 text-white opacity-0 group-has-[:checked]:opacity-100 transition-opacity duration-300"
                      />
                    </div>
                    <p
                      class="text-xs font-semibold after:content-['Select'] group-has-[:checked]:after:content-['Selected']"
                    ></p>
                  </div>
                </label>
              </div>
            </div>

            <!-- Selected Teams Summary -->
            <div id="selectedTeamsSummary" class="hidden">
              <div class="border-t border-[#DCDEDD] pt-4">
                <div class="flex items-center justify-between mb-3">
                  <label class="block text-brand-dark text-base font-semibold"
                    >Selected Teams</label
                  >
                  <button
                    type="button"
                    onclick="clearAllTeams()"
                    class="text-brand-light text-sm hover:text-brand-dark transition-colors"
                  >
                    Clear All
                  </button>
                </div>
                <div id="selectedTeamsList" class="flex flex-wrap gap-2"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Project Settings Section -->
        <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
          <div class="flex items-center gap-3 mb-6">
            <div
              class="w-12 h-12 bg-orange-50 rounded-[12px] flex items-center justify-center"
            >
              <Settings class="w-6 h-6 text-orange-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-xl font-bold">
                Project Settings
              </h3>
              <p class="text-brand-light text-sm font-normal">
                Configure project status, budget
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-5">
            <!-- Budget -->
            <div class="mb-4">
              <Input
                id="budget"
                name="budget"
                type="text"
                v-model="form.budget"
                label="Budget"
                placeholder="100.000.000"
              >
                <template #icon> Rp </template>
              </Input>
            </div>

            <!-- Initial Project Status (Full Width) -->
            <div class="md:col-span-2 mb-4">
              <label class="block text-brand-dark text-base font-semibold mb-1"
                >Initial Project Status</label
              >
              <div class="grid grid-cols-2 gap-4">
                <!-- Active Option -->
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
                        Active
                      </p>
                    </div>
                  </div>
                  <div
                    class="relative flex items-center justify-center w-fit h-8 shrink-0 rounded-xl border border-[#DCDEDD] py-2 px-3 gap-2"
                  >
                    <input
                      type="radio"
                      name="project_status"
                      value="active"
                      class="hidden"
                      v-model="form.status"
                    />
                    <div
                      class="flex size-[18px] rounded-full shadow-sm border border-[#DCDEDD] group-has-[:checked]:border-[5px] group-has-[:checked]:border-[#0C51D9] transition-all duration-300"
                    ></div>
                    <p
                      class="text-xs font-semibold after:content-['Select'] group-has-[:checked]:after:content-['Selected']"
                    ></p>
                  </div>
                </label>

                <!-- Planning Option -->
                <label
                  class="group card flex items-center justify-between w-full min-h-[60px] rounded-[16px] border border-[#DCDEDD] p-4 has-[:checked]:ring-2 has-[:checked]:ring-[#0C51D9] has-[:checked]:ring-offset-2 transition-all duration-300 cursor-pointer"
                >
                  <div class="flex items-center gap-3">
                    <div
                      class="w-10 h-10 bg-blue-50 rounded-[12px] flex items-center justify-center"
                    >
                      <ClipboardList class="w-5 h-5 text-blue-600" />
                    </div>
                    <div class="flex flex-col">
                      <p class="text-brand-dark text-base font-semibold">
                        Planning
                      </p>
                    </div>
                  </div>
                  <div
                    class="relative flex items-center justify-center w-fit h-8 shrink-0 rounded-xl border border-[#DCDEDD] py-2 px-3 gap-2"
                  >
                    <input
                      type="radio"
                      name="project_status"
                      value="planning"
                      class="hidden"
                      v-model="form.status"
                    />
                    <div
                      class="flex size-[18px] rounded-full shadow-sm border border-[#DCDEDD] group-has-[:checked]:border-[5px] group-has-[:checked]:border-[#0C51D9] transition-all duration-300"
                    ></div>
                    <p
                      class="text-xs font-semibold after:content-['Select'] group-has-[:checked]:after:content-['Selected']"
                    ></p>
                  </div>
                </label>

                <!-- On Hold Option -->
                <label
                  class="group card flex items-center justify-between w-full min-h-[60px] rounded-[16px] border border-[#DCDEDD] p-4 has-[:checked]:ring-2 has-[:checked]:ring-[#0C51D9] has-[:checked]:ring-offset-2 transition-all duration-300 cursor-pointer"
                >
                  <div class="flex items-center gap-3">
                    <div
                      class="w-10 h-10 bg-yellow-50 rounded-[12px] flex items-center justify-center"
                    >
                      <PauseCircle class="w-5 h-5 text-yellow-600" />
                    </div>
                    <div class="flex flex-col">
                      <p class="text-brand-dark text-base font-semibold">
                        On Hold
                      </p>
                    </div>
                  </div>
                  <div
                    class="relative flex items-center justify-center w-fit h-8 shrink-0 rounded-xl border border-[#DCDEDD] py-2 px-3 gap-2"
                  >
                    <input
                      type="radio"
                      name="project_status"
                      value="on_hold"
                      class="hidden"
                      v-model="form.status"
                    />
                    <div
                      class="flex size-[18px] rounded-full shadow-sm border border-[#DCDEDD] group-has-[:checked]:border-[5px] group-has-[:checked]:border-[#0C51D9] transition-all duration-300"
                    ></div>
                    <p
                      class="text-xs font-semibold after:content-['Select'] group-has-[:checked]:after:content-['Selected']"
                    ></p>
                  </div>
                </label>

                <!-- Draft Option -->
                <label
                  class="group card flex items-center justify-between w-full min-h-[60px] rounded-[16px] border border-[#DCDEDD] p-4 has-[:checked]:ring-2 has-[:checked]:ring-[#0C51D9] has-[:checked]:ring-offset-2 transition-all duration-300 cursor-pointer"
                >
                  <div class="flex items-center gap-3">
                    <div
                      class="w-10 h-10 bg-gray-50 rounded-[12px] flex items-center justify-center"
                    >
                      <FileText class="w-5 h-5 text-gray-600" />
                    </div>
                    <div class="flex flex-col">
                      <p class="text-brand-dark text-base font-semibold">
                        Draft
                      </p>
                    </div>
                  </div>
                  <div
                    class="relative flex items-center justify-center w-fit h-8 shrink-0 rounded-xl border border-[#DCDEDD] py-2 px-3 gap-2"
                  >
                    <input
                      type="radio"
                      name="project_status"
                      value="draft"
                      class="hidden"
                      v-model="form.status"
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

        <!-- Form Actions -->
        <div class="flex items-center gap-4 pb-6">
          <button
            type="submit"
            class="btn-primary rounded-[8px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-6 py-3 flex items-center gap-2"
          >
            <span class="text-brand-white text-base font-semibold"
              >Create Project</span
            >
            <Plus class="w-4 h-4 text-white" />
          </button>
          <button
            type="button"
            onclick="window.history.back()"
            class="border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-6 py-3 flex items-center gap-2"
          >
            <span class="text-brand-dark text-base font-semibold">Cancel</span>
          </button>
        </div>
      </form>
    </div>

    <RightSidebar />
  </div>

  <!-- Project Leader Selection Modal -->
  <div
    class="fixed inset-0 backdrop-blur-sm z-50 flex items-center justify-center"
    v-if="leaderModal"
  >
    <div
      class="bg-white rounded-[20px] border border-[#DCDEDD] w-full max-w-4xl mx-4 max-h-[80vh] overflow-hidden"
    >
      <!-- Modal Header -->
      <div class="p-6 border-b border-[#DCDEDD]">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div
              class="w-12 h-12 bg-green-50 rounded-[12px] flex items-center justify-center"
            >
              <Crown class="w-6 h-6 text-green-600" />
            </div>
            <div>
              <h3 class="text-brand-dark text-xl font-bold">
                Select Project Leader
              </h3>
              <p class="text-brand-light text-sm font-normal">
                Choose an employee to lead this project
              </p>
            </div>
          </div>
          <button
            type="button"
            @click="leaderModal = false"
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
            placeholder="Search employees..."
            v-model="searchLeader"
          />
        </div>
      </div>

      <!-- Employees List -->
      <div class="p-6 overflow-y-auto max-h-96">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            class="leader-card border border-[#DCDEDD] rounded-[16px] hover:border-[#0C51D9] hover:border-2 hover:shadow-lg transition-all duration-300 p-4 cursor-pointer"
            v-for="employee in employees"
            :key="employee.id"
            @click="handleSelectLeader(employee)"
          >
            <div class="flex items-center gap-4">
              <div
                class="w-14 h-14 relative flex items-center justify-center rounded-[12px] overflow-hidden"
              >
                <img
                  :src="employee.user?.profile_photo"
                  alt="Employee Photo"
                  class="w-14 h-14 rounded-[12px] object-cover"
                  v-if="employee.user?.profile_photo"
                />
                <div
                  class="w-14 h-14 rounded-[12px] flex items-center justify-center bg-gray-100"
                  v-else
                >
                  <UserCheck class="w-5 h-5 text-gray-400" />
                </div>
              </div>
              <div class="flex-1">
                <h4 class="text-brand-dark text-base font-bold">
                  {{ employee.user?.name }}
                </h4>
                <p class="text-brand-light text-sm font-normal">
                  {{ employee.job_information?.job_title }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- No Results Message -->
        <EmptyState
          v-if="employees.length === 0"
          icon="SearchX"
          title="No employees found"
          subtitle="Try adjusting your search terms"
        />
      </div>
    </div>
  </div>
</template>
