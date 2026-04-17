<script setup>
import { ref, onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import { storeToRefs } from "pinia";
import Input from "@/components/common/form/Input.vue";
import { User as UserIcon, Mail, Lock, Upload } from "lucide-vue-next";

const authStore = useAuthStore();
const { user, loading, error, success } = storeToRefs(authStore);
const { updateProfile, checkAuth } = authStore;

const form = ref({
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
  profile_photo: null,
  profile_photo_url: "",
});

const photoInput = ref(null);

const init = async () => {
  if (!user.value) {
    await checkAuth();
  }
  form.value.name = user.value?.name || "";
  form.value.email = user.value?.email || "";

  form.value.profile_photo_url = user.value?.profile_photo || "";
  form.value.profile_photo = null;
};

onMounted(init);

const handlePhotoSelect = (e) => {
  const file = e.target.files?.[0];
  if (file) {
    form.value.profile_photo = file;
    form.value.profile_photo_url = URL.createObjectURL(file);
  }
};

const handleSubmit = async () => {
  await updateProfile(form.value);
};
</script>

<template>
  <div class="p-5">
    <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6 mb-6">
      <div class="flex items-center gap-3 mb-6">
        <div
          class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center"
        >
          <UserIcon class="w-6 h-6 text-blue-600" />
        </div>
        <div>
          <h3 class="text-brand-dark text-xl font-bold">Edit Profile</h3>
          <p class="text-brand-light text-sm">
            Update your personal information
          </p>
        </div>
      </div>

      <form class="space-y-6" @submit.prevent="handleSubmit">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div class="mb-4">
            <Input
              id="name"
              name="name"
              type="text"
              v-model="form.name"
              label="Full Name"
              placeholder="Enter full name"
              :error="error?.name?.join(', ')"
              required
            >
              <template #icon>
                <UserIcon class="h-5 w-5 text-gray-400" />
              </template>
            </Input>
          </div>

          <div class="mb-4">
            <Input
              id="email"
              name="email"
              type="email"
              v-model="form.email"
              label="Email Address"
              placeholder="employee@company.com"
              :error="error?.email?.join(', ')"
              required
            >
              <template #icon>
                <Mail class="h-5 w-5 text-gray-400" />
              </template>
            </Input>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div class="mb-4">
            <Input
              id="password"
              name="password"
              type="password"
              v-model="form.password"
              label="New Password"
              placeholder="Enter new password (optional)"
              :error="error?.password?.join(', ')"
            >
              <template #icon>
                <Lock class="h-5 w-5 text-gray-400" />
              </template>
            </Input>
          </div>

          <div class="mb-4">
            <Input
              id="password_confirmation"
              name="password_confirmation"
              type="password"
              v-model="form.password_confirmation"
              label="Confirm Password"
              placeholder="Confirm new password"
              :error="error?.password_confirmation?.join(', ')"
            >
              <template #icon>
                <Lock class="h-5 w-5 text-gray-400" />
              </template>
            </Input>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div class="mb-4 md:col-span-2">
            <label class="block text-brand-dark text-base font-semibold mb-1"
              >Profile Photo</label
            >
            <div class="flex items-center gap-4">
              <div class="w-32 h-32">
                <div class="relative w-32 h-32">
                  <div
                    class="w-32 h-32 absolute bg-gradient-to-br from-primary-500 to-primary-600 rounded-full"
                  ></div>
                  <div
                    class="w-32 h-32 relative z-10 flex items-center justify-center"
                  >
                    <img
                      :src="form.profile_photo_url"
                      alt="Profile Photo"
                      class="w-16 h-16 object-cover rounded-full"
                      v-if="form.profile_photo_url"
                    />
                    <div
                      class="w-16 h-16 rounded-full bg-gray-100"
                      v-else
                    ></div>
                  </div>
                </div>
              </div>
              <div class="flex flex-col gap-2">
                <input
                  type="file"
                  accept="image/*"
                  class="hidden"
                  ref="photoInput"
                  @change="handlePhotoSelect"
                />
                <button
                  type="button"
                  class="border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-4 py-2 flex items-center gap-2 cursor-pointer"
                  @click="photoInput?.click()"
                >
                  <Upload class="w-4 h-4 text-gray-600" />
                  <span class="text-brand-dark text-base font-semibold"
                    >Upload Photo</span
                  >
                </button>
                <p class="text-brand-light text-xs">
                  JPG, PNG up to 2MB • Recommended: 400x400px
                </p>
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-4">
          <button
            type="submit"
            :disabled="loading"
            class="btn-primary rounded-[8px] border border-[#2151A0] hover:brightness-110 focus:ring-2 focus:ring-[#0C51D9] transition-all duration-300 blue-gradient blue-btn-shadow px-6 py-3"
          >
            <span class="text-brand-white text-base font-semibold">{{
              loading ? "Saving..." : "Save Changes"
            }}</span>
          </button>
          <span v-if="success" class="text-green-600 text-sm">{{
            success
          }}</span>
          <span v-if="typeof error === 'string'" class="text-red-600 text-sm">{{
            error
          }}</span>
        </div>
      </form>
    </div>
  </div>
</template>
