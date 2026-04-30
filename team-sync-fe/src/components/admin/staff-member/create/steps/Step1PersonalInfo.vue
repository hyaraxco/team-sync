<script setup lang="ts">
import { Input, TextArea } from "@/components/common/form";
import { DEFAULT_AVATAR } from "@/helpers/format";
import {
  User,
  Mail,
  Lock,
  Eye,
  EyeOff,
  Phone,
  Calendar,
  MapPin,
  Hash,
  Building2,
  Camera,
  FolderOpen,
  X,
} from "lucide-vue-next";
import { ref, computed } from "vue";

interface Props {
  modelValue: any;
  errors?: any;
  isEditMode?: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits([
  "update:modelValue",
  "email-blur",
  "identity-number-blur",
]);

const form = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

// Profile photo handling
const profilePhotoInput = ref<HTMLInputElement | null>(null);
const photoError = ref("");

const allowedPhotoTypes = ["image/jpeg", "image/png", "image/gif"];
const maxPhotoSize = 2 * 1024 * 1024;

const handlePhotoSelect = (e: Event) => {
  const target = e.target as HTMLInputElement;
  const file = target.files?.[0];

  if (file) {
    if (!allowedPhotoTypes.includes(file.type)) {
      photoError.value = "Photo must be JPG, JPEG, PNG, or GIF.";
      form.value.profile_photo = null;
      form.value.profile_photo_url = "";
      target.value = "";
      return;
    }

    if (file.size > maxPhotoSize) {
      photoError.value = "Photo size must be 2MB or less.";
      form.value.profile_photo = null;
      form.value.profile_photo_url = "";
      target.value = "";
      return;
    }

    photoError.value = "";
    form.value.profile_photo = file;
    form.value.profile_photo_url = URL.createObjectURL(file);
  }
};

const deletePhoto = () => {
  form.value.profile_photo = null;
  form.value.profile_photo_url = "";
  photoError.value = "";
  if (profilePhotoInput.value) {
    profilePhotoInput.value.value = "";
  }
};

const handleEmailBlur = () => {
  emit("email-blur", form.value.email);
};

const handleIdentityNumberBlur = () => {
  emit("identity-number-blur", form.value.identity_number);
};

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
</script>

<template>
  <div class="flex flex-col 2xl:flex-row gap-6 items-start">
    <!-- Profile Photo Card -->
    <div class="w-full max-w-xl mx-auto 2xl:mx-0 2xl:max-w-none 2xl:w-80 2xl:flex-shrink-0">
      <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
        <div class="flex items-center gap-3 mb-6">
          <div
            class="w-12 h-12 bg-purple-50 rounded-[12px] flex items-center justify-center"
          >
            <Camera class="w-6 h-6 text-purple-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-xl font-bold">Profile Photo</h3>
            <p class="text-brand-light text-sm font-normal">
              Upload employee photo
            </p>
          </div>
        </div>

        <div class="flex flex-col items-center">
          <!-- Photo Preview -->
          <div class="mb-4">
            <div class="relative w-48 h-48">
              <img
                :src="form.profile_photo_url || DEFAULT_AVATAR"
                alt="Profile"
                class="w-48 h-48 rounded-full object-cover border-2 border-[#DCDEDD]"
              />
              <button
                v-if="form.profile_photo_url"
                type="button"
                @click="deletePhoto"
                class="absolute -top-2 -right-2 w-8 h-8 bg-red-500 text-white rounded-full hover:bg-red-600 transition-colors flex items-center justify-center"
              >
                <X class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div class="flex flex-col gap-3 items-center">
            <input
              type="file"
              accept=".jpg,.jpeg,.png,.gif"
              class="hidden"
              ref="profilePhotoInput"
              @change="handlePhotoSelect"
            />
            <button
              type="button"
              @click="profilePhotoInput?.click()"
              class="border border-[#DCDEDD] rounded-[8px] hover:border-[#0C51D9] hover:border-2 hover:bg-gray-50 transition-all duration-300 px-6 py-3 flex items-center gap-2"
            >
              <FolderOpen class="w-4 h-4 text-gray-600" />
              <span class="text-brand-dark text-base font-semibold"
                >Browse Photo</span
              >
            </button>
            <p class="text-brand-light text-xs text-center">
              JPG, PNG up to 2MB<br />Recommended: 400x400px
            </p>
            <p
              v-if="photoError || errors?.profile_photo?.length"
              class="text-red-600 text-xs text-center"
            >
              {{ photoError || errors?.profile_photo?.join(", ") }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Form Section -->
    <div class="flex-1 w-full">
      <!-- Account Credentials Section -->
      <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6 mb-6">
        <div class="flex items-center gap-3 mb-6">
          <div
            class="w-12 h-12 bg-purple-50 rounded-[12px] flex items-center justify-center"
          >
            <Lock class="w-6 h-6 text-purple-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-xl font-bold">
              Account Credentials
            </h3>
            <p class="text-brand-light text-sm font-normal">
              Login information and account details
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
          <div class="mb-4">
            <Input
              id="name"
              name="name"
              type="text"
              v-model="form.name"
              label="Full Name *"
              placeholder="Enter full name"
              :error="errors?.name?.join(', ')"
              required
            >
              <template #icon>
                <User class="h-5 w-5 text-gray-400" />
              </template>
            </Input>
          </div>

          <div class="mb-4">
            <Input
              id="email"
              name="email"
              type="email"
              v-model="form.email"
              @blur="handleEmailBlur"
              label="Email Address *"
              placeholder="employee@company.com"
              :error="errors?.email?.join(', ')"
              required
            >
              <template #icon>
                <Mail class="h-5 w-5 text-gray-400" />
              </template>
            </Input>
          </div>

          <div class="mb-4">
            <Input
              id="password"
              name="password"
              :type="showPassword ? 'text' : 'password'"
              v-model="form.password"
              :label="
                props.isEditMode
                  ? 'Password (leave blank to keep current)'
                  : 'Password *'
              "
              placeholder="Enter password (min. 8 characters)"
              :error="errors?.password?.join(', ')"
              :required="!props.isEditMode"
            >
              <template #icon>
                <Lock class="h-5 w-5 text-gray-400" />
              </template>
              <template #suffix>
                <button
                  type="button"
                  :aria-label="showPassword ? 'Hide password' : 'Show password'"
                  class="text-gray-500 hover:text-[#0C51D9] transition-colors"
                  @click="showPassword = !showPassword"
                >
                  <EyeOff v-if="showPassword" class="h-4 w-4" />
                  <Eye v-else class="h-4 w-4" />
                </button>
              </template>
            </Input>
          </div>

          <div class="mb-4">
            <Input
              id="password_confirmation"
              name="password_confirmation"
              :type="showPasswordConfirmation ? 'text' : 'password'"
              v-model="form.password_confirmation"
              :label="
                props.isEditMode ? 'Confirm Password' : 'Confirm Password *'
              "
              placeholder="Confirm password"
              :error="errors?.password_confirmation?.join(', ')"
              :required="!props.isEditMode"
            >
              <template #icon>
                <Lock class="h-5 w-5 text-gray-400" />
              </template>
              <template #suffix>
                <button
                  type="button"
                  :aria-label="
                    showPasswordConfirmation
                      ? 'Hide password confirmation'
                      : 'Show password confirmation'
                  "
                  class="text-gray-500 hover:text-[#0C51D9] transition-colors"
                  @click="showPasswordConfirmation = !showPasswordConfirmation"
                >
                  <EyeOff v-if="showPasswordConfirmation" class="h-4 w-4" />
                  <Eye v-else class="h-4 w-4" />
                </button>
              </template>
            </Input>
          </div>

          <div class="mb-4 xl:col-span-2">
            <Input
              id="identity_number"
              name="identity_number"
              type="text"
              v-model="form.identity_number"
              @blur="handleIdentityNumberBlur"
              label="Identity Number (NIK/KTP) *"
              placeholder="Enter identity number"
              :error="errors?.identity_number?.join(', ')"
              required
            >
              <template #icon>
                <Hash class="h-5 w-5 text-gray-400" />
              </template>
            </Input>
          </div>
        </div>
      </div>

      <!-- Personal Information Section -->
      <div class="bg-white border border-[#DCDEDD] rounded-[20px] p-6">
        <div class="flex items-center gap-3 mb-6">
          <div
            class="w-12 h-12 bg-blue-50 rounded-[12px] flex items-center justify-center"
          >
            <User class="w-6 h-6 text-blue-600" />
          </div>
          <div>
            <h3 class="text-brand-dark text-xl font-bold">
              Personal Information
            </h3>
            <p class="text-brand-light text-sm font-normal">
              Basic employee details and contact information
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
          <div class="mb-4">
            <Input
              id="phone"
              name="phone"
              type="tel"
              v-model="form.phone"
              label="Phone Number *"
              placeholder="+1 (555) 123-4567"
              :error="errors?.phone?.join(', ')"
            >
              <template #icon>
                <Phone class="h-5 w-5 text-gray-400" />
              </template>
            </Input>
          </div>

          <div class="mb-4">
            <Input
              id="date_of_birth"
              name="date_of_birth"
              type="date"
              v-model="form.date_of_birth"
              label="Date of Birth *"
              :error="errors?.date_of_birth?.join(', ')"
            >
              <template #icon>
                <Calendar class="h-5 w-5 text-gray-400" />
              </template>
            </Input>
          </div>

          <!-- Religion -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1.5"
              >Religion</label
            >
            <select
              id="religion"
              name="religion"
              v-model="form.religion"
              class="w-full px-4 py-3 border border-[#DCDEDD] rounded-[12px] hover:border-[#0C51D9] focus:border-[#0C51D9] transition-all duration-300 bg-white"
            >
              <option value="">Select religion</option>
              <option value="islam">Islam</option>
              <option value="kristen">Kristen Protestan</option>
              <option value="katolik">Katolik</option>
              <option value="hindu">Hindu</option>
              <option value="budha">Budha</option>
              <option value="konghucu">Konghucu</option>
            </select>
            <p v-if="errors?.religion?.join(', ')" class="text-red-500 text-xs mt-1">
              {{ errors?.religion?.join(', ') }}
            </p>
          </div>

          <!-- Marital Status -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1.5"
              >Marital Status</label
            >
            <select
              id="marital_status"
              name="marital_status"
              v-model="form.marital_status"
              class="w-full px-4 py-3 border border-[#DCDEDD] rounded-[12px] hover:border-[#0C51D9] focus:border-[#0C51D9] transition-all duration-300 bg-white"
            >
              <option value="">Select marital status</option>
              <option value="single">Single / Belum Menikah</option>
              <option value="married">Married / Menikah</option>
              <option value="widowed">Widowed / Janda/Duda</option>
              <option value="divorced">Divorced / Bercerai</option>
            </select>
            <p v-if="errors?.marital_status?.join(', ')" class="text-red-500 text-xs mt-1">
              {{ errors?.marital_status?.join(', ') }}
            </p>
          </div>

          <!-- Blood Type -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1.5"
              >Blood Type</label
            >
            <select
              id="blood_type"
              name="blood_type"
              v-model="form.blood_type"
              class="w-full px-4 py-3 border border-[#DCDEDD] rounded-[12px] hover:border-[#0C51D9] focus:border-[#0C51D9] transition-all duration-300 bg-white"
            >
              <option value="">Select blood type</option>
              <option value="A">A</option>
              <option value="B">B</option>
              <option value="AB">AB</option>
              <option value="O">O</option>
            </select>
          </div>

          <div class="mb-4">
            <Input
              id="place_of_birth"
              name="place_of_birth"
              type="text"
              v-model="form.place_of_birth"
              label="Place of Birth *"
              placeholder="e.g. Jakarta, Indonesia"
              :error="errors?.place_of_birth?.join(', ')"
            >
              <template #icon>
                <MapPin class="h-5 w-5 text-gray-400" />
              </template>
            </Input>
          </div>

          <!-- Gender (Full Width) -->
          <div class="xl:col-span-2 mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1.5"
              >Gender *</label
            >
            <div class="flex flex-col sm:flex-row gap-4">
              <!-- Male Option -->
              <label
                class="group card flex items-center justify-between w-full min-h-[60px] rounded-[16px] border border-[#DCDEDD] p-4 has-[:checked]:ring-2 has-[:checked]:ring-[#0C51D9] has-[:checked]:ring-offset-2 transition-all duration-300 cursor-pointer"
              >
                <div class="flex items-center gap-3">
                  <div
                    class="w-10 h-10 bg-blue-50 rounded-[12px] flex items-center justify-center"
                  >
                    <User class="w-5 h-5 text-blue-600" />
                  </div>
                  <div class="flex flex-col">
                    <p class="text-brand-dark text-base font-semibold">Male</p>
                  </div>
                </div>
                <div
                  class="relative flex items-center justify-center w-fit h-8 shrink-0 rounded-xl border border-[#DCDEDD] py-2 px-3 gap-2"
                >
                  <input
                    type="radio"
                    name="gender"
                    value="male"
                    class="hidden"
                    v-model="form.gender"
                  />
                  <div
                    class="flex size-[18px] rounded-full shadow-sm border border-[#DCDEDD] group-has-[:checked]:border-[5px] group-has-[:checked]:border-[#0C51D9] transition-all duration-300"
                  ></div>
                  <p
                    class="text-xs font-semibold after:content-['Select'] group-has-[:checked]:after:content-['Selected']"
                  ></p>
                </div>
              </label>

              <!-- Female Option -->
              <label
                class="group card flex items-center justify-between w-full min-h-[60px] rounded-[16px] border border-[#DCDEDD] p-4 has-[:checked]:ring-2 has-[:checked]:ring-[#0C51D9] has-[:checked]:ring-offset-2 transition-all duration-300 cursor-pointer"
              >
                <div class="flex items-center gap-3">
                  <div
                    class="w-10 h-10 bg-pink-50 rounded-[12px] flex items-center justify-center"
                  >
                    <User class="w-5 h-5 text-pink-600" />
                  </div>
                  <div class="flex flex-col">
                    <p class="text-brand-dark text-base font-semibold">
                      Female
                    </p>
                  </div>
                </div>
                <div
                  class="relative flex items-center justify-center w-fit h-8 shrink-0 rounded-xl border border-[#DCDEDD] py-2 px-3 gap-2"
                >
                  <input
                    type="radio"
                    name="gender"
                    value="female"
                    class="hidden"
                    v-model="form.gender"
                  />
                  <div
                    class="flex size-[18px] rounded-full shadow-sm border border-[#DCDEDD] group-has-[:checked]:border-[5px] group-has-[:checked]:border-[#0C51D9] transition-all duration-300"
                  ></div>
                  <p
                    class="text-xs font-semibold after:content-['Select'] group-has-[:checked]:after:content-['Selected']"
                  ></p>
                </div>
              </label>
            </div>
          </div>

          <!-- Address -->
          <div class="mb-4">
            <TextArea
              id="address"
              name="address"
              v-model="form.address"
              label="Address *"
              placeholder="Enter full address"
              :error="errors?.address?.join(', ')"
              rows="5"
            >
              <template #icon>
                <MapPin class="h-5 w-5 text-gray-400" />
              </template>
            </TextArea>
          </div>

          <!-- City and Post Code -->
          <div class="mb-4">
            <!-- City -->
            <div class="mb-4">
              <Input
                id="city"
                name="city"
                type="text"
                v-model="form.city"
                label="City *"
                placeholder="e.g. Jakarta"
                :error="errors?.city?.join(', ')"
                required
              >
                <template #icon>
                  <Building2 class="h-5 w-5 text-gray-400" />
                </template>
              </Input>
            </div>

            <!-- Postal Code -->
            <div class="mb-4">
              <Input
                id="postal_code"
                name="postal_code"
                type="text"
                v-model="form.postal_code"
                label="Postal Code *"
                placeholder="e.g. 12345"
                :error="errors?.postal_code?.join(', ')"
                required
              >
                <template #icon>
                  <Hash class="h-5 w-5 text-gray-400" />
                </template>
              </Input>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
