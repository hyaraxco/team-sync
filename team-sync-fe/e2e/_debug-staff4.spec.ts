import { test } from "@playwright/test";
import { loginAsRole } from "./helpers/auth";

test("debug staff API response", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await loginAsRole(page, "hr");
    
    const responses: any[] = [];
    page.on("response", async (resp) => {
        if (resp.url().includes("staff-member")) {
            const body = await resp.json().catch(() => null);
            responses.push({ url: resp.url(), status: resp.status(), keys: Object.keys(body || {}), dataKeys: Object.keys(body?.data || {}), firstItem: body?.data?.data?.[0] ? Object.keys(body.data.data[0]) : body?.data?.[0] ? Object.keys(body.data[0]) : "none" });
        }
    });
    
    await page.goto("/admin/staff-members");
    await page.waitForLoadState("networkidle");
    await page.waitForTimeout(3000);
    
    console.log("=== API RESPONSES ===");
    for (const r of responses) {
        console.log(JSON.stringify(r));
    }
    
    await context.close();
});
