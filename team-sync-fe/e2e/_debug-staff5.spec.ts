import { test } from "@playwright/test";
import { loginAsRole } from "./helpers/auth";

test("debug staff API call", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await loginAsRole(page, "hr");
    
    const responses: string[] = [];
    page.on("response", async (resp) => {
        const url = resp.url();
        if (url.includes("8000") && url.includes("staff")) {
            const body = await resp.text().catch(() => "");
            responses.push(`${resp.status()} ${url} => ${body.substring(0, 500)}`);
        }
    });
    
    await page.goto("/admin/staff-members");
    await page.waitForLoadState("networkidle");
    await page.waitForTimeout(5000);
    
    console.log("=== BACKEND API CALLS ===");
    for (const r of responses) {
        console.log(r);
    }
    console.log("Total backend calls:", responses.length);
    
    // Also dump the full page text
    const fullText = await page.locator("body").innerText();
    console.log("=== FULL PAGE TEXT (last 1000) ===");
    console.log(fullText.substring(fullText.length - 1000));
    
    await context.close();
});
