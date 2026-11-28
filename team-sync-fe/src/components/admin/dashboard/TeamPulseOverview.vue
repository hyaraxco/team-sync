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
        badge: "bg-red-100 text-red-800",
        icon: ShieldAlert,
    },
    yellow: {
        label: "Perlu Perhatian",
        badge: "bg-amber-100 text-amber-800",
        icon: AlertTriangle,
    },
    green: {
        label: "Aman",
        badge: "bg-green-100 text-green-800",
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
    <div class="bg-[var(--color-surface)] border border-brand-border rounded-2xl p-4 sm:p-5 space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 rounded-xl bg-brand-primary flex items-center justify-center"
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
                    class="w-10 h-10 rounded-full border border-brand-border flex items-center justify-center hover:ring-2 hover:ring-brand-primary/20 transition-all duration-200"
                    @click="dashboardStore.fetchTeamPulse()"
                >
                    <RefreshCw class="w-4 h-4 text-brand-dark" :class="{ 'animate-spin': teamPulseLoading }" />
                </button>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="rounded-2xl border border-brand-border bg-[var(--color-surface)] p-4">
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span>
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-dark">Butuh Aksi</p>
                </div>
                <p class="text-2xl font-extrabold text-brand-dark mt-2 tabular-nums">{{ summary.red }}</p>
                <p class="text-xs text-brand-light mt-1">Perlu tindak lanjut cepat</p>
            </div>
            <div class="rounded-2xl border border-brand-border bg-[var(--color-surface)] p-4">
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-dark">Perlu Sapaan</p>
                </div>
                <p class="text-2xl font-extrabold text-brand-dark mt-2 tabular-nums">{{ summary.yellow }}</p>
                <p class="text-xs text-brand-light mt-1">Perlu sapaan ringan</p>
            </div>
            <div class="rounded-2xl border border-brand-border bg-[var(--color-surface)] p-4">
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-dark">Stabil</p>
                </div>
                <p class="text-2xl font-extrabold text-brand-dark mt-2 tabular-nums">{{ summary.green }}</p>
                <p class="text-xs text-brand-light mt-1">Berjalan normal</p>
            </div>
            <div class="rounded-2xl border border-brand-border bg-[var(--color-surface)] p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-light">Total</p>
                <p class="text-2xl font-extrabold text-brand-dark mt-2 tabular-nums">{{ summary.total }}</p>
                <p class="text-xs text-brand-light mt-1">Anggota tim termonitor</p>
            </div>
        </div>

        <div v-if="teamPulseLoading" class="space-y-3">
            <div v-for="i in 3" :key="i" class="h-28 rounded-2xl bg-gray-100 animate-pulse"></div>
        </div>

        <div
            v-else-if="staffMembers.length === 0"
            class="rounded-2xl border border-dashed border-brand-border p-8 text-center"
        >
            <p class="text-brand-dark font-semibold">Belum ada anggota tim yang bisa dipantau</p>
            <p class="text-brand-light text-sm mt-1">Pastikan manager sudah memiliki tim aktif.</p>
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="member in staffMembers"
                :key="member.id"
                class="border border-brand-border bg-[var(--color-surface)] rounded-2xl p-4 sm:p-5 hover:ring-2 hover:ring-brand-primary/20 transition-all"
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
                                <p class="text-brand-dark font-bold text-base leading-tight">{{ member.name }}</p>
                                <span
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                    :class="riskConfig[member.risk.level]?.badge || riskConfig.green.badge"
                                >
                                    <component
                                        :is="riskConfig[member.risk.level]?.icon || riskConfig.green.icon"
                                        class="w-3.5 h-3.5"
                                    />
                                    {{ riskConfig[member.risk.level]?.label || riskConfig.green.label }}
                                </span>
                            </div>
                            <p class="text-xs text-brand-light mt-1 leading-normal">
                                {{ member.job_title || "Tanpa jabatan" }}
                                <span v-if="member.team_name">• {{ member.team_name }}</span>
                            </p>
                            <p class="text-xs font-medium text-brand-dark mt-2">{{ member.risk.reason }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 xl:min-w-[380px]">
                        <div class="rounded-xl bg-[var(--color-surface-overlay)] border border-brand-border p-3">
                            <div class="flex items-center justify-between text-xs text-brand-light mb-2">
                                <span>Attendance</span>
                                <span>{{ member.attendance.label }}</span>
                            </div>
                            <div class="w-full bg-[var(--color-surface)] border border-brand-border rounded-full h-2 overflow-hidden">
                                <div
                                    class="h-full rounded-full bg-primary-700"
                                    :style="{ width: member.attendance.score + '%' }"
                                ></div>
                            </div>
                        </div>

                        <div class="rounded-xl bg-[var(--color-surface-overlay)] border border-brand-border p-3">
                            <div class="flex items-center justify-between text-xs text-brand-light mb-2">
                                <span>Task Velocity</span>
                                <span>{{ member.task_velocity.percent }}%</span>
                            </div>
                            <div class="w-full bg-[var(--color-surface)] border border-brand-border rounded-full h-2 overflow-hidden">
                                <div
                                    class="h-full rounded-full bg-emerald-600"
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
                            class="btn-primary whitespace-nowrap !py-2 !px-3.5 text-xs font-semibold"
                            :disabled="isNudging(member.id)"
                            @click="openNudgeModal(member)"
                        >
                            <LoaderCircle
                                v-if="isNudging(member.id)"
                                class="w-3.5 h-3.5 animate-spin"
                            />
                            <span>{{ isNudging(member.id) ? "Mengirim..." : "Sapa Kabar" }}</span>
                        </button>

                        <RouterLink
                            :to="member.detail_url"
                            class="btn-secondary whitespace-nowrap !py-2 !px-3.5 border border-brand-border text-xs font-semibold text-brand-dark hover:bg-[var(--color-surface-overlay)]"
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
                <div class="w-full max-w-xl rounded-3xl bg-[var(--color-surface)] border border-brand-border p-6 shadow-md">
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
                            class="w-full rounded-2xl border border-brand-border px-4 py-3 text-sm text-brand-dark outline-none focus:ring-2 focus:ring-brand-primary"
                        ></textarea>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-2xl border border-brand-border px-4 py-2.5 text-sm font-semibold text-brand-dark"
                            @click="closeNudgeModal"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            class="rounded-2xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
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
