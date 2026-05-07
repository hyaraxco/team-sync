import { test, expect } from "@playwright/test";
import { loginAsRole } from "./helpers/auth";

test("debug staff list HTML", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await loginAsRole(page, "hr");
    await page.goto("/admin/staff-members");
    await page.waitForLoadState("networkidle");
    await page.waitForTimeout(5000);
    
    // Check for buttons with text Edit
    const editCount = await page.locator("text=Edit").count();
    const viewCount = await page.locator("text=View").count();
    const agungCount = await page.locator("text=Agung").count();
    const cardCount = await page.locator("[class*='rounded-[16px]']").count();
    
    console.log("=== BUTTON DEBUG ===");
    console.log("Edit text count:", editCount);
    console.log("View text count:", viewCount);
    console.log("Agung text count:", agungCount);
    console.log("Card-like elements:", cardCount);
    
    // Try to get inner HTML of the grid area
    const gridHtml = await page.locator(".grid").first().innerHTML();
    console.log("Grid HTML (first 2000):", gridHtml.substring(0, 2000));
    
    await context.close();
});
