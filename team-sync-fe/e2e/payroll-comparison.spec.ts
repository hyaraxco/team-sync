import { test, expect } from "@playwright/test";
import { loginAsRole } from "./helpers/auth";
import { captureEvidence } from "./helpers/evidence";

test.describe("Month-over-Month Payroll Comparison Report", () => {
    test.beforeEach(async ({ page }) => {
        await loginAsRole(page, "finance");
    });

    test("HR should be able to view the Payroll MoM Comparison Report", async ({ page }) => {
        await page.goto("/admin/payroll/comparison");
        await page.waitForLoadState("networkidle");

        await captureEvidence(page, "payroll-mom-comparison-page.png");

        await expect(page.getByRole("heading", { name: /Month-over-Month Comparison/i })).toBeVisible({ timeout: 10_000 });

        const month1Input = page.locator('input[type="month"]').nth(0);
        const month2Input = page.locator('input[type="month"]').nth(1);
        await expect(month1Input).toBeVisible();
        await expect(month2Input).toBeVisible();

        const compareBtn = page.getByRole("button", { name: /Compare/i });
        await expect(compareBtn).toBeVisible();

        await compareBtn.click();

        await page.waitForTimeout(3_000);

        await captureEvidence(page, "payroll-mom-comparison-results.png");

        const hasTable = await page.getByRole("table").isVisible().catch(() => false);
        const hasNoData = await page.getByText(/No payroll data found/i).isVisible().catch(() => false);
        const hasLoading = await page.getByText(/Loading/i).isVisible().catch(() => false);

        expect(hasTable || hasNoData || hasLoading).toBeTruthy();
    });
});
