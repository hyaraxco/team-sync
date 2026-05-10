import { test, expect } from "./support/fixtures";
import { loginAsRole } from "./helpers/auth";

const toDateInputValue = (date: Date) => date.toISOString().slice(0, 10);

/**
 * Pick a weekday range 3-6 months in the future to avoid overlaps
 * with previous test runs or seeded data.
 */
const getNextMonthWeekdayRange = () => {
    const start = new Date();
    // Use 3-6 months in the future based on current timestamp to ensure uniqueness
    const monthsAhead = 3 + (Math.floor(Date.now() / 1000) % 4);
    start.setMonth(start.getMonth() + monthsAhead, 1);

    // Use a rotating day within the month
    const rotatingOffset = Math.floor((Date.now() / 1_000) % 20);
    start.setDate(5 + rotatingOffset);

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

        // Capture API responses for debugging
        const apiResponses: Array<{ url: string; status: number; body?: string }> = [];
        page.on("response", async (response) => {
            if (response.url().includes("/api/v1/")) {
                try {
                    const body = await response.text().catch(() => "<unreadable>");
                    apiResponses.push({
                        url: response.url(),
                        status: response.status(),
                        body: body.slice(0, 500),
                    });
                } catch {
                    // ignore
                }
            }
        });

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

        // Wait for either success modal or error toast
        const successModal = page.getByText("Request Submitted!");
        const errorToast = page.locator('[class*="toast"], [class*="error"], [role="alert"]').first();

        try {
            await expect(successModal).toBeVisible({ timeout: 15_000 });
        } catch {
            // If success modal doesn't appear, capture debugging info
            const toastText = await errorToast.textContent().catch(() => "no toast found");
            const lastApiCalls = apiResponses.slice(-10);
            const failedCalls = lastApiCalls.filter(r => r.status >= 400);
            console.log("Leave request failed. Failed API calls:", JSON.stringify(failedCalls, null, 2));
            console.log("Toast message:", toastText);
            throw new Error(
                `Leave request submission failed. ` +
                `Failed APIs: ${failedCalls.map(r => `${r.status} ${r.url}: ${r.body}`).join("; ")}. ` +
                `Toast: ${toastText}`
            );
        }

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
