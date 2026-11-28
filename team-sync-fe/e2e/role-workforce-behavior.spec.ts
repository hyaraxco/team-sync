import { request, type APIRequestContext, type APIResponse, type Browser, type Page } from "@playwright/test";
import { test, expect } from "./support/fixtures";
import { loginAsRole, roleCredentials, type RoleName } from "./helpers/auth";

const apiBase = (process.env.VITE_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1").replace(/\/?$/, "/");

type Employee = { id: number; user: { name: string; email: string } };
type Team = { id: number; name: string; leader?: Employee["user"] };
type Policy = { id: number; employment_type: string; late_grace_minutes: number };
type Entitlement = { id: number; requires_reason: boolean };
type RecordData = { id: number; name: string; reason: string; status: string; date: string };
type Period = { status: string; start_date: string; end_date: string };

async function session(browser: Browser, role: RoleName) {
    const context = await browser.newContext();
    const page = await context.newPage();
    await loginAsRole(page, role);
    const token = (await context.cookies()).find((cookie) => cookie.name === "token")?.value;
    expect(token, `${role} login must issue a token`).toBeTruthy();
    const api = await request.newContext({
        baseURL: apiBase,
        extraHTTPHeaders: { Accept: "application/json", Authorization: `Bearer ${token}` },
    });
    return { page, api, close: async () => { await api.dispose(); await context.close(); } };
}

async function data<T = RecordData>(response: APIResponse): Promise<T> {
    expect(response.ok(), `HTTP ${response.status()}: ${await response.text()}`).toBeTruthy();
    const body = await response.json();
    expect(body.success).toBe(true);
    return body.data;
}

async function directoryEmployee(api: APIRequestContext, role: RoleName) {
    const employees = await data<Employee[]>(await api.get("staff-members", { params: { search: roleCredentials[role].email.split("@")[0] } }));
    const employee = employees.find((item) => item.user.email === roleCredentials[role].email);
    expect(employee, `Seeded employee profile for ${role}`).toBeTruthy();
    if (!employee) throw new Error(`Missing seeded employee profile for ${role}`);
    return employee;
}

async function showDirectoryEmployee(page: Page, employee: Employee) {
    const response = page.waitForResponse((res) => res.url().includes("/staff-members/all/paginated") && res.request().method() === "GET");
    await page.goto("/admin/staff-members");
    expect((await response).status()).toBe(200);
    await page.getByPlaceholder("Search employees by name, department, role...").fill(employee.user.name);
    await expect(page.locator("main").getByText(employee.user.name, { exact: true }).first()).toBeVisible();
    await page.goto(`/admin/staff-members/${employee.id}`);
    await expect(page.locator("main").getByText(employee.user.name, { exact: true }).first()).toBeVisible();
}

// Existing attendance-flow covers Staff leave submission; meeting-schedule only covers navigation.
test.describe("Role workforce behavior", () => {
    test.describe.configure({ mode: "default" });
    test.setTimeout(120_000);

    for (const role of ["hr", "superadmin"] as const) {
        test(`${role} reads employee workforce details and persists a team change`, async ({ browser }) => {
            const actor = await session(browser, role);
            try {
                const employee = await directoryEmployee(actor.api, "employee");
                await showDirectoryEmployee(actor.page, employee);
                const teams = await data<Team[]>(await actor.api.get("teams"));
                const team = teams.find((item) => item.name === "Operations");
                if (!team) throw new Error("Missing seeded Operations team");
                const name = `${team.name} E2E ${role} ${Date.now()}`;
                try {
                    const updated = await data(await actor.api.put(`teams/${team.id}`, { data: { name } }));
                    expect(updated.name).toBe(name);
                    await actor.page.goto(`/admin/teams/${team.id}`);
                    await expect(actor.page.getByRole("heading", { name, exact: true })).toBeVisible();
                    expect((await data(await actor.api.get(`teams/${team.id}`))).name).toBe(name);
                } finally {
                    await data(await actor.api.put(`teams/${team.id}`, { data: { name: team.name } }));
                }
            } finally {
                await actor.close();
            }
        });
    }

    for (const role of ["employee", "finance", "manager"] as const) {
        test(`${role} is denied workforce administration by API and route guard`, async ({ browser }) => {
            const actor = await session(browser, role);
            try {
                expect((await actor.api.get("staff-members")).status()).toBe(403);
                expect((await actor.api.post("staff-members", { data: {} })).status()).toBe(403);
                await actor.page.goto("/admin/staff-members/create");
                await expect(actor.page).toHaveURL(/\/admin\/dashboard$/);
                if (role !== "manager") {
                    expect((await actor.api.get("teams")).status()).toBe(403);
                    expect((await actor.api.get("attendance-policies")).status()).toBe(403);
                    await actor.page.goto("/admin/teams");
                    await expect(actor.page).toHaveURL(/\/admin\/dashboard$/);
                    await actor.page.goto("/admin/attendance-settings");
                    await expect(actor.page).toHaveURL(/\/admin\/dashboard$/);
                }
            } finally {
                await actor.close();
            }
        });
    }

    test("manager changes a led team but cannot mutate another leader's team", async ({ browser }) => {
        const manager = await session(browser, "manager");
        try {
            const teams = await data<Team[]>(await manager.api.get("teams"));
            const own = teams.find((team) => team.leader?.email === roleCredentials.manager.email);
            const other = teams.find((team) => team.leader?.email !== roleCredentials.manager.email);
            if (!own || !other) throw new Error("Manager needs a led team and a separately led team");
            const name = `E2E manager team ${Date.now()}`;
            try {
                expect((await data(await manager.api.put(`teams/${own.id}`, { data: { name } }))).name).toBe(name);
                await manager.page.goto(`/admin/teams/${own.id}`);
                await expect(manager.page.getByRole("heading", { name, exact: true })).toBeVisible();
                expect((await manager.api.put(`teams/${other.id}`, { data: { name: "Unauthorized team rename" } })).status()).toBe(403);
                expect((await data(await manager.api.get(`teams/${other.id}`))).name).toBe(other.name);
                // The edit route is permission-only; the submit result must still enforce ownership.
                await manager.page.goto(`/admin/teams/edit/${other.id}`);
                await manager.page.getByPlaceholder("Enter team name ", { exact: true }).fill("Unauthorized team rename");
                const denied = manager.page.waitForResponse((res) => res.url().endsWith(`/teams/${other.id}`) && res.request().method() === "POST");
                await manager.page.getByRole("button", { name: "Update Team" }).click();
                expect((await denied).status()).toBe(403);
                await expect(manager.page).toHaveURL(new RegExp(`/admin/teams/edit/${other.id}$`));
                await expect(manager.page.getByRole("button", { name: "Update Team" })).toBeVisible();
            } finally {
                await data(await manager.api.put(`teams/${own.id}`, { data: { name: own.name } }));
            }
        } finally {
            await manager.close();
        }
    });

    for (const role of ["hr", "superadmin"] as const) {
    test(`${role} edits an attendance policy through the form and persists the visible grace period`, async ({ browser }) => {
        const hr = await session(browser, role);
        try {
            const policies = await data<Policy[]>(await hr.api.get("attendance-policies"));
            const original = policies[0];
            expect(original, "Seeded employment-type attendance policy").toBeTruthy();
            const grace = Number(original.late_grace_minutes) === 17 ? 18 : 17;
            try {
                await hr.page.goto("/admin/attendance-settings");
                const card = hr.page.locator(".policy-card").filter({ hasText: original.employment_type.replaceAll("_", " ") }).first();
                await card.getByRole("button", { name: "Edit Policy" }).click();
                const modal = hr.page.getByRole("dialog", { name: "Edit Attendance Policy" });
                await modal.getByLabel("Late Grace Minutes").fill(String(grace));
                const saved = hr.page.waitForResponse((res) => res.url().endsWith(`/attendance-policies/${original.id}`) && res.request().method() === "PUT");
                await modal.getByRole("button", { name: "Save Policy" }).click();
                expect((await saved).status()).toBe(200);
                await expect(modal).not.toBeVisible();
                await expect(card.getByText(`${grace} mins`, { exact: true })).toBeVisible();
                expect((await data<Policy[]>(await hr.api.get("attendance-policies"))).find((policy) => policy.id === original.id)?.late_grace_minutes).toBe(grace);
            } finally {
                await data(await hr.api.put(`attendance-policies/${original.id}`, { data: original }));
            }
        } finally {
            await hr.close();
        }
    });
    }

    test("manager reads attendance policies but cannot change company policy", async ({ browser }) => {
        const manager = await session(browser, "manager");
        try {
            const policies = await data<Policy[]>(await manager.api.get("attendance-policies"));
            const original = policies[0];
            expect(original, "Seeded employment-type attendance policy").toBeTruthy();
            const entitlements = await data<Entitlement[]>(await manager.api.get("leave-entitlements"));
            const entitlement = entitlements[0];
            expect(entitlement, "Seeded leave entitlement").toBeTruthy();
            const grace = Number(original.late_grace_minutes) === 17 ? 18 : 17;
            const requiresReason = !entitlement.requires_reason;
            try {
                expect((await manager.api.put(`attendance-policies/${original.id}`, { data: { late_grace_minutes: grace } })).status()).toBe(403);
                expect((await data<Policy[]>(await manager.api.get("attendance-policies"))).find((policy) => policy.id === original.id)).toEqual(original);
                await manager.page.goto("/admin/attendance-settings");
                const card = manager.page.locator(".policy-card").filter({ hasText: original.employment_type.replaceAll("_", " ") }).first();
                await expect(card.getByText(`${original.late_grace_minutes} mins`, { exact: true })).toBeVisible();
                await expect(card.getByRole("button", { name: "Edit Policy" })).toHaveCount(0);
                await manager.page.getByRole("button", { name: "Leave Entitlements", exact: true }).click();
                await expect(manager.page.getByRole("heading", { name: "Leave Quotas & Rules" })).toBeVisible();
                await expect(manager.page.locator(".policy-card").first()).toBeVisible();
                await expect(manager.page.getByRole("button", { name: "Edit Rules", exact: true })).toHaveCount(0);
                expect((await manager.api.put(`leave-entitlements/${entitlement.id}`, { data: { requires_reason: requiresReason } })).status()).toBe(403);
                expect((await data<Entitlement[]>(await manager.api.get("leave-entitlements"))).find((item) => item.id === entitlement.id)).toEqual(entitlement);
            } finally {
                const policyAfter = (await data<Policy[]>(await manager.api.get("attendance-policies"))).find((policy) => policy.id === original.id);
                const entitlementAfter = (await data<Entitlement[]>(await manager.api.get("leave-entitlements"))).find((item) => item.id === entitlement.id);
                if (policyAfter?.late_grace_minutes === grace || entitlementAfter?.requires_reason === requiresReason) {
                    const hr = await session(browser, "hr");
                    try {
                        if (policyAfter?.late_grace_minutes === grace) {
                            await data(await hr.api.put(`attendance-policies/${original.id}`, { data: { late_grace_minutes: original.late_grace_minutes } }));
                        }
                        if (entitlementAfter?.requires_reason === requiresReason) {
                            await data(await hr.api.put(`leave-entitlements/${entitlement.id}`, { data: { requires_reason: entitlement.requires_reason } }));
                        }
                    } finally {
                        await hr.close();
                    }
                }
            }
        } finally {
            await manager.close();
        }
    });

    for (const approver of ["hr", "superadmin"] as const) {
    test(`finance owns its leave request, manager is scoped out, and ${approver} decides it visibly`, async ({ browser }) => {
        const finance = await session(browser, "finance");
        const manager = await session(browser, "manager");
        const hr = await session(browser, approver);
        try {
            const reason = `E2E finance leave ${Date.now()}`;
            const date = new Date();
            date.setMonth(date.getMonth() + 8, approver === "hr" ? 12 : 19);
            while ([0, 6].includes(date.getDay())) date.setDate(date.getDate() + 1);
            const day = date.toISOString().slice(0, 10);
            const leave = await data(await finance.api.post("leave-requests", { data: { leave_type: "personal_leave", start_date: day, end_date: day, reason } }));
            expect((await data<RecordData[]>(await finance.api.get("my-leave-requests"))).some((item) => item.id === leave.id && item.reason === reason)).toBe(true);
            await finance.page.goto("/admin/attendance/my-attendances");
            await finance.page.getByTestId(`my-leave-request-${leave.id}`).click();
            await expect(finance.page.getByRole("dialog", { name: "Leave Request Details" }).getByText(reason, { exact: true })).toBeVisible();
            expect((await finance.api.post(`leave-requests/approve/${leave.id}`)).status()).toBe(403);
            expect((await manager.api.get(`leave-requests/${leave.id}`)).status()).toBe(403);
            expect((await manager.api.post(`leave-requests/approve/${leave.id}`)).status()).toBe(403);
            await hr.page.goto("/admin/leave-requests");
            await hr.page.locator('input[type="month"]').fill(day.slice(0, 7));
            const row = hr.page.locator("tbody tr").filter({ hasText: reason });
            const approve = approver === "superadmin";
            const action = approve ? "Approve" : "Reject";
            const status = approve ? "approved" : "rejected";
            await row.getByRole("button", { name: `${action} leave`, exact: true }).click();
            const decided = hr.page.waitForResponse((res) => res.url().endsWith(`/leave-requests/${approve ? "approve" : "reject"}/${leave.id}`) && res.request().method() === "POST");
            await hr.page.getByRole("dialog").getByRole("button", { name: action, exact: true }).click();
            expect((await decided).status()).toBe(200);
            await expect(row).toContainText(new RegExp(status, "i"));
            expect((await data(await hr.api.get(`leave-requests/${leave.id}`))).status).toBe(status);
        } finally {
            await finance.close();
            await manager.close();
            await hr.close();
        }
    });
    }

    for (const approver of ["hr", "superadmin"] as const) {
    test(`${approver} records and approves overtime, finance cannot approve it, and manager cannot access an outside employee`, async ({ browser }) => {
        const hr = await session(browser, approver);
        const finance = await session(browser, "finance");
        const manager = await session(browser, "manager");
        const employee = await session(browser, "employee");
        try {
            const staff = await directoryEmployee(hr.api, "finance");
            const existing = await data<{ data: RecordData[] }>(await hr.api.get("overtime", { params: { staff_member_id: staff.id, per_page: 100 } }));
            const periods = await data<{ data: Period[] }>(await hr.api.get("attendance-periods", { params: { per_page: 100 } }));
            const date = new Date();
            date.setDate(date.getDate() - 40);
            let day = date.toISOString().slice(0, 10);
            // Select an unused, unlocked date rather than relying on today's seeded records.
            while (existing.data.some((item) => item.date.slice(0, 10) === day) || periods.data.some((period) => period.status === "locked" && period.start_date.slice(0, 10) <= day && period.end_date.slice(0, 10) >= day)) {
                date.setDate(date.getDate() - 1);
                day = date.toISOString().slice(0, 10);
            }
            const record = await data(await hr.api.post("overtime", { data: { staff_member_id: staff.id, date: day, start_time: "18:00", end_time: "19:00", overtime_type: "workday", notes: `E2E scoped overtime ${Date.now()}` } }));
            expect(record.status).toBe("pending");
            expect((await finance.api.post(`overtime/${record.id}/approve`)).status()).toBe(403);
            expect((await employee.api.post(`overtime/${record.id}/approve`)).status()).toBe(403);
            expect((await manager.api.get(`overtime/${record.id}`)).status()).toBe(403);
            expect((await manager.api.post(`overtime/${record.id}/approve`)).status()).toBe(403);
            await manager.page.goto("/admin/overtime");
            await manager.page.getByPlaceholder("Search overtime records...").fill(staff.user.name);
            await expect(manager.page.getByText("No overtime records found", { exact: true })).toBeVisible();
            await finance.page.goto("/admin/attendance/my-overtime");
            await expect(finance.page.getByTestId(`my-overtime-record-${record.id}`)).toContainText(/pending/i);
            await hr.page.goto("/admin/overtime");
            await hr.page.getByPlaceholder("Search overtime records...").fill(staff.user.name);
            const formattedDay = new Date(day).toLocaleDateString("en-US", { month: "short", day: "numeric" });
            const row = hr.page.locator("tbody tr").filter({ hasText: staff.user.name }).filter({ hasText: formattedDay }).filter({ hasText: "18:00" });
            await row.getByRole("button", { name: "Approve overtime", exact: true }).click();
            const approved = hr.page.waitForResponse((res) => res.url().endsWith(`/overtime/${record.id}/approve`) && res.request().method() === "POST");
            await hr.page.getByRole("dialog", { name: "Approve Overtime" }).getByRole("button", { name: "Approve", exact: true }).click();
            expect((await approved).status()).toBe(200);
            await expect(row).toContainText(/approved/i);
            expect((await data(await hr.api.get(`overtime/${record.id}`))).status).toBe("approved");
            await finance.page.reload();
            await expect(finance.page.getByTestId(`my-overtime-record-${record.id}`)).toContainText(/approved/i);
        } finally {
            await hr.close();
            await finance.close();
            await manager.close();
            await employee.close();
        }
    });
    }

    for (const scheduler of ["hr", "superadmin"] as const) {
    test(`${scheduler} schedules a team meeting, team members see it, and view-only roles cannot schedule`, async ({ browser }) => {
        const hr = await session(browser, scheduler);
        const employee = await session(browser, "employee");
        const finance = await session(browser, "finance");
        const manager = await session(browser, "manager");
        try {
            const teams = await data<Team[]>(await hr.api.get("teams"));
            const engineering = teams.find((team) => team.name === "Engineering");
            const operations = teams.find((team) => team.name === "Operations");
            if (!engineering || !operations) throw new Error("Missing seeded Engineering and Operations teams");
            const title = `E2E engineering meeting ${Date.now()}`;
            const date = new Date(Date.now() + 7 * 86400_000).toISOString().slice(0, 16);
            await hr.page.goto("/admin/meetings");
            await hr.page.getByRole("button", { name: "Schedule Meeting", exact: true }).click();
            const dialog = hr.page.getByRole("dialog", { name: "Schedule Meeting" });
            await dialog.getByPlaceholder("Meeting title").fill(title);
            await dialog.locator('input[type="datetime-local"]').fill(date);
            await dialog.getByLabel(engineering.name, { exact: true }).check();
            const created = hr.page.waitForResponse((res) => res.url().endsWith("/meetings") && res.request().method() === "POST");
            await dialog.getByRole("button", { name: "Schedule Meeting", exact: true }).click();
            const response = await created;
            expect(response.status()).toBe(201);
            const meeting = (await response.json()).data;
            await expect(hr.page.locator("tbody tr").filter({ hasText: title })).toBeVisible();
            const outside = await data(await hr.api.post("meetings", { data: { title: `${title} Operations`, scheduled_at: date.replace("T", " ") + ":00", duration_minutes: 30, team_ids: [operations.id] } }));
            for (const actor of [employee, finance, manager]) {
                expect((await actor.api.post("meetings", { data: { title: "Unauthorized meeting", scheduled_at: date.replace("T", " ") + ":00", duration_minutes: 30, team_ids: [engineering.id] } })).status()).toBe(403);
                await actor.page.goto("/admin/meetings");
                await expect(actor.page.getByRole("button", { name: "Schedule Meeting", exact: true })).toHaveCount(0);
            }
            expect((await employee.api.get(`meetings/${meeting.id}`)).status()).toBe(200);
            expect((await employee.api.get(`meetings/${outside.id}`)).status()).toBe(403);
            await employee.page.getByPlaceholder("Search meetings by title...").fill(title);
            await expect(employee.page.locator("tbody tr").filter({ hasText: title })).toHaveCount(1);
            await expect(employee.page.locator("tbody tr").filter({ hasText: title })).toContainText(engineering.name);
            expect((await finance.api.get(`meetings/${meeting.id}`)).status()).toBe(403);
            expect((await finance.api.get(`meetings/${outside.id}`)).status()).toBe(200);
            expect((await manager.api.get(`meetings/${meeting.id}`)).status()).toBe(200);
            expect((await manager.api.get(`meetings/${outside.id}`)).status()).toBe(403);
            await manager.page.getByPlaceholder("Search meetings by title...").fill(title);
            await expect(manager.page.locator("tbody tr").filter({ hasText: title })).toHaveCount(1);
            await expect(manager.page.locator("tbody tr").filter({ hasText: title })).toContainText(engineering.name);
        } finally {
            await hr.close();
            await employee.close();
            await finance.close();
            await manager.close();
        }
    });
    }

    for (const role of ["manager", "hr", "superadmin"] as const) {
        test(`${role} reads attendance records, periods, mismatches, holidays and hybrid schedules`, async ({ browser }) => {
            const actor = await session(browser, role);
            try {
                const ledEmployeeIds = new Set<number>();
                if (role === "manager") {
                    const teams = await data<Team[]>(await actor.api.get("teams"));
                    const ledTeams = teams.filter((team) => team.leader?.email === roleCredentials.manager.email);
                    expect(ledTeams.length, "Manager must lead a seeded team").toBeGreaterThan(0);
                    for (const team of ledTeams) {
                        const details = await data<{ members: { staff_member: Employee }[] }>(await actor.api.get(`teams/${team.id}`));
                        for (const member of details.members) ledEmployeeIds.add(member.staff_member.id);
                    }
                    expect(ledEmployeeIds.size, "Manager led teams must have seeded employees").toBeGreaterThan(0);
                }
                const families = [
                    { route: "attendance-records", endpoint: "attendances/all/paginated", empty: "No attendance data found", field: "employee" },
                    { route: "attendance-periods", endpoint: "attendance-periods", empty: "Data periode kosong", field: "month" },
                    { route: "attendance-policy-mismatches", endpoint: "attendance-policy-mismatches", empty: "No policy mismatches", field: "employee" },
                    { route: "holiday-calendar", endpoint: "holiday-calendars", empty: "No holidays found", field: "name" },
                    { route: "hybrid-schedules", endpoint: "hybrid-schedules", empty: "No hybrid schedules", field: "employee" },
                ];
                for (const family of families) {
                    const response = actor.page.waitForResponse((res) => new URL(res.url()).pathname === `/api/v1/${family.endpoint}` && res.request().method() === "GET");
                    await actor.page.goto(`/admin/${family.route}`);
                    const received = await response;
                    expect(received.status(), `${role}: ${family.endpoint}`).toBe(200);
                    const body = await received.json();
                    expect(body.success).toBe(true);
                    const rows: { staff_member_id?: number; name?: string; start_date?: string; end_date?: string; employee_name?: string; user?: { name: string }; staff_member?: { name?: string; user?: { name: string } } }[] = Array.isArray(body.data) ? body.data : body.data.data;
                    expect(Array.isArray(rows), `${family.endpoint} must return records`).toBe(true);
                    if (role === "manager" && family.field === "employee") {
                        for (const row of rows) expect(ledEmployeeIds.has(row.staff_member_id!), `${family.endpoint}: employee ${row.staff_member_id} must belong to a led team`).toBe(true);
                    }
                    if (!rows.length) {
                        await expect(actor.page.getByText(family.empty, { exact: true })).toBeVisible();
                    } else {
                        const first = rows[0];
                        const visible = family.field === "month" ? `${first.start_date} — ${first.end_date}` : family.field === "name" ? first.name : first.staff_member?.user?.name ?? first.staff_member?.name ?? first.employee_name ?? first.user?.name;
                        expect(visible, `${family.endpoint} visible record identifier`).toBeTruthy();
                        await expect(actor.page.locator("main").getByText(visible!, { exact: true }).first()).toBeVisible();
                    }
                }
                const response = actor.page.waitForResponse((res) => res.url().includes("/attendance-corrections/all/paginated") && res.request().method() === "GET");
                await actor.page.goto("/admin/attendance-corrections");
                const received = await response;
                expect(received.status()).toBe(200);
                const body = await received.json();
                expect(body.success).toBe(true);
                const rows: { staff_member_id: number; reason: string }[] = body.data.data;
                expect(Array.isArray(rows)).toBe(true);
                if (role === "manager") {
                    for (const row of rows) expect(ledEmployeeIds.has(row.staff_member_id), `Correction employee ${row.staff_member_id} must belong to a led team`).toBe(true);
                    const finance = await session(browser, "finance");
                    try {
                        const outside = await data<Employee>(await finance.api.get("my-profile"));
                        expect(ledEmployeeIds.has(outside.id), "Finance fixture must be outside Manager's led teams").toBe(false);
                        const records = await data<{ id: number }[]>(await finance.api.get("my-attendances", { params: { from: "2000-01-01", to: new Date().toISOString().slice(0, 10) } }));
                        expect(records.length, "Foreign employee must have seeded attendance").toBeGreaterThan(0);
                        expect((await actor.api.get(`attendances/${records[0].id}`)).status()).toBe(404);
                        const corrections = await data<{ id: number }[]>(await finance.api.get("my-attendance-corrections"));
                        for (const correction of corrections) expect((await actor.api.get(`attendance-corrections/${correction.id}`)).status()).toBe(403);
                    } finally {
                        await finance.close();
                    }
                }
                if (rows.length) await expect(actor.page.locator("tbody").getByText(rows[0].reason, { exact: true }).first()).toBeVisible();
                else await expect(actor.page.getByText("No corrections found", { exact: true })).toBeVisible();
            } finally {
                await actor.close();
            }
        });
    }

    for (const role of ["employee", "finance"] as const) {
        test(`${role} cannot read attendance administration or write holidays`, async ({ browser }) => {
            const actor = await session(browser, role);
            try {
                for (const [route, endpoint] of [
                    ["attendance-records", "attendances/all/paginated"],
                    ["attendance-periods", "attendance-periods"],
                    ["attendance-policy-mismatches", "attendance-policy-mismatches"],
                    ["hybrid-schedules", "hybrid-schedules"],
                    ["attendance-corrections", "attendance-corrections/all/paginated"],
                ]) {
                    expect((await actor.api.get(endpoint)).status(), `${role}: ${endpoint}`).toBe(403);
                    await actor.page.goto(`/admin/${route}`);
                    await expect(actor.page).toHaveURL(/\/admin\/dashboard$/);
                }
                // Holiday listing is authenticated self-service; writes require attendance-menu.
                expect((await actor.api.post("holiday-calendars", { data: { date: "2030-01-02", name: "Forbidden holiday", type: "national_holiday" } })).status()).toBe(403);
                await actor.page.goto("/admin/holiday-calendar");
                await expect(actor.page).toHaveURL(/\/admin\/dashboard$/);
            } finally {
                await actor.close();
            }
        });
    }

    for (const role of ["employee", "manager", "hr", "finance", "superadmin"] as const) {
        test(`${role} reads only personal attendance and personal hybrid schedule`, async ({ browser }) => {
            const actor = await session(browser, role);
            try {
                const profile = await data<Employee>(await actor.api.get("my-profile"));
                const response = actor.page.waitForResponse((res) => new URL(res.url()).pathname === "/api/v1/my-attendances" && res.request().method() === "GET");
                await actor.page.goto("/admin/attendance/my-attendances");
                const received = await response;
                expect(received.status()).toBe(200);
                const rows: { date: string; staff_member_id: number }[] = (await received.json()).data;
                for (const row of rows) expect(row.staff_member_id).toBe(profile.id);
                if (rows.length) {
                    await actor.page.getByRole("button", { name: "List", exact: true }).click();
                    const date = new Date(rows[rows.length - 1].date).toLocaleDateString("en-US", { month: "short", day: "numeric" });
                    await expect(actor.page.locator("main").getByText(date, { exact: true }).first()).toBeVisible();
                } else {
                    await actor.page.getByRole("button", { name: "List", exact: true }).click();
                    await expect(actor.page.getByText("Data kehadiran tidak ditemukan", { exact: true })).toBeVisible();
                }
                const identity = await data<{ employee_profile: { job_information: { work_location: string } } }>(await actor.api.get("me"));
                if (identity.employee_profile.job_information.work_location === "hybrid") {
                    const scheduleResponse = actor.page.waitForResponse((res) => new URL(res.url()).pathname === "/api/v1/my-hybrid-schedule");
                    await actor.page.goto("/admin/attendance/my-hybrid-schedule");
                    const scheduleResult = await scheduleResponse;
                    expect(scheduleResult.status()).toBe(200);
                    const schedule = (await scheduleResult.json()).data;
                    if (schedule.staff_member_id) expect(schedule.staff_member_id).toBe(profile.id);
                    if (!schedule?.overrides?.length) await expect(actor.page.getByText("No override requests", { exact: true })).toBeVisible();
                    else await expect(actor.page.getByText(schedule.overrides[0].date, { exact: true })).toBeVisible();
                } else {
                    await expect(actor.page.getByRole("button", { name: "Hybrid Schedule", exact: true })).toHaveCount(0);
                    expect((await actor.api.get("my-hybrid-schedule")).status()).toBe(200);
                }
                const corrections = await data<{ staff_member_id: number }[]>(await actor.api.get("my-attendance-corrections"));
                for (const correction of corrections) expect(correction.staff_member_id).toBe(profile.id);
            } finally {
                await actor.close();
            }
        });
    }

    for (const role of ["hr", "superadmin"] as const) {
        test(`${role} creates, updates and removes a visible holiday`, async ({ browser }) => {
            const actor = await session(browser, role);
            const name = `E2E ${role} holiday ${Date.now()}`;
            let id: number | undefined;
            try {
                const holiday = await data(await actor.api.post("holiday-calendars", { data: { date: "2030-01-02", name, type: "national_holiday" } }));
                id = holiday.id;
                const showHoliday = async (name: string) => {
                    const loaded = actor.page.waitForResponse((res) => new URL(res.url()).pathname.endsWith("/holiday-calendars") && res.request().method() === "GET");
                    await actor.page.goto("/admin/holiday-calendar");
                    let paginator = (await (await loaded).json()).data;
                    while (!paginator.data.some((item: { id: number }) => item.id === id) && paginator.current_page < paginator.last_page) {
                        const next = actor.page.waitForResponse((res) => new URL(res.url()).pathname.endsWith("/holiday-calendars") && res.request().method() === "GET");
                        await actor.page.locator("button").filter({ has: actor.page.locator("svg.lucide-chevron-right") }).click();
                        paginator = (await (await next).json()).data;
                    }
                    await expect(actor.page.locator("tbody").getByText(name, { exact: true })).toBeVisible();
                };
                await showHoliday(name);
                const renamed = `${name} updated`;
                expect((await data(await actor.api.put(`holiday-calendars/${id}`, { data: { date: "2030-01-02", name: renamed, type: "national_holiday" } }))).name).toBe(renamed);
                await showHoliday(renamed);
                await data(await actor.api.delete(`holiday-calendars/${id}`));
                id = undefined;
                await actor.page.reload();
                await expect(actor.page.getByText(renamed, { exact: true })).toHaveCount(0);
            } finally {
                if (id !== undefined) await data(await actor.api.delete(`holiday-calendars/${id}`));
                await actor.close();
            }
        });
    }
});
