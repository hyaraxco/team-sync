<script setup>
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import {
    AlertTriangle,
    CheckCircle2,
    Clock3,
    LoaderCircle,
    MessageCircleHeart,
    RefreshCw,
    ShieldAlert,
} from "lucide-vue-next";
import { useDashboardStore } from "@/stores/dashboard";
import { useToast } from "@/composables/useToast";
import { DEFAULT_AVATAR } from "@/helpers/format";

const dashboardStore = useDashboardStore();
const { teamPulse, teamPulseLoading, teamPulseNudgingIds } = storeToRefs(dashboardStore);
const { success, error } = useToast();

const selectedMember = ref(null);
const isNudgeModalOpen = ref(false);
const draftMessage = ref("");

onMounted(() => {
    dashboardStore.fetchTeamPulse().catch(() => {});
});

const summary = computed(
    () =>
        teamPulse.value?.summary || {
            red: 0,
            yellow: 0,
            green: 0,
            total: 0,
        },
);

const staffMembers = computed(() => teamPulse.value?.staff_members || []);

const riskConfig = {
    red: {
        label: "Butuh Aksi",
        container: "border-red-200 bg-red-50/70",
        badge: "bg-red-100 text-red-700",
        icon: ShieldAlert,
    },
    yellow: {
        label: "Perlu Perhatian",
        container: "border-amber-200 bg-amber-50/70",
        badge: "bg-amber-100 text-amber-700",
        icon: AlertTriangle,
    },
    green: {
        label: "Aman",
        container: "border-green-200 bg-green-50/70",
        badge: "bg-green-100 text-green-700",
        icon: CheckCircle2,
    },
};

const openNudgeModal = (member) => {
    selectedMember.value = member;
    draftMessage.value = `Hi ${member.name}, kulihat task hari ini agak melambat. Ada blocker yang bisa kubantu?`;
    isNudgeModalOpen.value = true;
};

const closeNudgeModal = () => {
    isNudgeModalOpen.value = false;
    selectedMember.value = null;
    draftMessage.value = "";
};

const submitNudge = async () => {
    if (!selectedMember.value) {
        return;
    }

    try {
        await dashboardStore.sendTeamPulseNudge(selectedMember.value.id, draftMessage.value);
        success("Pesan terkirim", `Sapaan untuk ${selectedMember.value.name} sudah dikirim.`);
        closeNudgeModal();
    } catch {
        error("Gagal mengirim", dashboardStore.error || "Tidak dapat mengirim sapaan sekarang.");
    }
};

const isNudging = (memberId) => teamPulseNudgingIds.value.includes(String(memberId));

const formatTime = (value) => {
    if (!value) {
        return "-";
    }

    return new Date(value).toLocaleTimeString("id-ID", {
        hour: "2-digit",
        minute: "2-digit",
    });
};
</script>

<template>
    <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-4 sm:p-5 space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 rounded-[12px] bg-gradient-to-br from-[#0C51D9] to-[#3B82F6] flex items-center justify-center"
                >
                    <MessageCircleHeart class="w-5 h-5 text-white" />
                </div>
                <div>
                    <h3 class="text-brand-dark text-lg font-bold">Team Pulse</h3>
                    <p class="text-brand-light text-xs">Fokus ke anggota tim yang paling butuh perhatian hari ini.</p>
                </div>
            </div>

            <div class="flex items-center gap-3 self-start">
                <div class="text-right text-xs text-brand-light">
                    <p>Updated</p>
                    <p class="font-semibold text-brand-dark">{{ formatTime(teamPulse?.updated_at) }}</p>
                </div>
                <button
                    type="button"
                    class="w-10 h-10 rounded-full border border-[#DCDEDD] flex items-center justify-center hover:border-[#0C51D9] hover:border-2 transition-all duration-200"
                    @click="dashboardStore.fetchTeamPulse()"
                >
                    <RefreshCw class="w-4 h-4 text-brand-dark" :class="{ 'animate-spin': teamPulseLoading }" />
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div class="rounded-[16px] border border-red-200 bg-red-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Merah</p>
                <p class="text-2xl font-extrabold text-brand-dark mt-2">{{ summary.red }}</p>
                <p class="text-xs text-red-700 mt-1">Perlu tindak lanjut cepat</p>
            </div>
            <div class="rounded-[16px] border border-amber-200 bg-amber-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Kuning</p>
                <p class="text-2xl font-extrabold text-brand-dark mt-2">{{ summary.yellow }}</p>
                <p class="text-xs text-amber-700 mt-1">Perlu sapaan ringan</p>
            </div>
            <div class="rounded-[16px] border border-green-200 bg-green-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-green-700">Hijau</p>
                <p class="text-2xl font-extrabold text-brand-dark mt-2">{{ summary.green }}</p>
                <p class="text-xs text-green-700 mt-1">Berjalan stabil</p>
            </div>
            <div class="rounded-[16px] border border-[#DCDEDD] bg-gray-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-light">Total</p>
                <p class="text-2xl font-extrabold text-brand-dark mt-2">{{ summary.total }}</p>
                <p class="text-xs text-brand-light mt-1">Anggota tim termonitor</p>
            </div>
        </div>

        <div v-if="teamPulseLoading" class="space-y-3">
            <div v-for="i in 3" :key="i" class="h-28 rounded-[20px] bg-gray-100 animate-pulse"></div>
        </div>

        <div
            v-else-if="staffMembers.length === 0"
            class="rounded-[16px] border border-dashed border-[#DCDEDD] p-8 text-center"
        >
            <p class="text-brand-dark font-semibold">Belum ada anggota tim yang bisa dipantau</p>
            <p class="text-brand-light text-sm mt-1">Pastikan manager sudah memiliki tim aktif.</p>
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="member in staffMembers"
                :key="member.id"
                :class="riskConfig[member.risk.level]?.container || riskConfig.green.container"
                class="border rounded-[20px] p-4"
            >
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex items-start gap-3 min-w-0">
                        <img loading="lazy"
                            :src="member.profile_photo || DEFAULT_AVATAR"
                            :alt="member.name"
                            class="w-12 h-12 rounded-full object-cover flex-shrink-0"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-brand-dark font-bold text-base">{{ member.name }}</p>
                                <span
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold"
                                    :class="riskConfig[member.risk.level]?.badge || riskConfig.green.badge"
                                >
                                    <component
                                        :is="riskConfig[member.risk.level]?.icon || riskConfig.green.icon"
                                        class="w-3.5 h-3.5"
                                    />
                                    {{ riskConfig[member.risk.level]?.label || riskConfig.green.label }}
                                </span>
                            </div>
                            <p class="text-sm text-brand-light mt-1">
                                {{ member.job_title || "Tanpa jabatan" }}
                                <span v-if="member.team_name">• {{ member.team_name }}</span>
                            </p>
                            <p class="text-sm text-brand-dark mt-2">{{ member.risk.reason }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 xl:min-w-[380px]">
                        <div class="rounded-[16px] bg-white/80 border border-white p-3">
                            <div class="flex items-center justify-between text-xs text-brand-light mb-2">
                                <span>Attendance</span>
                                <span>{{ member.attendance.label }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                                <div
                                    class="h-full rounded-full bg-[#0C51D9]"
                                    :style="{ width: member.attendance.score + '%' }"
                                ></div>
                            </div>
                        </div>

                        <div class="rounded-[16px] bg-white/80 border border-white p-3">
                            <div class="flex items-center justify-between text-xs text-brand-light mb-2">
                                <span>Task Velocity</span>
                                <span>{{ member.task_velocity.percent }}%</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                                <div
                                    class="h-full rounded-full bg-emerald-500"
                                    :style="{ width: member.task_velocity.percent + '%' }"
                                ></div>
                            </div>
                            <p class="text-xs text-brand-light mt-2">
                                {{ member.task_velocity.done_today }} selesai hari ini •
                                {{ member.task_velocity.overdue }} overdue
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div v-if="member.nudge?.last_sent_at" class="text-xs text-brand-light flex items-center gap-2">
                        <Clock3 class="w-3.5 h-3.5" />
                        <span>Telah disapa pada {{ formatTime(member.nudge.last_sent_at) }}</span>
                    </div>
                    <div v-else class="text-xs text-brand-light">Belum ada sapaan hari ini</div>

                    <div class="flex items-center gap-2 self-start sm:self-auto">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-[16px] bg-[#0C51D9] px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60"
                            :disabled="isNudging(member.id)"
                            @click="openNudgeModal(member)"
                        >
                            <LoaderCircle v-if="isNudging(member.id)" class="w-4 h-4 animate-spin" />
                            <MessageCircleHeart v-else class="w-4 h-4" />
                            Tanya Kabar
                        </button>

                        <RouterLink
                            :to="member.detail_url"
                            class="inline-flex items-center rounded-[16px] border border-[#DCDEDD] px-4 py-2.5 text-sm font-semibold text-brand-dark hover:border-[#0C51D9] hover:text-[#0C51D9]"
                        >
                            View Details
                        </RouterLink>
                    </div>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="isNudgeModalOpen"
                class="fixed inset-0 z-[9998] bg-black/40 flex items-center justify-center p-4"
            >
                <div class="w-full max-w-xl rounded-[24px] bg-white border border-[#DCDEDD] p-6 shadow-xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h4 class="text-xl font-bold text-brand-dark">Kirim Tanya Kabar</h4>
                            <p class="text-sm text-brand-light mt-1">
                                Kirim sapaan untuk menanyakan apakah ada kendala yang bisa dibantu.
                            </p>
                        </div>
                        <button type="button" class="text-brand-light hover:text-brand-dark" @click="closeNudgeModal">
                            Tutup
                        </button>
                    </div>

                    <div class="mt-5 space-y-3">
                        <label class="block text-sm font-semibold text-brand-dark">Pesan</label>
                        <textarea
                            v-model="draftMessage"
                            rows="5"
                            class="w-full rounded-[16px] border border-[#DCDEDD] px-4 py-3 text-sm text-brand-dark outline-none focus:ring-2 focus:ring-[#0C51D9]"
                        ></textarea>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-[16px] border border-[#DCDEDD] px-4 py-2.5 text-sm font-semibold text-brand-dark"
                            @click="closeNudgeModal"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            class="rounded-[16px] bg-[#0C51D9] px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
                            :disabled="!selectedMember || isNudging(selectedMember.id)"
                            @click="submitNudge"
                        >
                            Kirim Nudge
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
