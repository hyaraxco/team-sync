import { test, expect } from "./support/fixtures";
import { loginAsRole } from "./helpers/auth";

test.describe.serial("Performance TOPSIS Ranking UI", () => {
  test.setTimeout(60_000);

  test("HR sees info banner for TOPSIS ranking on an active cycle (not completed)", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "hr");

    // The E2E seeder creates "E2E Review Cycle P4" with ID 1 (status: active)
    await page.goto("/admin/performance/cycles/1");
    await expect(page).toHaveURL(/\/admin\/performance\/cycles\/1$/);
    await expect(page.getByRole("heading", { name: "E2E Review Cycle P4" })).toBeVisible({ timeout: 15_000 });

    // For active cycles, TOPSIS section shows info banner (not the ranking panel)
    await expect(
      page.getByText(/TOPSIS Ranking is only available after the cycle is/i)
    ).toBeVisible();

    // The "Recalculate" button should NOT be visible for active cycles
    await expect(page.getByRole("button", { name: /Recalculate/i })).not.toBeVisible();

    await context.close();
  });

  test("HR can view, configure weights, and recalculate TOPSIS ranking for a completed cycle", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "hr");

    // The PerformanceDataSeeder creates "Q4 2025 Performance Review" (status: completed)
    await page.goto("/admin/performance/cycles/3");
    await expect(page).toHaveURL(/\/admin\/performance\/cycles\/3$/);
    await expect(page.getByRole("heading", { name: "Q4 2025 Performance Review" })).toBeVisible({ timeout: 15_000 });

    // TOPSIS heading and Recalculate button should be visible for completed cycles
    await expect(page.getByText("TOPSIS Performance Ranking")).toBeVisible();
    const recalculateButton = page.getByRole("button", { name: /Recalculate/i });
    await expect(recalculateButton).toBeVisible();

    // ── Weight Configuration ──
    // Open weight config panel
    await page.getByText(/Criteria Weights Configuration/i).click();

    // The sliders/inputs should be visible
    const rangeInputs = page.locator('input[type="range"]');
    await expect(rangeInputs.first()).toBeVisible();

    // Change first weight to max (1.0) — should break validation since total > 1.0
    await rangeInputs.nth(0).fill("1");

    // Recalculate button should be disabled when weights are invalid
    await expect(recalculateButton).toBeDisabled();

    // Reset weights
    await page.getByRole("button", { name: /Reset to Default/i }).click();
    await expect(recalculateButton).toBeEnabled();

    // ── Recalculate ──
    const responsePromise = page.waitForResponse(resp =>
      resp.url().includes("topsis-ranking") && resp.request().method() === "GET"
    );
    await recalculateButton.click();
    const topsisResponse = await responsePromise;

    // Backend should return 200 because there is a completed review
    expect(topsisResponse.status()).toBe(200);

    // Verify ranking table renders (use the one with "Rank" column, not the reviews table)
    const rankingTable = page.locator("table", { has: page.getByRole("columnheader", { name: "Rank" }) });
    await expect(rankingTable).toBeVisible();
    await expect(rankingTable.getByText("Agung Ramadhan")).toBeVisible();

    // Verify the Ideal Solution panel renders
    await expect(page.getByText("Positive Ideal Solution (A⁺)")).toBeVisible();
    await expect(page.getByText("Negative Ideal Solution (A⁻)")).toBeVisible();

    await context.close();
  });
});
