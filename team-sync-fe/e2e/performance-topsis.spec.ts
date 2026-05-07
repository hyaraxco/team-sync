import { test, expect } from "./support/fixtures";
import { loginAsRole } from "./helpers/auth";
import { request } from "@playwright/test";

const apiBaseUrl = (process.env.VITE_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1").replace(/\/?$/, "/");

/**
 * Find a performance review cycle by status via API.
 * Returns the cycle ID or throws if not found.
 */
async function findCycleByStatus(token: string, status: "active" | "completed"): Promise<{ id: number; name: string }> {
  const api = await request.newContext({
    baseURL: apiBaseUrl,
    extraHTTPHeaders: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    },
  });
  const resp = await api.get("performance/cycles?per_page=50");
  const json = await resp.json();
  // API returns { success, message, data: { data: [...], ... } } (paginated)
  const wrapper = json.data ?? json;
  const cycles = Array.isArray(wrapper) ? wrapper : (wrapper.data ?? []);
  const cycle = cycles.find(
    (c: { status: string }) => c.status === status
  );
  if (!cycle) throw new Error(`No ${status} cycle found in seeded data. Got: ${JSON.stringify(cycles.map((c: {id:number,name:string,status:string}) => ({id:c.id,name:c.name,status:c.status})))}`);
  return { id: cycle.id, name: cycle.name };
}

test.describe.serial("Performance TOPSIS Ranking UI", () => {
  test.setTimeout(60_000);

  test("HR sees info banner for TOPSIS ranking on an active cycle (not completed)", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "hr");

    // Get token and find the active cycle dynamically
    const cookies = await context.cookies();
    const token = cookies.find(c => c.name === "token")?.value ?? "";
    const activeCycle = await findCycleByStatus(token, "active");

    await page.goto(`/admin/performance/cycles/${activeCycle.id}`);
    await expect(page).toHaveURL(new RegExp(`/admin/performance/cycles/${activeCycle.id}$`));
    await expect(page.getByRole("heading", { name: activeCycle.name })).toBeVisible({ timeout: 15_000 });

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

    // Get token and find the completed cycle dynamically
    const cookies = await context.cookies();
    const token = cookies.find(c => c.name === "token")?.value ?? "";
    const completedCycle = await findCycleByStatus(token, "completed");

    await page.goto(`/admin/performance/cycles/${completedCycle.id}`);
    await expect(page).toHaveURL(new RegExp(`/admin/performance/cycles/${completedCycle.id}$`));
    await expect(page.getByRole("heading", { name: completedCycle.name })).toBeVisible({ timeout: 15_000 });

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
