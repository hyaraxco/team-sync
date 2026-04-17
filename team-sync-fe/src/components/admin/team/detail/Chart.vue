<script setup>
import { UserPlus, TrendingUp } from "lucide-vue-next";
import { onMounted, ref, watch } from "vue";
import { useTeamStore } from "@/stores/team";
import { storeToRefs } from "pinia";
import VueApexCharts from "vue3-apexcharts";

const props = defineProps({
  team: {
    type: Object,
    required: true,
  },
});

const teamStore = useTeamStore();
const { teamChartData, loadingTeamChart } = storeToRefs(teamStore);
const { fetchTeamChartData } = teamStore;

const memberGrowthOptions = ref({
  chart: {
    type: "bar",
    height: 250,
    toolbar: {
      show: false,
    },
  },
  plotOptions: {
    bar: {
      borderRadius: 8,
      columnWidth: "50%",
    },
  },
  colors: ["#10B981"],
  dataLabels: {
    enabled: false,
  },
  xaxis: {
    categories: [],
  },
  yaxis: {
    labels: {
      formatter: (value) => Math.floor(value),
    },
  },
  grid: {
    borderColor: "#e7e7e7",
    strokeDashArray: 4,
  },
});

const memberGrowthSeries = ref([
  {
    name: "New Members",
    data: [],
  },
]);

const taskGrowthOptions = ref({
  chart: {
    type: "line",
    height: 250,
    toolbar: {
      show: false,
    },
  },
  stroke: {
    curve: "smooth",
    width: 3,
  },
  colors: ["#3B82F6"],
  dataLabels: {
    enabled: false,
  },
  xaxis: {
    categories: [],
  },
  yaxis: {
    labels: {
      formatter: (value) => Math.floor(value),
    },
  },
  grid: {
    borderColor: "#e7e7e7",
    strokeDashArray: 4,
  },
  markers: {
    size: 5,
    colors: ["#3B82F6"],
    strokeWidth: 0,
    hover: {
      size: 7,
    },
  },
});

const taskGrowthSeries = ref([
  {
    name: "Tasks Assigned",
    data: [],
  },
]);

const updateCharts = () => {
  if (teamChartData.value.months.length > 0) {
    // Update member growth chart
    memberGrowthOptions.value.xaxis.categories = teamChartData.value.months;
    memberGrowthSeries.value = [
      {
        name: "New Members",
        data: teamChartData.value.member_growth,
      },
    ];

    // Update task growth chart
    taskGrowthOptions.value.xaxis.categories = teamChartData.value.months;
    taskGrowthSeries.value = [
      {
        name: "Tasks Assigned",
        data: teamChartData.value.task_growth,
      },
    ];
  }
};

const loadChartData = async () => {
  if (props.team?.id) {
    await fetchTeamChartData(props.team.id);
    updateCharts();
  }
};

onMounted(async () => {
  await loadChartData();
});

watch(() => props.team?.id, async (newId) => {
  if (newId) {
    await loadChartData();
  }
}, { immediate: true });

watch(teamChartData, () => {
  updateCharts();
}, { deep: true });
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Team Growth Chart (Employee Joining) -->
    <div class="bg-white border border-[#DCDEDD] rounded-[16px] p-6">
      <div class="flex items-center gap-3 mb-6">
        <div
          class="w-12 h-12 bg-green-50 rounded-[12px] flex items-center justify-center"
        >
          <UserPlus class="w-6 h-6 text-green-600" />
        </div>
        <div>
          <h3 class="text-brand-dark text-lg font-bold">Team Growth</h3>
          <p class="text-brand-light text-sm">
            New members joining over past 4 months
          </p>
        </div>
      </div>

      <!-- Chart Container -->
      <div class="relative">
        <div v-if="loadingTeamChart" class="flex items-center justify-center" style="height: 250px">
          <p class="text-brand-light">Loading...</p>
        </div>
        <VueApexCharts
          v-else
          type="bar"
          height="250"
          :options="memberGrowthOptions"
          :series="memberGrowthSeries"
        />
      </div>
    </div>

    <!-- Tasks Growth Chart -->
    <div class="bg-white border border-[#DCDEDD] rounded-[16px] p-6">
      <div class="flex items-center gap-3 mb-6">
        <div
          class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center"
        >
          <TrendingUp class="w-6 h-6 text-blue-600" />
        </div>
        <div>
          <h3 class="text-brand-dark text-lg font-bold">Tasks Growth</h3>
          <p class="text-brand-light text-sm">
            Tasks assigned to team over past 4 months
          </p>
        </div>
      </div>

      <!-- Chart Container -->
      <div class="relative">
        <div v-if="loadingTeamChart" class="flex items-center justify-center" style="height: 250px">
          <p class="text-brand-light">Loading...</p>
        </div>
        <VueApexCharts
          v-else
          type="line"
          height="250"
          :options="taskGrowthOptions"
          :series="taskGrowthSeries"
        />
      </div>
    </div>
  </div>
</template>
