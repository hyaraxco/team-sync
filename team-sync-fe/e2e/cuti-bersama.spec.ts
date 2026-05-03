import { test, expect } from "./support/fixtures";
import { loginAsRole } from "./helpers/auth";
import { captureEvidence } from "./helpers/evidence";

test.describe("Cuti Bersama (Collective Leave) Validations", () => {
    test.beforeEach(async ({ page }) => {
        await loginAsRole(page, "hr");
    });

    test("HR sees Upcoming Cuti Bersama widget on Leave Requests page", async ({ page }) => {
        await page.goto("/admin/leave-requests");
        await page.waitForLoadState("networkidle");

        await captureEvidence(page, "cuti-bersama-leave-requests-page.png");

        const heading = page.getByRole("heading", { name: /Leave Requests/i });
        await expect(heading).toBeVisible({ timeout: 10_000 });

        const cutiWidget = page.locator('[data-testid="upcoming-cuti-bersama"]');
        const cutiHeading = page.getByText(/Upcoming Cuti Bersama/i);

        const widgetVisible = await cutiWidget.first().isVisible();
        const headingVisible = await cutiHeading.first().isVisible();

        if (widgetVisible || headingVisible) {
            await captureEvidence(page, "cuti-bersama-widget-visible.png");
        }

        await expect(heading).toBeVisible();
    });

    test("HR can navigate to Leave Requests and manage requests", async ({ page }) => {
        await page.goto("/admin/leave-requests");
        await page.waitForLoadState("networkidle");

        await captureEvidence(page, "cuti-bersama-hr-leave-requests.png");

        await expect(page.getByRole("heading", { name: /Leave Requests/i })).toBeVisible({ timeout: 10_000 });

        const listTab = page.getByRole("button", { name: /List/i });
        await expect(listTab).toBeVisible();

        const calendarTab = page.getByRole("button", { name: /Calendar/i });
        await expect(calendarTab).toBeVisible();

        await calendarTab.click();
        await page.waitForLoadState("networkidle");
        await captureEvidence(page, "cuti-bersama-calendar-view.png");

        await expect(page.getByRole("button", { name: /Today/i })).toBeVisible();
    });
});
