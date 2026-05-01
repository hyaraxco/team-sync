import { expect, test } from "@playwright/test";
import { loginAsRole } from "./helpers/auth";

/**
 * Get the next available weekday (Mon-Fri) in the next month.
 * Next month's attendance period is always "open", so leave requests are accepted.
 */
const getNextMonthWeekday = (offset = 0) => {
    const date = new Date();
    date.setMonth(date.getMonth() + 1);
    date.setDate(10 + offset); // Start from 10th to avoid edge cases
    // Ensure it's a weekday
    while (date.getDay() === 0 || date.getDay() === 6) {
        date.setDate(date.getDate() + 1);
    }
    return date.toISOString().slice(0, 10);
};

test.describe.serial("Attendance flow", () => {
    test.setTimeout(120_000);

    test("employee can view My Attendance page", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        await loginAsRole(page, "employee");
        await page.goto("/admin/attendance/my-attendances");
        await expect(page).toHaveURL(/\/admin\/attendance\/my-attendances$/);

        await expect(page.getByRole("heading", { name: "Attendance Overview" })).toBeVisible();

        await expect(page.getByRole("heading", { name: "Recent Attendance" })).toBeVisible();
        await expect(page.getByRole("button", { name: "Request Leave" }).first()).toBeVisible();

        const clockSection = page.getByText(/Auto-present · Remote|Clock In|Clock Out/).first();
        await expect(clockSection).toBeVisible();

        await context.close();
    });

    test("employee can submit a leave request", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        await loginAsRole(page, "employee");
        await page.goto("/admin/attendance/my-attendances");
        await expect(page).toHaveURL(/\/admin\/attendance\/my-attendances$/);

        await page.getByRole("button", { name: "Request Leave" }).first().click();
        await expect(page.getByRole("heading", { name: "Request New Leave" })).toBeVisible();

        const leaveTypeSelect = page.locator("select").filter({ has: page.locator("option[value='']") }).first();
        await leaveTypeSelect.selectOption({ index: 1 });

        const startDate = getNextMonthWeekday(0);
        const endDate = getNextMonthWeekday(1);

        await page.getByTestId('leave-start-date').fill(startDate);
        await page.getByTestId('leave-end-date').fill(endDate);
        await page.getByTestId('leave-reason').fill(`E2E leave request ${Date.now()}`);

        await page.getByRole("button", { name: "Submit Request" }).click();

        await expect(page.getByRole("heading", { name: "Request Submitted!" })).toBeVisible({
            timeout: 20_000,
        });
        await expect(page.getByText("Your leave request has been successfully submitted")).toBeVisible();

        await page.getByRole("button", { name: "Got it!" }).click();
        await expect(page.getByRole("heading", { name: "Request Submitted!" })).toHaveCount(0);

        await context.close();
    });

    test("hr can view leave requests and pending actions", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        await loginAsRole(page, "hr");
        await page.goto("/admin/leave-requests");
        await expect(page).toHaveURL(/\/admin\/leave-requests$/);

        await expect(page.getByRole("heading", { name: "Leave Requests" })).toBeVisible();

        const hasTable = await page.locator("table tbody tr").first().isVisible({ timeout: 10_000 }).catch(() => false);
        const hasEmptyState = await page.getByText("No Requests Found").isVisible({ timeout: 5_000 }).catch(() => false);
        expect(hasTable || hasEmptyState).toBeTruthy();

        await context.close();
    });
});
