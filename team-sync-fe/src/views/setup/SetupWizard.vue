<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useSetupStore } from "@/stores/setup";
import { useToast } from "@/composables/useToast";
import Cookies from "js-cookie";
import {
    KeyRound,
    Stethoscope,
    UserPlus,
    CheckCircle2,
    AlertCircle,
    AlertTriangle,
    Info,
    RefreshCw,
    ArrowRight,
    ArrowLeft,
    PartyPopper,
} from "lucide-vue-next";

const router = useRouter();
const setupStore = useSetupStore();
const toast = useToast();

const currentStep = ref(1);
const totalSteps = 3;

// Step 1: License
const licenseKey = ref("");
const licenseActivated = ref(false);

// Step 3: Superadmin
const adminForm = ref({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
});

const setupComplete = ref(false);

onMounted(async () => {
    try {
        await setupStore.fetchSetupStatus();

        if (!setupStore.needsSetup) {
            router.replace({ name: "login" });
            return;
        }

        if (setupStore.hasLicense) {
            licenseActivated.value = true;
            currentStep.value = 2;
        }
    } catch {
        // Continue with setup even if status check fails
    }
});

const stepConfig = computed(() => [
    { number: 1, label: "Lisensi", icon: KeyRound },
    { number: 2, label: "Kesehatan Sistem", icon: Stethoscope },
    { number: 3, label: "Akun Admin", icon: UserPlus },
]);

// Step 1 handlers
const verifyAndActivateLicense = async () => {
    if (!licenseKey.value.trim()) {
        return;
    }

    setupStore.resetError();

    try {
        await setupStore.verifyLicense(licenseKey.value.trim());
        await setupStore.activateLicense(licenseKey.value.trim());
        licenseActivated.value = true;
        toast.success("Lisensi Valid", "Lisensi berhasil diaktifkan.");
        currentStep.value = 2;
        await setupStore.fetchDoctor();
    } catch {
        toast.error("Lisensi Gagal", setupStore.error || "Lisensi tidak valid atau sudah kadaluarsa.");
    }
};

// Step 2 handlers
const runDoctor = async () => {
    try {
        await setupStore.fetchDoctor();
    } catch {
        toast.error("Gagal", "Tidak dapat menjalankan pemeriksaan sistem.");
    }
};

const doctorStatusIcon = (status) => {
    if (status === "pass") return CheckCircle2;
    if (status === "fail") return AlertCircle;
    if (status === "warn") return AlertTriangle;
    return Info;
};

const doctorStatusColor = (status) => {
    if (status === "pass") return "text-green-600";
    if (status === "fail") return "text-red-600";
    if (status === "warn") return "text-amber-600";
    return "text-blue-600";
};

const doctorStatusBg = (status) => {
    if (status === "pass") return "bg-green-50 border-green-200";
    if (status === "fail") return "bg-red-50 border-red-200";
    if (status === "warn") return "bg-amber-50 border-amber-200";
    return "bg-blue-50 border-blue-200";
};

// Step 3 handlers
const submitBootstrap = async () => {
    setupStore.resetError();

    try {
        const result = await setupStore.bootstrap(
            adminForm.value.name,
            adminForm.value.email,
            adminForm.value.password,
            adminForm.value.password_confirmation,
        );

        if (result?.token) {
            Cookies.set("token", result.token);
        }

        setupComplete.value = true;
        toast.success("Selamat!", "Server Anda sudah menyala dan siap digunakan.");
    } catch {
        toast.error("Gagal", setupStore.error || "Tidak dapat membuat akun admin.");
    }
};

const goToDashboard = () => {
    router.replace({ name: "login" });
};

const canProceedStep2 = computed(() => setupStore.isDoctorHealthy);
</script>

<template>
    <div
        class="min-h-[100dvh] bg-gradient-to-br from-slate-50 via-white to-blue-50 flex flex-col items-center justify-center p-4 sm:p-8"
    >
        <div class="w-full max-w-2xl">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center gap-3 mb-4">
                    <div
                        class="w-12 h-12 bg-brand-primary rounded-xl flex items-center justify-center"
                    >
                        <span class="text-white text-lg font-bold">TS</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900">Team Sync Pro</h1>
                </div>
                <p class="text-gray-500 text-sm">Setup Wizard - Konfigurasi awal instance Anda</p>
            </div>

            <!-- Stepper -->
            <div v-if="!setupComplete" class="flex items-center justify-center gap-2 mb-8">
                <template v-for="(step, index) in stepConfig" :key="step.number">
                    <div
                        class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold transition-all"
                        :class="
                            currentStep === step.number
                                ? 'bg-brand-primary text-white'
                                : currentStep > step.number
                                  ? 'bg-green-100 text-green-700'
                                  : 'bg-gray-100 text-gray-400'
                        "
                    >
                        <component :is="currentStep > step.number ? CheckCircle2 : step.icon" class="w-4 h-4" />
                        <span class="hidden sm:inline">{{ step.label }}</span>
                        <span class="sm:hidden">{{ step.number }}</span>
                    </div>
                    <div
                        v-if="index < stepConfig.length - 1"
                        class="w-8 h-0.5 bg-gray-200"
                        :class="{ 'bg-green-300': currentStep > step.number }"
                    ></div>
                </template>
            </div>

            <!-- Step 1: License Upload -->
            <div
                v-if="currentStep === 1 && !setupComplete"
                class="bg-white rounded-3xl border border-gray-200 p-6 sm:p-8 shadow-sm"
            >
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-blue-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <KeyRound class="w-8 h-8 text-brand-primary" />
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">Aktivasi Lisensi</h2>
                    <p class="text-gray-500 text-sm mt-2">
                        Masukkan kunci lisensi yang Anda dapatkan dari email pembelian.
                    </p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Kunci Lisensi</label>
                        <textarea
                            v-model="licenseKey"
                            rows="5"
                            placeholder="Paste kunci lisensi di sini..."
                            class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-gray-900 outline-none focus:ring-2 focus:ring-brand-primary focus:border-brand-primary font-mono break-all"
                        ></textarea>
                    </div>

                    <div
                        v-if="setupStore.error"
                        class="rounded-xl bg-red-50 border border-red-200 p-3 text-sm text-red-700 flex items-start gap-2"
                    >
                        <AlertCircle class="w-5 h-5 flex-shrink-0 mt-0.5" />
                        <span>
                            {{ typeof setupStore.error === "string" ? setupStore.error : "Kunci lisensi tidak valid." }}
                        </span>
                    </div>

                    <button
                        type="button"
                        class="w-full flex items-center justify-center gap-2 rounded-2xl bg-brand-primary px-4 py-3 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60 transition-all"
                        :disabled="!licenseKey.trim() || setupStore.licenseVerifyLoading"
                        @click="verifyAndActivateLicense"
                    >
                        <RefreshCw v-if="setupStore.licenseVerifyLoading" class="w-4 h-4 animate-spin" />
                        <template v-else>
                            Verifikasi & Aktifkan
                            <ArrowRight class="w-4 h-4" />
                        </template>
                    </button>
                </div>
            </div>

            <!-- Step 2: System Health -->
            <div
                v-if="currentStep === 2 && !setupComplete"
                class="bg-white rounded-3xl border border-gray-200 p-6 sm:p-8 shadow-sm"
            >
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-green-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <Stethoscope class="w-8 h-8 text-green-600" />
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">Lisensi Valid! Mari periksa kesiapan server.</h2>
                    <p class="text-gray-500 text-sm mt-2">Memastikan semua komponen sistem berjalan dengan baik.</p>
                </div>

                <div v-if="setupStore.doctorLoading" class="space-y-3">
                    <div v-for="i in 5" :key="i" class="h-14 rounded-xl bg-gray-100 animate-pulse"></div>
                </div>

                <div v-else-if="setupStore.doctorChecks.length > 0" class="space-y-3">
                    <div
                        v-for="check in setupStore.doctorChecks"
                        :key="check.label"
                        class="flex items-start gap-3 rounded-xl border p-3"
                        :class="doctorStatusBg(check.status)"
                    >
                        <component
                            :is="doctorStatusIcon(check.status)"
                            class="w-5 h-5 mt-0.5 flex-shrink-0"
                            :class="doctorStatusColor(check.status)"
                        />
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ check.label }}</p>
                            <p class="text-xs text-gray-600 mt-0.5">{{ check.message }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 mt-6">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-2xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:border-brand-primary"
                        @click="runDoctor"
                    >
                        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': setupStore.doctorLoading }" />
                        Cek Ulang
                    </button>
                    <button
                        type="button"
                        class="flex-1 flex items-center justify-center gap-2 rounded-2xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60 transition-all"
                        :disabled="!canProceedStep2"
                        @click="currentStep = 3"
                    >
                        Lanjut
                        <ArrowRight class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Step 3: Superadmin Setup -->
            <div
                v-if="currentStep === 3 && !setupComplete"
                class="bg-white rounded-3xl border border-gray-200 p-6 sm:p-8 shadow-sm"
            >
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-purple-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <UserPlus class="w-8 h-8 text-purple-600" />
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">Buat Akun Superadmin</h2>
                    <p class="text-gray-500 text-sm mt-2">
                        Akun ini akan memiliki akses penuh ke seluruh modul administratif.
                    </p>
                </div>

                <form class="space-y-4" @submit.prevent="submitBootstrap">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nama Lengkap</label>
                        <input
                            v-model="adminForm.name"
                            type="text"
                            required
                            placeholder="Nama administrator"
                            class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-gray-900 outline-none focus:ring-2 focus:ring-brand-primary"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Email</label>
                        <input
                            v-model="adminForm.email"
                            type="email"
                            required
                            placeholder="admin@perusahaan.com"
                            class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-gray-900 outline-none focus:ring-2 focus:ring-brand-primary"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Password</label>
                        <input
                            v-model="adminForm.password"
                            type="password"
                            required
                            minlength="8"
                            placeholder="Minimal 8 karakter"
                            class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-gray-900 outline-none focus:ring-2 focus:ring-brand-primary"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Konfirmasi Password</label>
                        <input
                            v-model="adminForm.password_confirmation"
                            type="password"
                            required
                            minlength="8"
                            placeholder="Ulangi password"
                            class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm text-gray-900 outline-none focus:ring-2 focus:ring-brand-primary"
                        />
                    </div>

                    <div
                        v-if="setupStore.error"
                        class="rounded-xl bg-red-50 border border-red-200 p-3 text-sm text-red-700"
                    >
                        <template v-if="typeof setupStore.error === 'object'">
                            <ul class="list-disc list-inside">
                                <li v-for="(messages, field) in setupStore.error" :key="field">
                                    {{ Array.isArray(messages) ? messages.join(", ") : messages }}
                                </li>
                            </ul>
                        </template>
                        <template v-else>{{ setupStore.error }}</template>
                    </div>

                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-2xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700"
                            @click="currentStep = 2"
                        >
                            <ArrowLeft class="w-4 h-4" />
                            Kembali
                        </button>
                        <button
                            type="submit"
                            class="flex-1 flex items-center justify-center gap-2 rounded-2xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60 transition-all"
                            :disabled="setupStore.bootstrapLoading"
                        >
                            <RefreshCw v-if="setupStore.bootstrapLoading" class="w-4 h-4 animate-spin" />
                            <template v-else>
                                Buat Akun & Mulai
                                <ArrowRight class="w-4 h-4" />
                            </template>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Setup Complete -->
            <div
                v-if="setupComplete"
                class="bg-white rounded-3xl border border-gray-200 p-6 sm:p-8 shadow-sm text-center"
            >
                <div class="w-20 h-20 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <PartyPopper class="w-10 h-10 text-green-600" />
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Server Anda sudah menyala!</h2>
                <p class="text-gray-500 mb-6">Instance Team Sync Pro siap digunakan. Silakan masuk ke dashboard.</p>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-2xl bg-brand-primary px-6 py-3 text-sm font-semibold text-white hover:brightness-110 transition-all"
                    @click="goToDashboard"
                >
                    Masuk ke Dashboard
                    <ArrowRight class="w-4 h-4" />
                </button>
            </div>
        </div>
    </div>
</template>
