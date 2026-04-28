import { expect, test } from "@playwright/test";
import { loginAsRole } from "./helpers/auth";

const getTomorrow = () => {
    const date = new Date();
    date.setDate(date.getDate() + 1);
    return date.toISOString().slice(0, 10);
};

const getDayAfterTomorrow = () => {
    const date = new Date();
    date.setDate(date.getDate() + 2);
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

        const tomorrow = getTomorrow();
        const dayAfterTomorrow = getDayAfterTomorrow();

        await page.locator('input[type="date"]').nth(0).fill(tomorrow);
        await page.locator('input[type="date"]').nth(1).fill(dayAfterTomorrow);
        await page
            .locator('textarea[placeholder*="detailed reason for your leave request"]')
            .fill(`E2E leave request ${Date.now()}`);

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

        const listOrEmptyState = page
            .locator("text=No Requests Found, table tbody tr")
            .first();
        await expect(listOrEmptyState).toBeVisible();

        await expect(
            page.locator("button[title='Approve Leave'], button[title='Reject Leave']").first()
        ).toBeVisible({ timeout: 20_000 });

        await context.close();
    });
});
