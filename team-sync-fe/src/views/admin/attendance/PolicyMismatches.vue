<template>
  <div class="policy-mismatches-container min-h-screen bg-neutral-900 text-neutral-100 p-8">
    <div class="max-w-7xl mx-auto space-y-8 relative">
      <div class="absolute top-0 right-0 -mr-32 -mt-32 w-96 h-96 bg-rose-600/20 rounded-full blur-[120px] pointer-events-none"></div>
      
      <header class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-end gap-6 border-b border-white/30 pb-8">
        <div class="space-y-2">
          <h1 class="text-5xl font-extralight tracking-tight font-display bg-clip-text text-transparent bg-gradient-to-r from-white to-neutral-500">
            Policy Mismatches
          </h1>
          <p class="text-neutral-400 font-light tracking-wide max-w-xl">
            Review and resolve discrepancies between employee scheduled work locations and actual attendance data.
          </p>
        </div>
      </header>

      <div class="relative z-10 space-y-6">
        <div v-if="error" class="p-6 rounded-2xl border border-rose-500/20 bg-rose-500/5 text-rose-400 flex items-center gap-3">
          <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
          <p>Failed to load mismatches. The API endpoint might be missing or under construction.</p>
        </div>

        <div v-else-if="loading" class="flex justify-center p-16 border border-white/15 rounded-3xl bg-white/[0.06]">
          <svg class="animate-spin w-8 h-8 text-rose-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        </div>

        <div v-else-if="!mismatches.length" class="text-center p-16 border border-dashed border-white/30 rounded-3xl bg-white/[0.01] text-neutral-500">
          <svg class="w-16 h-16 mx-auto mb-4 opacity-50 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <p class="font-light text-lg">No pending policy mismatches.</p>
          <p class="text-sm mt-1">All attendance logs match their scheduled locations.</p>
        </div>

        <div v-else class="overflow-x-auto rounded-3xl border border-white/30 bg-white/[0.06] backdrop-blur-xl shadow-2xl">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="border-b border-white/30 text-xs uppercase tracking-widest text-neutral-500 bg-white/[0.06]">
                <th class="p-5 font-medium">Employee</th>
                <th class="p-5 font-medium">Date</th>
                <th class="p-5 font-medium">Scheduled</th>
                <th class="p-5 font-medium">Actual</th>
                <th class="p-5 font-medium text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr v-for="item in mismatches" :key="item.id" class="hover:bg-white/10 transition-colors group">
                <td class="p-5">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-rose-500/20 text-rose-400 flex items-center justify-center text-xs font-bold">
                      {{ item.employee_name.charAt(0) }}
                    </div>
                    <span class="font-medium text-neutral-200">{{ item.employee_name }}</span>
                  </div>
                </td>
                <td class="p-5 text-neutral-400 text-sm">{{ item.date }}</td>
                <td class="p-5">
                  <span class="px-2 py-1 text-xs rounded bg-white/10 text-neutral-300 border border-white/30">
                    {{ item.scheduled_location }}
                  </span>
                </td>
                <td class="p-5">
                  <span class="px-2 py-1 text-xs rounded bg-rose-500/10 text-rose-400 border border-rose-500/20">
                    {{ item.actual_location }}
                  </span>
                </td>
                <td class="p-5 text-right space-x-3 opacity-0 group-hover:opacity-100 transition-opacity">
                  <button @click="acknowledge(item.id)" class="text-xs px-3 py-1.5 rounded-lg bg-amber-500/10 text-amber-400 hover:bg-amber-500/20 transition-colors">
                    Acknowledge
                  </button>
                  <button @click="resolve(item.id)" class="text-xs px-3 py-1.5 rounded-lg bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 transition-colors">
                    Resolve
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useAttendanceStore } from '@/stores/attendance';
import { useToast } from '@/composables/useToast';

const attendanceStore = useAttendanceStore();
const toast = useToast();
const mismatches = ref([]);
const loading = ref(true);
const error = ref(false);

onMounted(async () => {
  try {
    const response = await attendanceStore.fetchPolicyMismatches();
    mismatches.value = response?.data || [];
  } catch (err) {
    toast.error(
      'Failed to load policy mismatches',
      attendanceStore.error || err?.response?.data?.message || 'Failed to load mismatches.',
    );
    error.value = true;
  } finally {
    loading.value = false;
  }
});

const acknowledge = async (id) => {
  try {
    await attendanceStore.acknowledgePolicyMismatch(id, 'Acknowledged by HR');
    mismatches.value = mismatches.value.filter(m => m.id !== id);
  } catch (err) {
    alert('Failed to acknowledge mismatch');
  }
};

const resolve = async (id) => {
  try {
    await attendanceStore.resolvePolicyMismatch(id, 'Resolved to match scheduled');
    mismatches.value = mismatches.value.filter(m => m.id !== id);
  } catch (err) {
    alert('Failed to resolve mismatch');
  }
};
</script>

<style scoped>
.font-display {
  font-family: 'Outfit', 'Inter', sans-serif;
}
</style>
