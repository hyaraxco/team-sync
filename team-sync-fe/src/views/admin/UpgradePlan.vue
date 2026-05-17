<script setup>
import { ref, computed } from "vue";
import { CircleCheck } from "lucide-vue-next";
import { useToast } from "@/composables/useToast";

const toast = useToast();
const billingCycle = ref("annual"); // 'monthly' | 'annual'

const plans = computed(() => [
    {
        id: "pro", // Jadikan Pro sebagai kartu pertama yang di-highlight (seperti "FOUNDER" di gambar)
        name: "PRO",
        isHighlighted: true,
        headerBadge: "Rekomendasi Utama 🚀",
        savings: "Save Rp 240k/yr",
        price: { monthly: 99000, annual: 79000 },
        priceSuffix: "/ user / bln",
        description: "+ Fitur lengkap untuk UKM",
        features: [
            "Maks 100 Karyawan",
            "Unlimited Tim & Project",
            "Payroll, Payslip & Overtime",
            "Performance Review (TOPSIS)",
            "Analytics & Data Export",
            "Leave Management Advanced",
            "Subdomain company.teamsync.com",
        ],
        cta: "Mulai Pro",
        footerText: "Trial gratis 30 hari",
        isCurrent: false,
    },
    {
        id: "free",
        name: "FREE",
        isHighlighted: false,
        savings: null,
        price: { monthly: 0, annual: 0 },
        priceSuffix: "/ selamanya",
        description: "+ Fitur dasar HRIS",
        features: [
            "Maks 25 Karyawan",
            "Maks 2 Tim & 5 Project",
            "Dashboard & Team Pulse",
            "Absensi (Clock in/out)",
            "Task Management",
            "Leave Management Basic",
            "Support Komunitas",
        ],
        cta: "Current Plan",
        footerText: "",
        isCurrent: true,
    },
    {
        id: "business",
        name: "BUSINESS",
        isHighlighted: false,
        savings: "Save Rp 480k/yr",
        price: { monthly: 199000, annual: 159000 },
        priceSuffix: "/ user / bln",
        description: "+ Skala Enterprise",
        features: [
            "Unlimited Karyawan & Tim",
            "Unlimited Project",
            "Dedicated Support (4hr SLA)",
            "Custom Domain Included",
            "Advanced Encryption Controls",
            "Custom Integration",
            "On-premise Option (Lifetime)",
        ],
        cta: "Hubungi Sales",
        footerText: "",
        isCurrent: false,
    },
]);

const handleUpgrade = (planId) => {
    if (planId === "free") return;
    toast.info("Coming soon", "Payment gateway integration will be available shortly.");
};
</script>

<template>
    <div class="max-w-6xl mx-auto py-12 px-4 sm:px-6 lg:px-8 transition-colors duration-300">
        <!-- Billing Toggle -->
        <div class="flex justify-center mb-16">
            <div
                class="inline-flex items-center bg-white rounded-full border border-brand-border shadow-sm p-1"
            >
                <button
                    class="px-6 py-2.5 rounded-full text-sm font-bold transition-all duration-200 flex items-center gap-2"
                    :class="
                        billingCycle === 'annual'
                            ? 'bg-gray-900 shadow-sm text-white'
                            : 'text-gray-500 hover:text-gray-900'
                    "
                    @click="billingCycle = 'annual'"
                >
                    Billed yearly
                    <span
                        class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider"
                        :class="
                            billingCycle === 'annual'
                                ? 'bg-white/20 text-white'
                                : 'bg-green-100 text-green-700'
                        "
                    >
                        Save 20%
                    </span>
                </button>
                <button
                    class="px-6 py-2.5 rounded-full text-sm font-bold transition-all duration-200"
                    :class="
                        billingCycle === 'monthly'
                            ? 'bg-gray-900 shadow-sm text-white'
                            : 'text-gray-500 hover:text-gray-900'
                    "
                    @click="billingCycle = 'monthly'"
                >
                    Billed monthly
                </button>
            </div>
        </div>

        <!-- Pricing Cards Layout -->
        <div class="flex flex-col lg:flex-row items-stretch justify-center gap-8 lg:gap-0">
            <!-- Highlighted Card (PRO) -->
            <div class="w-full lg:w-1/3 relative z-10 lg:-mr-4 group">
                <div
                    class="bg-white rounded-3xl shadow-md border-2 border-brand-primary flex flex-col h-full overflow-hidden transition-all duration-300"
                >
                    <!-- Top Badge -->
                    <div
                        class="bg-blue-50 py-3 text-center border-b border-blue-100"
                    >
                        <span
                            class="text-[11px] font-black text-brand-primary uppercase tracking-[0.2em]"
                        >
                            {{ plans[0].headerBadge }}
                        </span>
                    </div>

                    <div class="p-8 sm:p-10 flex flex-col h-full">
                        <!-- Header -->
                        <div class="flex items-center justify-between mb-8">
                            <h3 class="text-xl font-black text-gray-900 tracking-tight">
                                {{ plans[0].name }}
                            </h3>
                            <span
                                v-if="billingCycle === 'annual' && plans[0].savings"
                                class="px-3 py-1 bg-yellow-100 text-yellow-800 text-[10px] font-black rounded-full uppercase"
                            >
                                {{ plans[0].savings }}
                            </span>
                        </div>

                        <!-- Price -->
                        <div class="mb-2 flex items-baseline gap-1">
                            <span class="text-xs font-bold text-gray-400 uppercase">Rp</span>
                            <span class="text-6xl font-black text-gray-900 tracking-tighter">
                                {{ (plans[0].price[billingCycle] / 1000).toString() }}
                                <span class="text-4xl">k</span>
                            </span>
                            <span class="text-sm font-bold text-gray-400 ml-1">
                                {{ plans[0].priceSuffix }}
                            </span>
                        </div>
                        <p class="text-sm font-semibold text-gray-500 mb-8">
                            {{ plans[0].description }}
                        </p>

                        <!-- Divider -->
                        <div class="h-px bg-gray-100 mb-8"></div>

                        <!-- Features -->
                        <ul class="space-y-5 mb-10 flex-1">
                            <li
                                v-for="(feature, index) in plans[0].features"
                                :key="index"
                                class="flex items-start gap-3.5"
                            >
                                <div
                                    class="mt-0.5 bg-blue-600 rounded-full p-0.5 shrink-0 shadow-sm shadow-blue-200"
                                >
                                    <CircleCheck class="w-4 h-4 text-white" stroke-width="3" />
                                </div>
                                <span class="text-sm text-gray-700 font-bold leading-tight">
                                    {{ feature }}
                                </span>
                            </li>
                        </ul>

                        <!-- CTA -->
                        <div class="mt-auto text-center">
                            <button
                                class="w-full rounded-2xl py-4 px-6 text-base font-black bg-brand-primary text-white hover:bg-primary-800 transition-all duration-300 shadow-xl shadow-blue-500/25 active:scale-[0.98]"
                                @click="handleUpgrade(plans[0].id)"
                            >
                                {{ plans[0].cta }}
                            </button>
                            <p
                                v-if="plans[0].footerText"
                                class="text-[11px] text-gray-400 mt-4 font-black uppercase tracking-widest"
                            >
                                {{ plans[0].footerText }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Standard Cards Container -->
            <div
                class="w-full lg:w-2/3 flex flex-col sm:flex-row bg-white rounded-3xl border border-brand-border shadow-md relative z-0 overflow-hidden transition-all duration-300"
            >
                <!-- Loop untuk plan sisanya -->
                <div
                    v-for="(plan, idx) in plans.slice(1)"
                    :key="plan.id"
                    class="w-full sm:w-1/2 flex flex-col p-8 sm:p-10 transition-colors duration-300"
                    :class="{ 'border-t sm:border-t-0 sm:border-l border-brand-border': idx > 0 }"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-8">
                        <h3 class="text-xl font-black text-gray-900 tracking-tight">{{ plan.name }}</h3>
                        <span
                            v-if="billingCycle === 'annual' && plan.savings"
                            class="px-3 py-1 bg-gray-100 text-gray-700 text-[10px] font-black rounded-full uppercase"
                        >
                            {{ plan.savings }}
                        </span>
                    </div>

                    <!-- Price -->
                    <div class="mb-2 flex items-baseline gap-1">
                        <span class="text-xs font-bold text-gray-400 uppercase">Rp</span>
                        <span class="text-5xl font-black text-gray-900 tracking-tighter">
                            {{ (plan.price[billingCycle] / 1000).toString() }}
                            <span class="text-3xl">k</span>
                        </span>
                        <span class="text-sm font-bold text-gray-400 ml-1">
                            {{ plan.priceSuffix }}
                        </span>
                    </div>
                    <p class="text-sm font-semibold text-gray-500 mb-8">{{ plan.description }}</p>

                    <!-- Divider -->
                    <div class="h-px bg-gray-100 mb-8"></div>

                    <!-- Features -->
                    <ul class="space-y-5 mb-10 flex-1">
                        <li v-for="(feature, index) in plan.features" :key="index" class="flex items-start gap-3.5">
                            <div class="mt-0.5 bg-gray-100 rounded-full p-0.5 shrink-0">
                                <CircleCheck class="w-4 h-4 text-gray-400" stroke-width="3" />
                            </div>
                            <span class="text-sm text-gray-600 font-bold leading-tight">
                                {{ feature }}
                            </span>
                        </li>
                    </ul>

                    <!-- CTA -->
                    <div class="mt-auto text-center">
                        <button
                            class="w-full rounded-2xl py-4 px-6 text-base font-black transition-all duration-300 active:scale-[0.98]"
                            :class="[
                                plan.isCurrent
                                    ? 'bg-gray-50 text-gray-400 cursor-default'
                                    : 'bg-gray-900 text-white hover:bg-black',
                            ]"
                            :disabled="plan.isCurrent"
                            @click="handleUpgrade(plan.id)"
                        >
                            {{ plan.cta }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
