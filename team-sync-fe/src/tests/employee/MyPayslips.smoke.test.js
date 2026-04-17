import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick, ref } from "vue";

const payslips = ref([]);
const meta = ref({
  current_page: 1,
  last_page: 1,
  per_page: 12,
  total: 0,
  from: 0,
  to: 0,
});
const loading = ref(false);

const fetchMyPayslips = vi.fn().mockResolvedValue(undefined);
const downloadPayslip = vi.fn().mockResolvedValue(
  new Blob(["pdf"], { type: "application/pdf" })
);
const push = vi.fn();

vi.mock("@/stores/payroll", () => ({
  usePayrollStore: () => ({
    fetchMyPayslips,
    downloadPayslip,
  }),
}));

vi.mock("pinia", async (importOriginal) => {
  const actual = await importOriginal();

  return {
    ...actual,
    storeToRefs: () => ({
      payslips,
      meta,
      loading,
    }),
  };
});

vi.mock("vue-router", () => ({
  useRouter: () => ({
    push,
  }),
}));

import MyPayslips from "@/views/employee/MyPayslips.vue";

const PaginationStub = {
  name: "Pagination",
  props: ["meta", "loading"],
  template: '<div class="pagination-stub"></div>',
};

const factory = () =>
  mount(MyPayslips, {
    global: {
      stubs: {
        Pagination: PaginationStub,
        AnimatedValue: {
          props: ["value"],
          template: "<span>{{ value }}</span>",
        },
      },
    },
  });

const flushAsync = async () => {
  await nextTick();
  await Promise.resolve();
  await nextTick();
};

const findButtonByText = (wrapper, text) =>
  wrapper.findAll("button").find((button) => button.text().includes(text));

describe("MyPayslips smoke", () => {
  beforeEach(() => {
    payslips.value = [];
    meta.value = {
      current_page: 1,
      last_page: 1,
      per_page: 12,
      total: 0,
      from: 0,
      to: 0,
    };
    loading.value = false;

    fetchMyPayslips.mockClear();
    downloadPayslip.mockClear();
    push.mockClear();

    window.URL.createObjectURL = vi.fn(() => "blob:mock-url");
    window.URL.revokeObjectURL = vi.fn();
    vi.spyOn(HTMLAnchorElement.prototype, "click").mockImplementation(() => {});
  });

  it("fetches payslip list on mount with default employee filters", async () => {
    const currentYear = new Date().getFullYear();
    factory();
    await flushAsync();

    expect(fetchMyPayslips).toHaveBeenCalledWith({
      page: 1,
      row_per_page: 12,
      search: null,
      year: currentYear,
    });
  });

  it("renders payroll cards and navigates to detail when view is clicked", async () => {
    payslips.value = [
      {
        id: 11,
        period: "2026-04-01",
        payment_date: "2026-04-30",
        net_salary: 9800000,
        gross_salary: 11000000,
        total_deductions: 1200000,
      },
    ];
    meta.value = {
      current_page: 1,
      last_page: 1,
      per_page: 12,
      total: 1,
      from: 1,
      to: 1,
    };

    const wrapper = factory();
    await flushAsync();

    expect(wrapper.text()).toContain("My Payroll");
    expect(wrapper.text()).toContain("April 2026");
    expect(wrapper.get('[data-testid="my-payroll-highlight"]').text()).toContain(
      "Latest paid payroll"
    );
    expect(wrapper.text()).toContain("Average Net Salary");

    const viewButton = findButtonByText(wrapper, "View");
    expect(viewButton).toBeTruthy();
    await viewButton.trigger("click");

    expect(push).toHaveBeenCalledWith({
      name: "employee.payroll.detail",
      params: { id: 11 },
    });
  });

  it("triggers payslip PDF download when PDF button is clicked", async () => {
    payslips.value = [
      {
        id: 15,
        period: "2026-04-01",
        payment_date: "2026-04-30",
        net_salary: 9200000,
        gross_salary: 10500000,
        total_deductions: 1300000,
      },
    ];
    const wrapper = factory();
    await flushAsync();

    const pdfButton = findButtonByText(wrapper, "PDF");
    expect(pdfButton).toBeTruthy();
    await pdfButton.trigger("click");
    await flushAsync();

    expect(downloadPayslip).toHaveBeenCalledWith(15);
    expect(window.URL.createObjectURL).toHaveBeenCalled();
    expect(window.URL.revokeObjectURL).toHaveBeenCalledWith("blob:mock-url");
  });

  it("shows empty state when employee has no paid payroll history", async () => {
    payslips.value = [];
    meta.value = {
      current_page: 1,
      last_page: 1,
      per_page: 12,
      total: 0,
      from: 0,
      to: 0,
    };

    const wrapper = factory();
    await flushAsync();

    expect(wrapper.text()).toContain("No payroll history found");
    expect(wrapper.get('[data-testid="my-payroll-empty"]').text()).toContain(
      String(new Date().getFullYear())
    );
  });

  it("lets employee clear an active payroll search", async () => {
    const wrapper = factory();
    await flushAsync();

    const searchInput = wrapper.get('input[type="text"]');
    await searchInput.setValue("April");
    await flushAsync();

    expect(wrapper.get('[data-testid="my-payroll-clear-search"]').text()).toContain(
      "Clear"
    );

    await wrapper.get('[data-testid="my-payroll-clear-search"]').trigger("click");
    await flushAsync();

    expect(searchInput.element.value).toBe("");
  });
});
