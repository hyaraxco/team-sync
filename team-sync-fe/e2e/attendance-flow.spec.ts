import { test, expect } from "./support/fixtures";
import { loginAsRole } from "./helpers/auth";

const toDateInputValue = (date: Date) => date.toISOString().slice(0, 10);

/**
 * Pick a weekday range in next month with a rotating start day
 * to avoid overlaps between repeated test executions.
 */
const getNextMonthWeekdayRange = () => {
    const start = new Date();
    start.setMonth(start.getMonth() + 1, 1);

    const rotatingOffset = Math.floor((Date.now() / 1_000) % 10);
    start.setDate(10 + rotatingOffset);

    while (start.getDay() === 0 || start.getDay() === 6) {
        start.setDate(start.getDate() + 1);
    }

    const end = new Date(start);
    end.setDate(end.getDate() + 1);

    while (end.getDay() === 0 || end.getDay() === 6) {
        end.setDate(end.getDate() + 1);
    }

    return {
        startDate: toDateInputValue(start),
        endDate: toDateInputValue(end),
    };
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

        // Wait for page to fully load
        await expect(page.getByRole("heading", { name: "Attendance Overview" })).toBeVisible();

        // Click Request Leave button (wait for it to be visible)
        const requestLeaveBtn = page.getByRole("button", { name: "Request Leave" }).first();
        await expect(requestLeaveBtn).toBeVisible({ timeout: 10_000 });
        await requestLeaveBtn.click();

        // Wait for modal to appear (it's Teleported to body)
        await expect(page.getByText("Request New Leave")).toBeVisible({ timeout: 10_000 });

        // Wait for leave types to load from API
        const leaveTypeSelect = page.locator("select").filter({ has: page.locator("option[value='']") }).first();
        await expect(leaveTypeSelect).toBeVisible({ timeout: 5_000 });

        // Wait for options to be populated (option elements are hidden inside select, use count check)
        await page.waitForFunction(() => {
            const select = document.querySelector('select');
            return select && select.options.length > 1;
        }, { timeout: 10_000 });
        await leaveTypeSelect.selectOption({ index: 1 });

        const { startDate, endDate } = getNextMonthWeekdayRange();

        await page.getByTestId('leave-start-date').fill(startDate);
        await page.getByTestId('leave-end-date').fill(endDate);
        await page.getByTestId('leave-reason').fill(`E2E leave request ${Date.now()}`);

        await page.getByRole("button", { name: "Submit Request" }).click();

        await expect(page.getByText("Request Submitted!")).toBeVisible({
            timeout: 20_000,
        });
        await expect(page.getByText("Your leave request has been successfully submitted")).toBeVisible();

        await page.getByRole("button", { name: "Got it!" }).click();
        await expect(page.getByText("Request Submitted!")).toHaveCount(0);

        await context.close();
    });

    test("hr can view leave requests and pending actions", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        await loginAsRole(page, "hr");
        await page.goto("/admin/leave-requests");
        await expect(page).toHaveURL(/\/admin\/leave-requests$/);

        await expect(page.getByRole("heading", { name: "Leave Requests" })).toBeVisible();

        const hasTable = await page.locator("table tbody tr").first().isVisible({ timeout: 10_000 });
        const hasEmptyState = await page.getByText("No Requests Found").first().isVisible({ timeout: 5_000 });
        expect(hasTable || hasEmptyState).toBeTruthy();

        await context.close();
    });
});
