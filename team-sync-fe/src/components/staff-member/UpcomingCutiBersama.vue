<script setup>
import { onMounted, ref } from "vue";
import { Calendar } from "lucide-vue-next";
import { axiosInstance } from "@/plugins/axios";
import { formatDateShort } from "@/utils/dateUtils";
import { useToast } from "@/composables/useToast";

const toast = useToast();
const cutiBersama = ref([]);
const loading = ref(false);

const fetchUpcomingCutiBersama = async () => {
    loading.value = true;
    try {
        const response = await axiosInstance.get("/my-upcoming-cuti-bersama");
        if (response.data.success) {
            cutiBersama.value = response.data.data || [];
        }
    } catch (error) {
        toast.error("Gagal memuat cuti bersama", "Silakan coba lagi nanti.");
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchUpcomingCutiBersama();
});
</script>

<template>
    <div v-if="cutiBersama.length > 0" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-center gap-2 mb-3">
            <Calendar class="w-5 h-5 text-blue-600" />
            <h3 class="font-semibold text-blue-900">Upcoming Cuti Bersama</h3>
        </div>
        <p class="text-xs text-blue-700 mb-3">Company-wide collective leave days. No leave request needed.</p>
        <ul class="space-y-2">
            <li v-for="holiday in cutiBersama" :key="holiday.id" class="flex items-start gap-2 text-sm">
                <span class="inline-block w-2 h-2 mt-1.5 rounded-full bg-blue-500"></span>
                <div>
                    <span class="font-medium text-blue-900">{{ formatDateShort(holiday.date) }}</span>
                    <span class="text-blue-700">- {{ holiday.name }}</span>
                </div>
            </li>
        </ul>
    </div>
    <div v-else-if="!loading" class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
        <Calendar class="w-8 h-8 text-gray-400 mx-auto mb-2" />
        <p class="text-sm text-gray-600">Belum ada cuti bersama tahun ini</p>
    </div>
</template>
