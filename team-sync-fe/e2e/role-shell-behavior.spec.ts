import { test, expect } from "./support/fixtures";
import { loginAsRole, roleCredentials, type RoleName } from "./helpers/auth";

const api = process.env.VITE_API_BASE_URL || "http://127.0.0.1:8000/api/v1";
const roles: RoleName[] = ["employee", "manager", "hr", "finance", "superadmin"];

for (const role of roles) {
    test(`${role}: employee identity and own profile changes persist`, async ({ page }) => {
        await loginAsRole(page, role);
        const token = (await page.context().cookies()).find(cookie => cookie.name === "token")?.value;
        expect(token).toBeTruthy();
        const headers = { Authorization: `Bearer ${decodeURIComponent(token!)}`, Accept: "application/json" };
        const identity = await page.request.get(`${api}/me`, { headers });
        expect(identity.status()).toBe(200);
        const user = (await identity.json()).data;
        expect(user.email).toBe(roleCredentials[role].email);
        expect(user.roles).toEqual([role === "employee" ? "staff" : role]);
        expect(user.employee_profile.id).toBeTruthy();
        expect(user.employee_profile.job_information.id).toBeTruthy();
        expect(user.permissions).toContain("profile-view");

        await page.goto("/admin/my-profile/edit");
        await expect(page.getByLabel("Full Name")).toHaveValue(user.name);
        const changedName = `${user.name} E2E`;
        try {
            await page.getByLabel("Full Name").fill(changedName);
            const saved = page.waitForResponse(response =>
                new URL(response.url()).pathname.endsWith("/me") && response.request().method() === "POST");
            await page.getByRole("button", { name: "Save Changes" }).click();
            expect((await saved).status()).toBe(200);
            await page.reload();
            await expect(page.getByLabel("Full Name")).toHaveValue(changedName);
            const persisted = await page.request.get(`${api}/me`, { headers });
            expect((await persisted.json()).data.name).toBe(changedName);
        } finally {
            const restored = await page.request.put(`${api}/me`, { headers, data: { name: user.name } });
            expect(restored.status()).toBe(200);
        }
    });

    test(`${role}: notification refresh and license authority use real responses`, async ({ page }) => {
        await loginAsRole(page, role);
        const token = (await page.context().cookies()).find(cookie => cookie.name === "token")!.value;
        const headers = { Authorization: `Bearer ${decodeURIComponent(token)}`, Accept: "application/json" };
        await page.goto("/admin/notifications");
        await expect(page.getByRole("heading", { name: "All Notifications" })).toBeVisible();
        const refreshed = page.waitForResponse(response =>
            new URL(response.url()).pathname.endsWith("/my-notifications") && response.request().method() === "GET");
        await page.getByRole("button", { name: "Refresh", exact: true }).click();
        const response = await refreshed;
        expect(response.status()).toBe(200);
        const body = await response.json();
        expect(Array.isArray(body.data.items)).toBe(true);
        if (body.data.items.length === 0) {
            await expect(page.getByText("Belum ada notifikasi.")).toBeVisible();
        } else {
            await expect(page.getByRole("button", { name: "Mark all read", exact: true })).toBeEnabled();
            const marked = page.waitForResponse(r => r.url().endsWith("/my-notifications/mark-all-read"));
            await page.getByRole("button", { name: "Mark all read", exact: true }).click();
            expect((await marked).status()).toBe(200);
            const unread = await page.request.get(`${api}/my-notifications/unread-count`, { headers });
            expect(unread.status()).toBe(200);
            expect((await unread.json()).data.unread_count).toBe(0);
        }
        await page.goto("/admin/upgrade-plan");
        await expect(page.getByRole("heading", { name: "License management", exact: true })).toBeVisible();
        await expect(page.getByText("This page does not offer plan selection or license purchases.", { exact: false })).toBeVisible();
        const license = await page.request.get(`${api}/licenses`, { headers });
        expect(license.status()).toBe(role === "superadmin" ? 200 : 403);
    });

    test(`${role}: dashboard data respects workforce authority`, async ({ page }) => {
        const endpoint = role === "employee" || role === "manager" ? "/dashboard/my-statistics"
            : role === "finance" ? "/analytics/payroll" : "/dashboard/statistics";
        const loaded = page.waitForResponse(response => new URL(response.url()).pathname.endsWith(endpoint));
        await loginAsRole(page, role);
        const response = await loaded;
        expect(response.status()).toBe(200);
        expect((await response.json()).data).toBeTruthy();
        if (role === "employee" || role === "manager") {
            await expect(page.getByRole("heading", { name: /Welcome back,/ })).toBeVisible();
        } else if (role === "finance") {
            await expect(page.getByRole("heading", { name: "Payroll Cost Trends" })).toBeVisible();
        } else {
            await expect(page.getByText("Our Employees", { exact: true }).first()).toBeVisible();
        }
        const token = (await page.context().cookies()).find(cookie => cookie.name === "token")!.value;
        const workforce = await page.request.get(`${api}/dashboard/statistics`, {
            headers: { Authorization: `Bearer ${decodeURIComponent(token)}`, Accept: "application/json" },
        });
        expect(workforce.status()).toBe(role === "hr" || role === "superadmin" ? 200 : 403);
    });

    test(`${role}: analytics filters honor domain permissions`, async ({ page }) => {
        await loginAsRole(page, role);
        const token = (await page.context().cookies()).find(cookie => cookie.name === "token")!.value;
        const headers = { Authorization: `Bearer ${decodeURIComponent(token)}`, Accept: "application/json" };
        if (role === "employee") {
            await page.goto("/admin/analytics");
            await expect(page).toHaveURL(/\/admin\/dashboard$/);
        } else {
            await page.goto("/admin/analytics");
            const tab = role === "finance" ? "Payroll" : role === "manager" ? "Performance" : "Workforce";
            await page.getByRole("tab", { name: tab, exact: true }).click();
            const endpoint = role === "finance" ? "/analytics/payroll"
                : role === "manager" ? "/analytics/performance/team-summary" : "/analytics/workforce";
            const filtered = page.waitForResponse(response =>
                new URL(response.url()).pathname.endsWith(endpoint) && new URL(response.url()).searchParams.get("period") === "3m");
            await page.getByLabel("Filter by period").selectOption("3m");
            const response = await filtered;
            expect(response.status()).toBe(200);
            expect((await response.json()).data).toBeTruthy();
            await expect(page.getByRole("tab", { name: tab, exact: true })).toHaveAttribute("aria-selected", "true");
        }
        const workforce = await page.request.get(`${api}/analytics/workforce`, { headers });
        expect(workforce.status()).toBe(role === "hr" || role === "superadmin" ? 200 : 403);
        const payroll = await page.request.get(`${api}/analytics/payroll`, { headers });
        expect(payroll.status()).toBe(role === "finance" || role === "superadmin" ? 200 : 403);
    });

    test(`${role}: settings expose only permitted configuration destinations`, async ({ page }) => {
        await loginAsRole(page, role);
        const token = (await page.context().cookies()).find(cookie => cookie.name === "token")!.value;
        const identity = await page.request.get(`${api}/me`, {
            headers: { Authorization: `Bearer ${decodeURIComponent(token)}`, Accept: "application/json" },
        });
        expect(identity.status()).toBe(200);
        const permissions: string[] = (await identity.json()).data.permissions;
        const expectedSettings: Record<RoleName, string[]> = {
            employee: [], manager: [], hr: ["settings-hr-manage"],
            finance: ["settings-finance-manage"],
            superadmin: ["settings-hr-manage", "settings-finance-manage", "settings-system-manage"],
        };
        expect(permissions.filter(permission => permission.startsWith("settings-")).sort()).toEqual(expectedSettings[role].sort());
        await page.goto("/admin/settings");
        if (!permissions.some(permission => ["settings-hr-manage", "settings-finance-manage", "settings-system-manage"].includes(permission))) {
            await expect(page).toHaveURL(/\/admin\/dashboard$/);
            return;
        }
        await expect(page.getByRole("main").getByRole("heading", { name: "Settings", exact: true })).toBeVisible();
        for (const [permission, destination] of [
            ["settings-finance-manage", "/admin/payroll/settings"],
            ["settings-hr-manage", "/admin/attendance-settings"],
            ["settings-hr-manage", "/admin/performance/templates"],
        ]) {
            const link = page.getByRole("main").locator(`a[href="${destination}"]`);
            if (permissions.includes(permission)) await expect(link).toBeVisible();
            else await expect(link).toHaveCount(0);
        }
    });
}
