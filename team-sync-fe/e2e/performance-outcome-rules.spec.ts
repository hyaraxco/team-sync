import { test, expect } from "./support/fixtures";
import { loginAsRole } from "./helpers/auth";

test.describe.serial("Performance Outcome Rules CRUD", () => {
  test.setTimeout(60_000);

  test("HR can navigate to outcome rules page", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "hr");

    await page.goto("/admin/performance/outcome-rules");
    await expect(page).toHaveURL(/\/admin\/performance\/outcome-rules$/);
    await expect(page.getByText("Performance Outcome Rules")).toBeVisible();

    // Page loads — either table or empty state
    await page.waitForLoadState("networkidle");
    const pageText = await page.textContent("body");
    const hasContent = pageText?.includes("Outstanding") || pageText?.includes("No outcome rules");
    expect(hasContent).toBe(true);

    await context.close();
  });

  test("HR can create, edit, and delete an outcome rule", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "hr");
    await page.goto("/admin/performance/outcome-rules");
    await page.waitForLoadState("networkidle");
    const token = (await context.cookies()).find(cookie => cookie.name === "token")!.value;
    const api = process.env.VITE_API_BASE_URL || "http://127.0.0.1:8000/api/v1";
    const headers = { Authorization: `Bearer ${decodeURIComponent(token)}`, Accept: "application/json" };
    const listed = await page.request.get(`${api}/performance/outcome-rules`, { headers });
    expect(listed.status()).toBe(200);
    expect((await listed.json()).data.some((rule: { label: string }) => rule.label === "Outstanding")).toBe(true);
    let createdRuleId: number | undefined;
    try {

    // Create
    await page.getByRole("button", { name: /Add Rule/i }).first().click();
    await expect(page.getByRole("heading", { name: /Add Outcome Rule/i })).toBeVisible();

    await page.locator('input[type="text"]').fill("E2E Test Rule");
    const numberInputs = page.locator("form input[type='number']");
    await numberInputs.nth(0).fill("1.00");
    await numberInputs.nth(1).fill("1.49");
    await numberInputs.nth(2).fill("1.5");
    await numberInputs.nth(3).fill("3");
    await page.getByLabel("Active", { exact: true }).uncheck();

    const responsePromise = page.waitForResponse(resp =>
      resp.url().includes("outcome-rules") && resp.request().method() === "POST"
    );
    await page.getByRole("button", { name: /Create Rule/i }).click();
    const createResponse = await responsePromise;

    expect(createResponse.status()).toBe(201);
    createdRuleId = (await createResponse.json()).data.id;
    await expect(page.getByText(/created successfully/i)).toBeVisible({ timeout: 5_000 });
    await expect(page.getByText("E2E Test Rule")).toBeVisible();

      // Edit
      const ruleRow = page.locator("table tbody tr", { hasText: "E2E Test Rule" });
      await ruleRow.locator("button").first().click();
      await expect(page.getByRole("heading", { name: /Edit Rule/i })).toBeVisible();

      await page.locator("input[type='text']").fill("E2E Updated Rule");
      const updated = page.waitForResponse(resp => resp.url().includes("outcome-rules") && resp.request().method() === "PUT");
      await page.getByRole("button", { name: /Update Rule/i }).click();
      expect((await updated).status()).toBe(200);

      // Delete
      const updatedRow = page.locator("table tbody tr", { hasText: "E2E Updated Rule" });
      await updatedRow.locator("button").last().click();
      // ConfirmationModal component — click its "Delete" button
      await expect(page.getByRole("heading", { name: /Delete Rule/i })).toBeVisible();
      const deleted = page.waitForResponse(resp => resp.url().includes("outcome-rules") && resp.request().method() === "DELETE");
      await page.getByRole("button", { name: /^Delete$/ }).click();
      expect((await deleted).status()).toBe(200);
      await expect(page.getByText("E2E Updated Rule")).not.toBeVisible();

    } finally {
      if (createdRuleId !== undefined) {
        const cleanupResponse = await page.request.delete(`${api}/performance/outcome-rules/${createdRuleId}`, { headers });
        expect([200, 404]).toContain(cleanupResponse.status());
      }
    }
    await context.close();
  });
});
