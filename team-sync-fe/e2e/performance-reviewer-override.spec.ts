import { test, expect } from "./support/fixtures";
import { loginAsRole } from "./helpers/auth";

test.describe.serial("Performance Reviewer Override Journey", () => {
  test.setTimeout(120_000);

  test("HR can generate reviews and override an assigned reviewer", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    // 1. Login as HR
    await loginAsRole(page, "hr");

    // 2. Navigate to Review Cycle Detail
    // We assume Cycle 1 exists from seeders and is in active state
    await page.goto("/admin/performance/cycles/1");
    await expect(page).toHaveURL(/\/admin\/performance\/cycles\/1$/);

    // Verify we are on the detail page and the Generated Reviews section is visible
    await expect(page.getByText("Generated Reviews")).toBeVisible({ timeout: 15_000 });

    // 3. Generate Reviews
    // Check if the "Generate Reviews" button exists and click it
    const generateBtn = page.getByRole("button", { name: /Generate Reviews/i });
    if (await generateBtn.isVisible()) {
      await generateBtn.click();
      // ConfirmationModal component with confirm-text="Generate"
      await expect(page.getByRole("heading", { name: /Generate Reviews/i })).toBeVisible();
      await page.getByRole("button", { name: /^Generate$/ }).click();
      
      // Wait for success toast (using vue-toastification standard classes or just wait for text)
      await expect(page.getByText(/Successfully generated/i)).toBeVisible({ timeout: 15_000 });
    }

    // 4. Override reviewer assignment
    // Find the first "Assign" button
    const assignBtn = page.getByTitle("Override Reviewer").first();
    await expect(assignBtn).toBeVisible({ timeout: 15_000 });
    await assignBtn.click();

    // Wait for the Override Modal
    const modalHeading = page.getByRole("heading", { name: "Assign Reviewer" });
    await expect(modalHeading).toBeVisible();

    const selectReviewer = page.locator("select");
    
    // Wait for options to populate (at least 2: placeholder + 1 staff member)
    await expect(async () => {
      const count = await selectReviewer.locator('option').count();
      expect(count).toBeGreaterThanOrEqual(2);
    }).toPass({ timeout: 15_000 });
    
    // Select a reviewer from the dropdown
    await selectReviewer.waitFor({ state: "visible" });
    
    // Choose the second option (first real staff member after disabled placeholder)
    await selectReviewer.selectOption({ index: 1 });

    // Click Save Assignment
    const saveBtn = page.getByRole("button", { name: /Save Assignment/i });
    await saveBtn.click();

    // Verify success
    await expect(page.getByText(/Reviewer assigned successfully/i)).toBeVisible({ timeout: 15_000 });
    
    // The modal should close
    await expect(modalHeading).toHaveCount(0);

    // Verify badging exists in the row
    // The role badge should appear next to the reviewer's name
    const tableBody = page.locator("table tbody");
    await expect(tableBody.locator("span.bg-blue-100").first()).toBeVisible();

    await context.close();
  });
});
