<template>
    <div
        class="rounded-2xl transition-all duration-300 p-6 group relative overflow-hidden flex flex-col justify-between"
        :class="[
            highlight
                ? 'min-h-[220px] bg-gradient-to-br from-gray-900 to-gray-950 border border-white/5 shadow-xl'
                : 'bg-white border border-brand-border hover:ring-2 hover:ring-brand-primary/20',
        ]"
    >
        <div class="relative z-10 flex flex-col justify-between h-full">
            <!-- Non-Highlight Header -->
            <div v-if="!highlight" class="flex items-center justify-between mb-4">
                <h3
                    class="text-xs font-semibold uppercase tracking-wider text-brand-light group-hover:text-brand-primary transition-colors"
                >
                    {{ title }}
                </h3>
                <div v-if="icon" class="p-2.5 bg-primary-50 rounded-lg group-hover:bg-brand-primary transition-colors">
                    <component :is="icon" class="w-5 h-5 text-brand-primary group-hover:text-white transition-colors" />
                </div>
            </div>

            <!-- Highlight Content Wrapper -->
            <div :class="highlight ? 'flex flex-col' : 'space-y-2 relative'">
                <!-- Highlight Trend & Header -->
                <template v-if="highlight">
                    <div v-if="trend !== null" class="mb-5 inline-flex self-start">
                        <span
                            class="inline-flex flex-row items-center text-[13px] font-semibold px-3.5 py-1.5 rounded-full bg-slate-600/40 text-white backdrop-blur-md"
                        >
                            <component :is="trendIcon" class="w-3.5 h-3.5 mr-1.5" />
                            {{ trend >= 0 ? "+" : "" }}{{ trend }}% this month
                        </span>
                    </div>
                    <h3 class="text-brand-white-90 text-sm font-medium">
                        {{ title }}
                    </h3>
                </template>

                <!-- Value and Subtitle Container -->
                <div class="flex items-center justify-between relative">
                    <div :class="highlight ? 'flex flex-col space-y-1' : 'w-full'">
                        <div class="flex items-baseline space-x-3" :class="!highlight ? 'justify-between' : ''">
                            <span
                                class="tracking-tight"
                                :class="
                                    highlight
                                        ? 'text-brand-white text-3xl sm:text-5xl font-extrabold leading-none my-4'
                                        : 'text-[28px] leading-tight font-extrabold text-brand-dark'
                                "
                            >
                                {{ formattedValue }}
                            </span>
                            <!-- Trend on non-highlight -->
                            <span
                                v-if="!highlight && trend !== null"
                                :class="[
                                    trendClass,
                                    'flex items-center text-xs font-bold px-2 py-1 rounded-[6px] bg-opacity-10 ',
                                ]"
                                :style="
                                    trend >= 0
                                        ? 'background-color: rgba(16, 185, 129, 0.1)'
                                        : 'background-color: rgba(239, 68, 68, 0.1)'
                                "
                            >
                                <component :is="trendIcon" class="w-3 h-3 mr-1" />
                                {{ Math.abs(trend) }}%
                            </span>
                        </div>

                        <p
                            v-if="subtitle"
                            :class="
                                highlight
                                    ? 'text-brand-white-80 text-base font-normal'
                                    : 'text-sm font-medium text-gray-400'
                            "
                        >
                            {{ subtitle }}
                        </p>
                    </div>

                    <!-- Highlight Icon Block -->
                    <div v-if="highlight && icon" class="absolute right-0 top-1/2 -translate-y-1/2 -mr-1">
                        <div class="p-5 bg-slate-500/20 rounded-[26px] backdrop-blur-xl shrink-0">
                            <component :is="icon" class="w-10 h-10 text-white opacity-95" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div
            v-if="loading"
            :class="highlight ? 'bg-black/40 border-black/10' : 'bg-white/80'"
            class="absolute inset-0 backdrop-blur-sm flex items-center justify-center rounded-lg z-20"
        >
            <div class="w-full max-w-[60%] h-2 bg-slate-200/50 rounded-full overflow-hidden">
                <div class="h-full bg-brand-primary w-1/2 animate-[progress_1s_ease-in-out_infinite]"></div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from "vue";
import { ArrowUpIcon, ArrowDownIcon } from "lucide-vue-next";

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    value: {
        type: [Number, String],
        required: true,
    },
    format: {
        type: String,
        default: "number", // 'number', 'currency', 'percentage', 'days'
    },
    trend: {
        type: Number,
        default: null,
    },
    subtitle: {
        type: String,
        default: null,
    },
    icon: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    highlight: {
        type: Boolean,
        default: false,
    },
});

const formattedValue = computed(() => {
    if (props.loading) return "...";

    const val = parseFloat(props.value);

    switch (props.format) {
        case "currency":
            return `IDR ${new Intl.NumberFormat("id-ID", {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0,
            }).format(val)}`;
        case "percentage":
            return `${val.toFixed(1)}%`;
        case "days":
            return `${val.toFixed(0)} days`;
        default:
            return new Intl.NumberFormat("id-ID").format(val);
    }
});

const trendClass = computed(() => {
    if (props.trend === null) return "";
    return props.trend >= 0 ? "text-green-600" : "text-red-600";
});

const trendIcon = computed(() => {
    if (props.trend === null) return null;
    return props.trend >= 0 ? ArrowUpIcon : ArrowDownIcon;
});
</script>
