<template>
  <div class="attendance-settings-container min-h-screen bg-neutral-900 text-neutral-100 p-8">
    <div class="max-w-7xl mx-auto space-y-8 relative">
      <!-- Decorative Background Blur -->
      <div class="absolute top-0 right-0 -mr-32 -mt-32 w-96 h-96 bg-purple-600/20 rounded-full blur-[120px] pointer-events-none"></div>
      <div class="absolute bottom-0 left-0 -ml-32 w-96 h-96 bg-blue-600/20 rounded-full blur-[120px] pointer-events-none"></div>

      <!-- Header Section -->
      <header class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-end gap-6 border-b border-white/10 pb-8">
        <div class="space-y-2">
          <h1 class="text-5xl font-extralight tracking-tight font-display bg-clip-text text-transparent bg-gradient-to-r from-white to-neutral-500">
            System Configuration
          </h1>
          <p class="text-neutral-400 font-light tracking-wide max-w-xl">
            Configure global attendance rules, grace periods, and manage holiday schedules across the organization.
          </p>
        </div>
      </header>

      <!-- Tabs Navigation -->
      <nav class="relative z-10 flex gap-8 border-b border-white/5" aria-label="Tabs">
        <button 
          v-for="tab in ['Attendance Policies', 'Holiday Calendars']" 
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

      <!-- Tab Content: Attendance Policies -->
      <transition name="fade" mode="out-in">
        <section v-if="activeTab === 'Attendance Policies'" key="policies" class="relative z-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          <!-- Policy Card -->
          <div class="policy-card group p-6 rounded-2xl bg-white/[0.03] border border-white/[0.05] hover:bg-white/[0.05] transition-all duration-500 hover:-translate-y-1 hover:shadow-2xl hover:shadow-purple-500/10">
            <div class="flex justify-between items-start mb-6">
              <h3 class="text-xl font-medium">Standard Hours</h3>
              <span class="px-3 py-1 text-xs font-semibold uppercase tracking-wider rounded-full bg-white/10 text-neutral-300">Default</span>
            </div>
            
            <div class="space-y-4">
              <div class="flex justify-between items-center border-b border-white/5 pb-2">
                <span class="text-neutral-400 font-light">Late Grace Period</span>
                <span class="font-medium text-purple-400">15 mins</span>
              </div>
              <div class="flex justify-between items-center border-b border-white/5 pb-2">
                <span class="text-neutral-400 font-light">Half Day Max</span>
                <span class="font-medium text-blue-400">4 hours</span>
              </div>
              <div class="flex justify-between items-center border-b border-white/5 pb-2">
                <span class="text-neutral-400 font-light">Required Work Days</span>
                <span class="font-medium text-white">Mon - Fri</span>
              </div>
            </div>

            <button class="w-full mt-8 py-3 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-sm tracking-wide font-medium transition-all focus:outline-none focus:ring-2 focus:ring-purple-500/50">
              Edit Policy
            </button>
          </div>

          <!-- Add New Policy Card -->
          <button class="policy-card flex flex-col items-center justify-center p-6 rounded-2xl border border-dashed border-white/20 text-neutral-400 hover:text-white hover:border-white/50 hover:bg-white/5 transition-all duration-500 min-h-[300px]">
            <svg class="w-12 h-12 mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"></path></svg>
            <span class="font-medium tracking-wide">Create Custom Policy</span>
          </button>
        </section>

        <!-- Tab Content: Holiday Calendars -->
        <section v-else-if="activeTab === 'Holiday Calendars'" key="holidays" class="relative z-10 space-y-6">
          <div class="flex justify-between items-center">
            <h2 class="text-2xl font-light">Upcoming Holidays</h2>
            <button class="px-6 py-2.5 rounded-full bg-white text-black font-medium text-sm hover:scale-105 transition-transform duration-300">
              + Add Holiday
            </button>
          </div>

          <div class="overflow-x-auto rounded-2xl border border-white/10 bg-white/[0.02] backdrop-blur-md">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="border-b border-white/10 text-xs uppercase tracking-widest text-neutral-500">
                  <th class="p-4 font-medium">Holiday Name</th>
                  <th class="p-4 font-medium">Date</th>
                  <th class="p-4 font-medium">Type</th>
                  <th class="p-4 font-medium text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-white/5">
                <tr v-if="holidayStore.error" class="text-center text-rose-500 bg-rose-500/5">
                  <td colspan="4" class="p-8 font-light flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Failed to load holidays. The service might be temporarily unavailable.
                  </td>
                </tr>
                <tr v-else-if="holidayStore.loading" class="text-center text-neutral-400">
                  <td colspan="4" class="p-8 font-light italic flex items-center justify-center gap-2">
                    <svg class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Loading holidays...
                  </td>
                </tr>
                <tr v-else-if="!holidayStore.paginatedHolidays?.length" class="text-center text-neutral-500">
                  <td colspan="4" class="p-8 font-light italic">No holidays configured yet.</td>
                </tr>
                <tr v-else v-for="holiday in holidayStore.paginatedHolidays" :key="holiday.id" class="hover:bg-white/5 transition-colors">
                  <td class="p-4 font-medium text-neutral-200">{{ holiday.name || holiday.description }}</td>
                  <td class="p-4 text-neutral-400">{{ holiday.date }}</td>
                  <td class="p-4"><span class="px-2 py-1 text-xs rounded bg-neutral-800 text-neutral-300 border border-neutral-700">{{ holiday.type }}</span></td>
                  <td class="p-4 text-right">
                    <button class="text-sm text-neutral-500 hover:text-white transition-colors">Edit</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </transition>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useHolidayCalendarStore } from '@/stores/holidayCalendar';

const activeTab = ref('Attendance Policies');
const holidayStore = useHolidayCalendarStore();

onMounted(async () => {
  try {
    await holidayStore.fetchAllPaginated();
  } catch (error) {
    console.error('Failed to load holidays', error);
  }
});
</script>

<style scoped>
.font-display {
  font-family: 'Outfit', 'Inter', sans-serif;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.4s ease, transform 0.4s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(10px);
}
</style>
