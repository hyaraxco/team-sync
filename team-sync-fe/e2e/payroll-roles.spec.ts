import { expect, test } from "@playwright/test";
import { loginAsRole } from "./helpers/auth";
import { processQueueOnce } from "./helpers/backend";
import { captureEvidence } from "./helpers/evidence";

let payrollIdForJourney: string | null = null;

const todayDate = () => new Date().toISOString().slice(0, 10);

const thisMonth = () => {
  const date = new Date();
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  return `${year}-${month}`;
};

const previousMonth = () => {
  const date = new Date();
  date.setMonth(date.getMonth() - 1);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  return `${year}-${month}`;
};

const extractPayrollIdFromPendingRow = async (page: Parameters<typeof captureEvidence>[0]) => {
  const pendingRow = page.locator('[data-testid^="payroll-row-"]').filter({
    hasText: /pending/i,
  });
  const pendingRowCount = await pendingRow.count();

  if (pendingRowCount === 0) {
    throw new Error(
      "No pending payroll row found for E2E finance journey. Run `bun run e2e:prepare:be` and rerun."
    );
  }

  const pendingDetailButton = pendingRow
    .first()
    .locator('[data-testid^="payroll-detail-btn-"]')
    .first();
  await expect(pendingDetailButton).toBeVisible({ timeout: 15_000 });
  const testId = await pendingDetailButton.getAttribute("data-testid");

  if (!testId) {
    throw new Error("Failed to resolve payroll id from pending payroll detail button.");
  }

  return testId.replace("payroll-detail-btn-", "");
};

const expectRedirectToDashboard = async (page: Parameters<typeof captureEvidence>[0], path: string) => {
  await page.goto(path);
  await expect(page).toHaveURL(/\/admin\/dashboard$/);
};

test.describe.serial("Payroll role journey (Bun + Docker BE)", () => {
  test.setTimeout(240_000);

  test("manager is denied payroll admin access", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "manager");
    await expect(page.getByTestId("sidebar-section-personal")).toBeVisible();
    await expect(page.getByRole("link", { name: "My Profile" })).toBeVisible();
    await expect(page.getByRole("link", { name: "My Attendance" })).toBeVisible();
    await expect(page.getByRole("link", { name: "My Payroll" })).toBeVisible();
    await page.goto("/admin/payroll");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);
    await expect(page.getByRole("link", { name: "Payroll", exact: true })).toHaveCount(0);
    await expect(page.getByText("Process Payroll")).toHaveCount(0);
    await expect(page.getByText("Request Leave")).toBeVisible();
    await page.goto("/admin/my-profile");
    await expect(page).toHaveURL(/\/admin\/my-profile$/);
    await page.goto("/admin/attendance/my-attendances");
    await expect(page).toHaveURL(/\/admin\/attendance\/my-attendances$/);
    await page.goto("/admin/my-payroll");
    await expect(page).toHaveURL(/\/admin\/my-payroll$/);
    await expect(
      page.locator("main").getByRole("heading", { name: "My Payroll" })
    ).toBeVisible();

    await captureEvidence(page, "manager-deny-payroll.png");
    await context.close();
  });

  test("hr creates payroll draft for current month (pending)", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "hr");
    await expect(page.getByTestId("sidebar-section-personal")).toBeVisible();
    await expect(page.getByText("Request Leave")).toBeVisible();
    await page.goto("/admin/attendance/my-attendances");
    await expect(page).toHaveURL(/\/admin\/attendance\/my-attendances$/);
    await page.goto("/admin/payroll/create");
    await expect(page).toHaveURL(/\/admin\/payroll\/create$/);
    await expect(page.getByTestId("payroll-create-month")).toHaveAttribute(
      "max",
      thisMonth()
    );

    const monthInput = page.getByTestId("payroll-create-month");
    const submitButton = page.getByTestId("payroll-create-submit");

    const canGenerateForMonth = async (month: string) => {
      await monthInput.fill(month);
      try {
        await expect
          .poll(async () => submitButton.isEnabled(), {
            timeout: 6_000,
            intervals: [250, 500, 1_000],
          })
          .toBe(true);
        return true;
      } catch {
        return false;
      }
    };

    const canGenerateCurrentMonth = await canGenerateForMonth(thisMonth());
    const canGeneratePreviousMonth = canGenerateCurrentMonth
      ? false
      : await canGenerateForMonth(previousMonth());

    if (canGenerateCurrentMonth || canGeneratePreviousMonth) {
      await submitButton.click();
      await expect(page).toHaveURL(/\/admin\/payroll$/);
      await expect(
        page.locator("text=/processed in the background|being processed/i").first()
      ).toBeVisible({
        timeout: 15_000,
      });
      processQueueOnce();
      await page.reload();
    } else {
      await page.goto("/admin/payroll");
    }

    await expect(page.locator('[data-testid^="payroll-row-"]').first()).toBeVisible({
      timeout: 15_000,
    });

    payrollIdForJourney = await extractPayrollIdFromPendingRow(page);

    await page.goto(`/admin/payroll/${payrollIdForJourney}`);
    await expect(page).toHaveURL(
      new RegExp(`/admin/payroll/${payrollIdForJourney}$`)
    );
    await expect(page.getByText("Payroll Draft Review")).toBeVisible();
    await expect(page.getByText("Mark as Paid")).toHaveCount(0);
    await expect(page.getByText("Total Employees")).toHaveCount(0);

    await page.goto("/admin/payroll/create");
    await expect(page).toHaveURL(/\/admin\/payroll\/create$/);
    await monthInput.fill(thisMonth());
    const resolvedMonth = await monthInput.inputValue();

    if (resolvedMonth === thisMonth()) {
      await expect(submitButton).toBeDisabled();
    } else {
      expect(resolvedMonth).not.toBe(thisMonth());
      await expect(submitButton).toBeEnabled();
    }

    await captureEvidence(page, "hr-pending-created.png");
    await context.close();
  });

  test("finance reviews, approves, then marks payroll as paid", async ({
    browser,
  }) => {
    if (!payrollIdForJourney) {
      throw new Error("Payroll id is missing from HR step.");
    }

    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "finance");
    await expect(page.getByTestId("sidebar-section-personal")).toBeVisible();
    await expect(page.getByText("Request Leave")).toBeVisible();

    await page.goto("/admin/payroll/create");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);
    await expectRedirectToDashboard(page, "/admin/teams");
    await expectRedirectToDashboard(page, "/admin/projects");
    await page.goto("/admin/my-payroll");
    await expect(page).toHaveURL(/\/admin\/my-payroll$/);
    await expect(
      page.locator("main").getByRole("heading", { name: "My Payroll" })
    ).toBeVisible();

    await page.goto("/admin/payroll");
    await page.getByTestId("payroll-settings-link").click();
    await expect(page).toHaveURL(/\/admin\/payroll\/settings$/);
    await expect(page.getByText("Payroll Settings")).toBeVisible();
    await page.getByTestId("payroll-settings-back").click();
    await expect(page).toHaveURL(/\/admin\/payroll$/);

    await page.goto(`/admin/payroll/${payrollIdForJourney}`);
    await expect(page).toHaveURL(
      new RegExp(`/admin/payroll/${payrollIdForJourney}$`)
    );
    await expect(page.getByTestId("payroll-export-excel")).toBeVisible();
    await expect(page.getByTestId("payroll-approve")).toBeVisible();
    await expect(page.getByTestId("payroll-mark-as-paid")).toHaveCount(0);
    await expect(page.getByText("Total Employees")).toBeVisible();
    await expect(
      page.getByRole("heading", { name: "Payroll Activity" })
    ).toBeVisible();

    await page.goto("/admin/payroll");
    await page.getByTestId("payroll-export-report-open").click();
    await page.getByTestId("payroll-report-type").selectOption("detail");
    await page.getByTestId("payroll-report-status").selectOption("pending");
    const reportDownloadPromise = page.waitForEvent("download");
    await page.getByTestId("payroll-report-submit").click();
    const reportDownload = await reportDownloadPromise;
    expect(reportDownload.suggestedFilename()).toContain("Detail");

    const exportPromise = page.waitForEvent("download");
    await page.goto(`/admin/payroll/${payrollIdForJourney}`);
    await page.getByTestId("payroll-export-excel").click();
    const exportDownload = await exportPromise;
    expect(exportDownload.suggestedFilename().toLowerCase()).toContain("payroll");

    await page.getByTestId("payroll-approve").click();
    await page.getByTestId("payroll-confirm-approve").click();
    await expect(
      page
        .getByText("Review is complete. Finance can now mark this payroll as paid.")
    ).toBeVisible({ timeout: 20_000 });
    await expect(page.getByTestId("payroll-mark-as-paid")).toBeVisible({
      timeout: 20_000,
    });

    await page.getByTestId("payroll-mark-as-paid").click();
    await page.getByTestId("payroll-payment-date").fill(todayDate());
    await page.getByTestId("payroll-confirm-mark-as-paid").click();

    await expect(page.getByTestId("payroll-mark-as-paid")).toHaveCount(0, {
      timeout: 20_000,
    });
    await expect(page.getByTestId("payroll-auto-notification-info")).toBeVisible();
    await expect(page.getByTestId("payroll-resend-notifications")).toBeVisible();
    await page.getByTestId("payroll-resend-notifications").click();
    await page.getByTestId("payroll-confirm-resend-notifications").click();
    await expect(
      page
        .getByTestId("payroll-activity-list")
        .getByText("Payroll approved for payment")
    ).toBeVisible({ timeout: 15_000 });
    await expect(
      page
        .getByTestId("payroll-activity-list")
        .getByText("Payroll marked as paid")
    ).toBeVisible({ timeout: 15_000 });
    await expect(
      page
        .getByTestId("payroll-activity-list")
        .getByText("Payroll notifications resent")
    ).toBeVisible({ timeout: 15_000 });

    await page.goto("/admin/payroll");
    await expect(page.locator("text=/paid/i").first()).toBeVisible({
      timeout: 15_000,
    });

    await captureEvidence(page, "finance-paid.png");
    await context.close();
  });

  test("employee accesses My Payroll and is denied payroll admin routes", async ({
    browser,
  }) => {
    const context = await browser.newContext({
      acceptDownloads: true,
    });
    const page = await context.newPage();

    await loginAsRole(page, "employee");
    await expect(page.getByTestId("sidebar-section-personal")).toBeVisible();

    await page.goto("/admin/my-payroll");
    await expect(page).toHaveURL(/\/admin\/my-payroll$/);
    await expect(page.locator('[data-testid^="my-payroll-card-"]').first()).toBeVisible({
      timeout: 15_000,
    });

    await page.locator('[data-testid^="my-payroll-view-"]').first().click();
    await expect(page).toHaveURL(/\/admin\/my-payroll\/\d+$/);
    await expect(page.locator("text=NET SALARY")).toBeVisible();

    const downloadPromise = page.waitForEvent("download");
    await page.getByTestId("payslip-detail-download").click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toContain("payslip");

    await expectRedirectToDashboard(page, "/admin/payroll");
    await expectRedirectToDashboard(page, "/admin/payroll/create");
    await expectRedirectToDashboard(page, "/admin/attendances");
    await expectRedirectToDashboard(page, "/admin/employees");
    await expectRedirectToDashboard(page, "/admin/teams");

    await captureEvidence(page, "employee-my-payroll.png");
    await context.close();
  });
});
