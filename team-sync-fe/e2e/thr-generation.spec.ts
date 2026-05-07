import { test, expect } from "./support/fixtures";
import { loginAsRole } from "./helpers/auth";
import { captureEvidence } from "./helpers/evidence";

test.describe("THR Generation Flow (E2E)", () => {
    test.beforeEach(async ({ page }) => {
        await loginAsRole(page, "hr");
    });

    test("HR should be able to navigate to THR Management and view/generate THR", async ({ page }) => {
        await page.goto("/admin/payroll/thr");
        await page.waitForLoadState("networkidle");

        await captureEvidence(page, "thr-flow-management-page.png");

        await expect(page.getByRole("heading", { name: /THR Management/i })).toBeVisible({ timeout: 10_000 });

        const generateBtn = page.getByRole("button", { name: /Generate THR/i });
        await expect(generateBtn).toBeVisible();

        await generateBtn.click();

        await expect(page.getByText(/Generate THR/i).first()).toBeVisible({ timeout: 5_000 });
        await captureEvidence(page, "thr-flow-generate-modal.png");

        await expect(page.getByRole("combobox").first()).toBeVisible();

        const cancelBtn = page.getByRole("button", { name: /Cancel/i });
        if (await cancelBtn.isVisible()) {
            await cancelBtn.click();
        }
    });
});
