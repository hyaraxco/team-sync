<script setup>
import { BookOpen, Check, Lightbulb, Users, Zap, Copy } from "lucide-vue-next";
</script>

<template>
  <!-- Right Sidebar -->
  <div class="w-full lg:w-80 flex-shrink-0">
    <!-- Tips Section -->
    <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6 top-6">
      <div class="flex items-center gap-3 mb-6">
        <div
          class="w-12 h-12 bg-yellow-50 rounded-[12px] flex items-center justify-center"
        >
          <Lightbulb class="w-6 h-6 text-yellow-600" />
        </div>
        <div>
          <h3 class="text-brand-dark text-xl font-bold">Team Creation Tips</h3>
          <p class="text-brand-light text-sm font-normal">
            Best practices for setting up teams
          </p>
        </div>
      </div>

      <div class="space-y-4">
        <div class="flex items-start gap-3">
          <div
            class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
          >
            <Check class="w-3 h-3 text-green-600" />
          </div>
          <div>
            <p class="text-brand-dark text-base font-semibold">
              Choose a Clear Name
            </p>
            <p class="text-brand-light text-xs font-normal">
              Pick a descriptive name that reflects the team's purpose and
              function.
            </p>
          </div>
        </div>

        <div class="flex items-start gap-3">
          <div
            class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
          >
            <Check class="w-3 h-3 text-green-600" />
          </div>
          <div>
            <p class="text-brand-dark text-base font-semibold">
              Select Right Lead
            </p>
            <p class="text-brand-light text-xs font-normal">
              Choose someone with leadership experience and domain expertise.
            </p>
          </div>
        </div>

        <div class="flex items-start gap-3">
          <div
            class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
          >
            <Check class="w-3 h-3 text-green-600" />
          </div>
          <div>
            <p class="text-brand-dark text-base font-semibold">
              Define Clear Goals
            </p>
            <p class="text-brand-light text-xs font-normal">
              Write a clear description of what the team will achieve.
            </p>
          </div>
        </div>

        <div class="flex items-start gap-3">
          <div
            class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
          >
            <Check class="w-3 h-3 text-green-600" />
          </div>
          <div>
            <p class="text-brand-dark text-base font-semibold">
              Plan for Growth
            </p>
            <p class="text-brand-light text-xs font-normal">
              Consider how the team will scale and evolve over time.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions Section -->
    <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6 mt-6">
      <div class="flex items-center gap-3 mb-6">
        <div
          class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center"
        >
          <Zap class="w-6 h-6 text-blue-600" />
        </div>
        <div>
          <h3 class="text-brand-dark text-xl font-bold">Quick Actions</h3>
          <p class="text-brand-light text-sm font-normal">
            Common team setup tasks
          </p>
        </div>
      </div>

      <div class="space-y-3">
        <button
          class="w-full border border-[#DCDEDD] rounded-[12px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-4 py-3 flex items-center gap-3 text-left"
        >
          <Users class="w-5 h-5 text-gray-600" />
          <div class="flex-1">
            <h4 class="text-brand-dark text-sm font-semibold">
              Browse Employees
            </h4>
            <p class="text-brand-light text-xs">Find potential team members</p>
          </div>
        </button>

        <button
          class="w-full border border-[#DCDEDD] rounded-[12px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-4 py-3 flex items-center gap-3 text-left"
        >
          <Copy class="w-5 h-5 text-gray-600" />
          <div class="flex-1">
            <h4 class="text-brand-dark text-sm font-semibold">
              Copy Existing Team
            </h4>
            <p class="text-brand-light text-xs">Use another team as template</p>
          </div>
        </button>

        <button
          class="w-full border border-[#DCDEDD] rounded-[12px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-4 py-3 flex items-center gap-3 text-left"
        >
          <BookOpen class="w-5 h-5 text-gray-600" />
          <div class="flex-1">
            <h4 class="text-brand-dark text-sm font-semibold">
              Team Setup Guide
            </h4>
            <p class="text-brand-light text-xs">Step-by-step instructions</p>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>
