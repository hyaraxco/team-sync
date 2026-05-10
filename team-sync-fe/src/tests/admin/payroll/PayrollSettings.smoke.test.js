import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick, ref } from "vue";

const loading = ref(false);
const settings = ref({
    payday_day: 25,
    attendance_cutoff_day: 25,
    working_days_mode: "auto_business_days",
    default_working_days: 22,
    absent_deduction_rate: 1,
    rounding_mode: "nearest",
    rounding_unit: 1000,
    note_template: "Hari kerja: {working_days}",
    updated_at: "2026-04-07T12:00:00.000Z",
    updated_by: {
        name: "Dwimeta",
    },
    active_version: {
        id: 9,
        version_number: 3,
    },
});

const settingsHistory = [
    {
        id: 9,
        version_number: 3,
        effective_at: "2026-04-10T10:00:00.000Z",
        payday_day: 25,
        attendance_cutoff_day: 25,
        working_days_mode: "auto_business_days",
        default_working_days: 22,
        absent_deduction_rate: 1,
        rounding_mode: "nearest",
        rounding_unit: 1000,
        note_template: "Hari kerja: {working_days}",
        updated_by: {
            name: "Dwimeta",
        },
    },
    {
        id: 7,
        version_number: 2,
        effective_at: "2026-04-09T10:00:00.000Z",
        payday_day: 24,
        attendance_cutoff_day: 24,
        working_days_mode: "fixed",
        default_working_days: 20,
        absent_deduction_rate: 1.5,
        rounding_mode: "floor",
        rounding_unit: 500,
        note_template: "Alpha {absent_days} | Potongan Rp {deduction}",
        updated_by: {
            name: "Finance A",
        },
    },
];

const fetchSettings = vi.fn().mockResolvedValue(settings.value);
const fetchSettingsHistory = vi.fn().mockResolvedValue(settingsHistory);
const fetchBpjsRateHistory = vi.fn().mockResolvedValue([]);
const updateSettings = vi.fn().mockResolvedValue(settings.value);
const push = vi.fn();
const toastSuccess = vi.fn();
const toastError = vi.fn();

vi.mock("@/stores/payroll", () => ({
    usePayrollStore: () => ({
        fetchSettings,
        fetchSettingsHistory,
        fetchBpjsRateHistory,
        updateSettings,
        error: null,
    }),
}));

vi.mock("pinia", async (importOriginal) => {
    const actual = await importOriginal();

    return {
        ...actual,
        storeToRefs: () => ({
            loading,
            settings,
        }),
    };
});

vi.mock("vue-router", () => ({
    useRouter: () => ({
        push,
    }),
}));

vi.mock("@/composables/useToast", () => ({
    useToast: () => ({
        success: toastSuccess,
        error: toastError,
    }),
}));

import PayrollSettings from "@/views/admin/payroll/PayrollSettings.vue";

const factory = () => mount(PayrollSettings);

const flushAsync = async () => {
    await nextTick();
    await Promise.resolve();
    await nextTick();
};

describe("PayrollSettings smoke", () => {
<<<<<<< Updated upstream
    beforeEach(() => {
        loading.value = false;
        settings.value = {
            payday_day: 25,
            attendance_cutoff_day: 25,
            working_days_mode: "auto_business_days",
            default_working_days: 22,
            absent_deduction_rate: 1,
            rounding_mode: "nearest",
            rounding_unit: 1000,
            note_template: "Hari kerja: {working_days}",
            updated_at: "2026-04-07T12:00:00.000Z",
            updated_by: {
                name: "Dwimeta",
            },
            active_version: {
                id: 9,
                version_number: 3,
            },
        };
        fetchSettings.mockClear();
        fetchSettingsHistory.mockClear();
        fetchBpjsRateHistory.mockClear();
        updateSettings.mockClear();
        push.mockClear();
        toastSuccess.mockClear();
        toastError.mockClear();
    });
=======
  beforeEach(() => {
    loading.value = false;
    settings.value = {
      payday_day: 25,
      attendance_cutoff_day: 25,
      working_days_mode: "auto_business_days",
      default_working_days: 22,
      absent_deduction_rate: 1,
      rounding_mode: "nearest",
      rounding_unit: 1000,
      note_template: "Hari kerja: {working_days}",
      updated_at: "2026-04-07T12:00:00.000Z",
      updated_by: {
        name: "Dwimeta",
      },
      active_version: {
        id: 9,
        version_number: 3,
      },
    };
    fetchSettings.mockClear();
    fetchSettingsHistory.mockClear();
    updateSettings.mockClear();
    push.mockClear();
    toastSuccess.mockClear();
    toastError.mockClear();
  });

  it("fetches payroll settings on mount", async () => {
    const wrapper = factory();
    await flushAsync();

    expect(fetchSettings).toHaveBeenCalledTimes(1);
    expect(fetchSettingsHistory).toHaveBeenCalledTimes(1);
    expect(wrapper.text()).toContain("Payroll Settings");
    expect(wrapper.text()).toContain("Updated by Dwimeta");
    expect(wrapper.text()).toContain("Active v3");
    expect(wrapper.find('[data-testid="payroll-settings-history-list"]').exists()).toBe(true);
  });

  it("submits updated payroll settings payload", async () => {
    const wrapper = factory();
    await flushAsync();

    await wrapper.get('[data-testid="payroll-settings-payday-day"]').setValue("27");
    await wrapper.get('[data-testid="payroll-settings-cutoff-day"]').setValue("24");
    await wrapper.get('[data-testid="payroll-settings-working-days-mode"]').setValue("fixed");
    await wrapper.get('[data-testid="payroll-settings-default-working-days"]').setValue("20");
    await wrapper.get('[data-testid="payroll-settings-absent-deduction-rate"]').setValue("1.5");
    await wrapper.get('[data-testid="payroll-settings-rounding-mode"]').setValue("floor");
    await wrapper.get('[data-testid="payroll-settings-rounding-unit"]').setValue("500");
    await wrapper.get('[data-testid="payroll-settings-note-template"]').setValue(
      "Alpha {absent_days} | Potongan Rp {deduction}"
    );
    await wrapper.get('[data-testid="payroll-settings-save"]').trigger("click");
    await flushAsync();

    expect(updateSettings).toHaveBeenCalledWith(expect.objectContaining({
      payday_day: 27,
      attendance_cutoff_day: 24,
      working_days_mode: "fixed",
      default_working_days: 20,
      absent_deduction_rate: 1.5,
      rounding_mode: "floor",
      rounding_unit: 500,
      note_template: "Alpha {absent_days} | Potongan Rp {deduction}",
    }));
    expect(toastSuccess).toHaveBeenCalled();
  });
>>>>>>> Stashed changes

    it("fetches payroll settings on mount", async () => {
        const wrapper = factory();
        await flushAsync();

        expect(fetchSettings).toHaveBeenCalledTimes(1);
        expect(fetchSettingsHistory).toHaveBeenCalledTimes(1);
        expect(fetchBpjsRateHistory).toHaveBeenCalledTimes(1);
        expect(wrapper.text()).toContain("Payroll Settings");
        expect(wrapper.text()).toContain("Updated by Dwimeta");
        expect(wrapper.text()).toContain("Active v3");
        expect(wrapper.find('[data-testid="payroll-settings-history-list"]').exists()).toBe(true);
    });

    it("submits updated payroll settings payload", async () => {
        const wrapper = factory();
        await flushAsync();

        await wrapper.get('[data-testid="payroll-settings-payday-day"]').setValue("27");
        await wrapper.get('[data-testid="payroll-settings-cutoff-day"]').setValue("24");
        await wrapper.get('[data-testid="payroll-settings-working-days-mode"]').setValue("fixed");
        await wrapper.get('[data-testid="payroll-settings-default-working-days"]').setValue("20");
        await wrapper.get('[data-testid="payroll-settings-absent-deduction-rate"]').setValue("1.5");
        await wrapper.get('[data-testid="payroll-settings-rounding-mode"]').setValue("floor");
        await wrapper.get('[data-testid="payroll-settings-rounding-unit"]').setValue("500");
        await wrapper
            .get('[data-testid="payroll-settings-note-template"]')
            .setValue("Alpha {absent_days} | Potongan Rp {deduction}");
        await wrapper.get('[data-testid="payroll-settings-save"]').trigger("click");
        await flushAsync();

        expect(updateSettings).toHaveBeenCalledWith(
            expect.objectContaining({
                payday_day: 27,
                attendance_cutoff_day: 24,
                working_days_mode: "fixed",
                default_working_days: 20,
                absent_deduction_rate: 1.5,
                rounding_mode: "floor",
                rounding_unit: 500,
                note_template: "Alpha {absent_days} | Potongan Rp {deduction}",
                payroll_bank_name: null,
                payroll_bank_code: null,
            }),
        );
        expect(toastSuccess).toHaveBeenCalled();
    });

    it("shows version comparison details for selected settings history entry", async () => {
        const wrapper = factory();
        await flushAsync();

        expect(wrapper.find('[data-testid="payroll-settings-history-compare-panel"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="payroll-settings-history-compare-row"]').length).toBeGreaterThan(0);

        await wrapper.get('[data-testid="payroll-settings-history-compare-select-7"]').trigger("click");

        await flushAsync();

        expect(wrapper.find('[data-testid="payroll-settings-history-compare-empty"]').exists()).toBe(true);
    });
});
