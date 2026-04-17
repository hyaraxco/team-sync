<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue";
import {
  Clock,
  MapPin,
  Camera,
  Video,
  RotateCcw,
  LogIn,
  LogOut,
  Info,
  Globe,
  Building,
  Loader2,
  AlertCircle,
  UserCheck,
} from "lucide-vue-next";
import { useAttendanceStore } from "@/stores/attendance";
import { storeToRefs } from "pinia";
import { useToast } from "@/composables/useToast";
import StatsCard from "@/components/common/StatsCard.vue";

const toast = useToast();

const attendanceStore = useAttendanceStore();
const { loading, todayAttendance } = storeToRefs(attendanceStore);
const { checkIn, checkOut, fetchTodayAttendance } = attendanceStore;

// State
const currentTime = ref("");
const currentDate = ref("");

// Computed
const isCheckedIn = computed(() => {
  return todayAttendance.value?.check_in && !todayAttendance.value?.check_out;
});

const workingHours = computed(() => {
  if (!todayAttendance.value?.check_in) {
    return "0h 0m";
  }

  const checkInTime = new Date(todayAttendance.value.check_in);
  const endTime = todayAttendance.value.check_out
    ? new Date(todayAttendance.value.check_out)
    : new Date(); // Use current time if still checked in

  const diffMs = endTime - checkInTime;
  const diffMins = Math.floor(diffMs / (1000 * 60));
  const hours = Math.floor(diffMins / 60);
  const minutes = diffMins % 60;

  return `${hours}h ${minutes}m`;
});

const canCheckIn = computed(() => {
  return !isCheckedIn.value;
});

const canCheckOut = computed(() => {
  return isCheckedIn.value;
});

const checkInTime = computed(() => {
  if (!todayAttendance.value?.check_in) return "--:--";
  return new Date(todayAttendance.value.check_in).toLocaleTimeString("en-US", {
    hour12: false,
    hour: "2-digit",
    minute: "2-digit",
  });
});

const checkOutTime = computed(() => {
  if (!todayAttendance.value?.check_out) return "--:--";
  return new Date(todayAttendance.value.check_out).toLocaleTimeString("en-US", {
    hour12: false,
    hour: "2-digit",
    minute: "2-digit",
  });
});

const attendanceStatus = computed(() => {
  if (!todayAttendance.value) {
    return { text: "Not Clocked In", class: "bg-red-100 text-red-700" };
  }
  if (todayAttendance.value.check_in && !todayAttendance.value.check_out) {
    return { text: "Clocked In", class: "bg-green-100 text-green-700" };
  }
  if (todayAttendance.value.check_in && todayAttendance.value.check_out) {
    return { text: "Completed", class: "bg-blue-100 text-blue-700" };
  }
  return { text: "Not Clocked In", class: "bg-red-100 text-red-700" };
});

// Clock functions
const updateClock = () => {
  const now = new Date();

  const timeOptions = {
    hour12: false,
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  };

  const dateOptions = {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  };

  currentTime.value = now.toLocaleTimeString("en-US", timeOptions);
  currentDate.value = now.toLocaleDateString("en-US", dateOptions);
};



// Attendance actions
const handleCheckIn = async () => {
  try {
    await checkIn({
      check_in_lat: null,
      check_in_long: null,
    });

    toast.success("Clocked In", "You have successfully clocked in!");
    await fetchTodayAttendance();
  } catch (error) {
    console.error("Check in failed:", error);
    toast.error("Check-in Failed", "Failed to check in. Please try again.");
  }
};

const handleCheckOut = async () => {
  try {
    await checkOut({
      check_out_lat: null,
      check_out_long: null,
    });

    toast.success("Clocked Out", "You have successfully clocked out!");
    await fetchTodayAttendance();
  } catch (error) {
    console.error("Check out failed:", error);
    toast.error("Check-out Failed", "Failed to check out. Please try again.");
  }
};

// Lifecycle
let clockInterval;

onMounted(async () => {
  await fetchTodayAttendance();
  updateClock();
  clockInterval = setInterval(updateClock, 1000);
});

onUnmounted(() => {
  if (clockInterval) clearInterval(clockInterval);
});
</script>

<template>
  <div class="p-5">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <StatsCard
        title="Status"
        :value="attendanceStatus.text"
        iconName="UserCheckIcon"
        colorScheme="blue"
      />
      <StatsCard
        title="Clock In"
        :value="checkInTime"
        iconName="LogInIcon"
        colorScheme="green"
      />
      <StatsCard
        title="Clock Out"
        :value="checkOutTime"
        iconName="LogOutIcon"
        colorScheme="orange"
      />
      <StatsCard
        title="Working Hours"
        :value="workingHours"
        iconName="ClockIcon"
        colorScheme="purple"
      />
    </div>

    <!-- Clock In/Out Action -->
    <div class="max-w-md mx-auto">
      <div
        class="bg-white border border-[#DCDEDD] rounded-[20px] hover:border-[#0C51D9] hover:border-2 transition-all duration-300 p-8 text-center"
      >
        <div class="mb-6">
          <div
            class="w-20 h-20 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4"
          >
            <LogIn v-if="!isCheckedIn" class="w-10 h-10 text-blue-600" />
            <LogOut v-else class="w-10 h-10 text-blue-600" />
          </div>
          <h3 class="text-brand-dark text-2xl font-bold mb-2">
            {{ isCheckedIn ? "Ready to Clock Out?" : "Ready to Clock In?" }}
          </h3>
          <p class="text-brand-light text-base">
            Mark your attendance
          </p>
        </div>

        <button
          v-if="!isCheckedIn"
          @click="handleCheckIn"
          :disabled="!canCheckIn || loading"
          class="w-full btn-primary rounded-[12px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-8 py-4 flex items-center justify-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <LogIn class="w-6 h-6 text-white" />
          <span class="text-white text-lg font-bold">
            {{ loading ? "Processing..." : "Clock In" }}
          </span>
        </button>

        <button
          v-else
          @click="handleCheckOut"
          :disabled="!canCheckOut || loading"
          class="w-full btn-primary rounded-[12px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-8 py-4 flex items-center justify-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <LogOut class="w-6 h-6 text-white" />
          <span class="text-white text-lg font-bold">
            {{ loading ? "Processing..." : "Clock Out" }}
          </span>
        </button>
      </div>
    </div>
  </div>
</template>
