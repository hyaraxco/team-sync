import { test, expect } from "@playwright/test";
import { loginAsRole } from "./helpers/auth";

test("debug staff list page content", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await loginAsRole(page, "hr");
    await page.goto("/admin/staff-members");
    await page.waitForLoadState("networkidle");
    await page.waitForTimeout(3000);
    const bodyText = await page.locator("main").innerText();
    console.log("=== STAFF LIST CONTENT (first 3000 chars) ===");
    console.log(bodyText.substring(0, 3000));
    console.log("=== END ===");
    console.log("Contains 'Edit':", bodyText.includes("Edit"));
    console.log("Contains 'View':", bodyText.includes("View"));
    console.log("Contains 'All Staff':", bodyText.includes("All Staff"));
    console.log("Contains 'Agung':", bodyText.includes("Agung"));
    await context.close();
});
