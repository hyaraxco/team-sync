import { test, expect } from "./support/fixtures";
import { loginAsRole } from "./helpers/auth";

test.describe.serial("Schedule meeting flow", () => {
    test.setTimeout(120_000);

    test("hr can see Schedule Meeting button and navigate to meetings page", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        await loginAsRole(page, "hr");
        await expect(page).toHaveURL(/\/admin\/dashboard$/);

        const scheduleButton = page.locator('[data-action-id="schedule-meeting"]');
        await expect(scheduleButton).toBeVisible();
        await expect(scheduleButton).toHaveText(/Schedule Meeting/);

        await page.goto("/admin/meetings");
        await expect(page).toHaveURL(/\/admin\/meetings$/);
        await expect(page.getByRole("heading", { name: "Meetings" })).toBeVisible();

        await context.close();
    });

    test("employee cannot see Schedule Meeting button but can access meetings page", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        await loginAsRole(page, "employee");
        await expect(page).toHaveURL(/\/admin\/dashboard$/);

        const scheduleButton = page.locator('[data-action-id="schedule-meeting"]');
        await expect(scheduleButton).toHaveCount(0);

        await context.close();
    });
});
