import { beforeEach, describe, expect, it, vi } from "vitest";
import { flushPromises, mount } from "@vue/test-utils";
import { nextTick, ref } from "vue";

const { setToken } = vi.hoisted(() => ({ setToken: vi.fn() }));

// 1. Action mocks
const fetchSetupStatus = vi.fn().mockResolvedValue({});
const verifyLicense = vi.fn().mockResolvedValue({});
const activateLicense = vi.fn().mockResolvedValue({});
const fetchDoctor = vi.fn().mockResolvedValue({});
const bootstrap = vi.fn().mockResolvedValue({ token: "fake-token" });
const resetError = vi.fn();
const push = vi.fn();
const replace = vi.fn();

// 2. State refs for store
const needsSetup = ref(true);
const hasLicense = ref(false);
const doctorResult = ref(null);
const licenseVerifyLoading = ref(false);
const doctorLoading = ref(false);
const bootstrapLoading = ref(false);
const error = ref(null);

// 3. Mocks
vi.mock("@/stores/setup", () => ({
    useSetupStore: () => ({
        fetchSetupStatus,
        verifyLicense,
        activateLicense,
        fetchDoctor,
        bootstrap,
        resetError,
        needsSetup: needsSetup.value,
        hasLicense: hasLicense.value,
        licenseVerifyLoading: licenseVerifyLoading.value,
        doctorLoading: doctorLoading.value,
        bootstrapLoading: bootstrapLoading.value,
        error: error.value,
        get isDoctorHealthy() {
            return doctorResult.value?.healthy === true;
        },
        get doctorChecks() {
            return doctorResult.value?.checks || [];
        },
    }),
}));

vi.mock("@/stores/auth", () => ({
    useAuthStore: () => ({ setToken }),
}));

const toastSuccess = vi.fn();
const toastError = vi.fn();

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: toastSuccess,
        error: toastError,
    }),
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();

    return {
        ...actual,
        storeToRefs: () => ({}),
    };
});

vi.mock("vue-router", () => ({
    useRouter: () => ({
        push,
        replace,
    }),
    useRoute: () => ({
        params: {},
        query: {},
        name: "setup",
    }),
    createRouter: vi.fn(() => ({
        push,
        replace,
    })),
    createWebHistory: vi.fn(),
}));

// 4. Import view AFTER mocks
import SetupWizard from "@/views/setup/SetupWizard.vue";

// 5. Factory
const factory = () =>
    mount(SetupWizard, {
        global: {
            stubs: {
                RouterLink: {
                    props: ["to"],
                    template: "<a><slot /></a>",
                },
            },
        },
    });

describe("SetupWizard smoke", () => {
    beforeEach(() => {
        needsSetup.value = true;
        hasLicense.value = false;
        doctorResult.value = null;
        licenseVerifyLoading.value = false;
        doctorLoading.value = false;
        bootstrapLoading.value = false;
        error.value = null;
        fetchSetupStatus.mockClear();
        verifyLicense.mockClear();
        activateLicense.mockClear();
        fetchDoctor.mockClear();
        bootstrap.mockClear();
        setToken.mockClear();
        resetError.mockClear();
        push.mockClear();
        replace.mockClear();
        toastSuccess.mockClear();
        toastError.mockClear();
    });

    it("renders step 1 license activation form on initial load", async () => {
        const wrapper = factory();
        await nextTick();
        await Promise.resolve();

        expect(wrapper.text()).toContain("Team Sync Pro");
        expect(wrapper.text()).toContain("Setup Wizard");
        expect(wrapper.text()).toContain("Aktivasi Lisensi");
        expect(wrapper.text()).toContain("Kunci Lisensi");
        expect(wrapper.find("textarea").exists()).toBe(true);
    });

    it("fetches setup status on mount", async () => {
        const wrapper = factory();
        await nextTick();
        await Promise.resolve();

        expect(fetchSetupStatus).toHaveBeenCalledTimes(1);
    });

    it("renders stepper with all 3 steps", async () => {
        const wrapper = factory();
        await nextTick();
        await Promise.resolve();

        expect(wrapper.text()).toContain("Lisensi");
        expect(wrapper.text()).toContain("Kesehatan Sistem");
        expect(wrapper.text()).toContain("Akun Admin");
    });

    it("shows verify button disabled when license key is empty", async () => {
        const wrapper = factory();
        await nextTick();
        await Promise.resolve();

        const button = wrapper.find("button");
        expect(button.attributes("disabled")).toBeDefined();
    });

    it("stores the bootstrap session and shows setup completion after creating the admin", async () => {
        hasLicense.value = true;
        doctorResult.value = { healthy: true };
        const wrapper = factory();
        await flushPromises();

        await wrapper.findAll("button").find((button) => button.text().includes("Lanjut")).trigger("click");
        await wrapper.get('input[type="text"]').setValue("Setup Admin");
        await wrapper.get('input[type="email"]').setValue("admin@example.test");
        const passwords = wrapper.findAll('input[type="password"]');
        await passwords[0].setValue("setup-password");
        await passwords[1].setValue("setup-password");
        await wrapper.get("form").trigger("submit");
        await flushPromises();

        expect(bootstrap).toHaveBeenCalledWith("Setup Admin", "admin@example.test", "setup-password", "setup-password");
        expect(setToken).toHaveBeenCalledWith("fake-token");
        expect(wrapper.text()).toContain("Server Anda sudah menyala!");
        expect(wrapper.find("form").exists()).toBe(false);
        await wrapper.get("button").trigger("click");
        expect(replace).toHaveBeenCalledWith({ name: "login" });
    });

    it("redirects to login when setup is not needed", async () => {
        needsSetup.value = false;
        const wrapper = factory();
        await nextTick();
        await Promise.resolve();

        expect(replace).toHaveBeenCalledWith({ name: "login" });
    });
});
