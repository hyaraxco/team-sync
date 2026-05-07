import { test, expect } from "@playwright/test";
import { loginAsRole } from "./helpers/auth";

test("debug staff list grids and API", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await loginAsRole(page, "hr");
    
    // Intercept the staff-members API call
    const apiPromise = page.waitForResponse(resp => 
        resp.url().includes("staff-members") && resp.request().method() === "GET"
    );
    
    await page.goto("/admin/staff-members");
    
    const apiResp = await apiPromise.catch(() => null);
    if (apiResp) {
        console.log("API status:", apiResp.status());
        const body = await apiResp.json().catch(() => null);
        console.log("API data count:", body?.data?.data?.length ?? "N/A");
        console.log("API first employee:", JSON.stringify(body?.data?.data?.[0]?.user?.name ?? "N/A"));
    } else {
        console.log("No API response captured");
    }
    
    await page.waitForLoadState("networkidle");
    await page.waitForTimeout(3000);
    
    // Count all grids
    const grids = page.locator(".grid");
    const gridCount = await grids.count();
    console.log("Total grids:", gridCount);
    
    for (let i = 0; i < gridCount; i++) {
        const text = await grids.nth(i).innerText();
        const hasEdit = text.includes("Edit");
        const hasView = text.includes("View");
        console.log(`Grid ${i}: hasEdit=${hasEdit}, hasView=${hasView}, chars=${text.length}`);
        if (hasEdit || hasView) {
            console.log(`Grid ${i} text (first 500):`, text.substring(0, 500));
        }
    }
    
    await context.close();
});
