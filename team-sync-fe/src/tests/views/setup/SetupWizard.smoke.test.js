import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick, ref } from "vue";

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

const toastSuccess = vi.fn();
const toastError = vi.fn();

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: toastSuccess,
        error: toastError,
    }),
}));

vi.mock("js-cookie", () => ({
    default: {
        set: vi.fn(),
        get: vi.fn(),
        remove: vi.fn(),
    },
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

    it("redirects to login when setup is not needed", async () => {
        needsSetup.value = false;
        const wrapper = factory();
        await nextTick();
        await Promise.resolve();

        expect(replace).toHaveBeenCalledWith({ name: "login" });
    });
});
