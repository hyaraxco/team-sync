import { test, expect } from "./support/fixtures";
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

        await expect
            .poll(async () => {
                if (await page.getByRole("table").first().isVisible()) {
                    return "table";
                }

                if (await page.getByText(/No payroll data found/i).first().isVisible()) {
                    return "no-data";
                }

                if (await page.getByText(/loading/i).first().isVisible()) {
                    return "loading";
                }

                return "pending";
            }, {
                timeout: 15_000,
                intervals: [250, 500, 1_000],
            })
            .toMatch(/^(table|no-data)$/);

        await captureEvidence(page, "payroll-mom-comparison-results.png");
    });
});
