<script setup lang="ts">
import { X, AlertCircle } from "lucide-vue-next";
import ModalWrapper from "@/components/common/ModalWrapper.vue";

interface Props {
    show: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits(["close"]);
</script>

<template>
    <ModalWrapper :show="show" maxWidth="sm" @close="emit('close')">
        <!-- Modal Content -->
        <div class="text-center pt-2">
            <div class="flex justify-center mb-4">
                <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center">
                    <AlertCircle class="w-8 h-8 text-red-600" />
                </div>
            </div>

            <h3 class="text-brand-dark text-xl font-bold mb-2">Oops! Something's Not Right</h3>
            <p class="text-brand-light text-base font-normal mb-6">
                Please check your data again and make sure all required fields are filled correctly.
            </p>

            <button
                type="button"
                @click="emit('close')"
                class="w-full btn-primary rounded-[12px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-red-600 transition-all duration-300 bg-gradient-to-r from-red-500 to-red-600 shadow-lg px-6 py-3"
            >
                <span class="text-white text-base font-semibold">OK, I'll Fix It</span>
            </button>
        </div>
    </ModalWrapper>
</template>
