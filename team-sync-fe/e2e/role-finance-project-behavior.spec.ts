import { test, expect } from "./support/fixtures";
import { request, type Page } from "@playwright/test";
import { loginAsRole, roleCredentials } from "./helpers/auth";

const apiBase = (process.env.VITE_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1").replace(/\/?$/, "/");

const authenticatedApi = async (page: Page) => {
  const token = (await page.context().cookies()).find((cookie) => cookie.name === "token")?.value;
  expect(token, "Role login must provide the real bearer token").toBeTruthy();
  return request.newContext({
    baseURL: apiBase,
    extraHTTPHeaders: { Accept: "application/json", Authorization: `Bearer ${token}` },
  });
};

for (const role of ["finance", "superadmin"] as const) {
  test(`${role} reads payroll and previews THR with server-calculated results`, async ({ page }) => {
    await loginAsRole(page, role);
    const payrollResponse = page.waitForResponse((response) =>
      response.url().includes("/payrolls/all/paginated") && response.request().method() === "GET",
    );
    await page.goto("/admin/payroll");
    expect((await payrollResponse).status()).toBe(200);
    await expect(page.getByRole("heading", { name: "Payroll Dashboard", exact: true })).toBeVisible();

    await page.goto("/admin/payroll/thr");
    await expect(page.getByRole("heading", { name: "THR Management", exact: true })).toBeVisible();
    await page.getByRole("button", { name: "Generate THR", exact: true }).click();
    await page.locator("select").filter({ has: page.locator('option[value="idul_fitri"]') }).selectOption("idul_fitri");
    await page.locator('input[type="number"]').fill(String(new Date().getFullYear()));
    await page.locator('input[type="date"]').fill(`${new Date().getFullYear()}-12-25`);
    const simulationResponse = page.waitForResponse((response) =>
      response.url().endsWith("/thr/simulate") && response.request().method() === "POST",
    );
    await page.getByRole("button", { name: "Preview / Simulate", exact: true }).click();
    const response = await simulationResponse;
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body.success).toBe(true);
    expect(body.data.eligible_count).toBeGreaterThan(0);
    await expect(page.getByText("THR Simulation Preview", { exact: true })).toBeVisible();
    await expect(page.getByRole("status").filter({ hasText: body.data.calculation_notice })).toBeVisible();
    await expect(page.getByText(`Payment Deadline: ${body.data.payment_deadline}`, { exact: true })).toBeVisible();
  });
}

for (const role of ["hr", "manager", "employee"] as const) {
  test(`${role} cannot enter payroll administration or generate payroll and THR`, async ({ page }) => {
    await loginAsRole(page, role);
    const api = await authenticatedApi(page);
    try {
      await page.goto("/admin/payroll/create");
      await expect(page).toHaveURL(/\/admin\/dashboard$/);
      await expect(page.locator("main")).toBeVisible();
      const payroll = await api.post("payrolls/generate", {
        data: { salary_month: `${new Date().getFullYear()}-12` },
      });
      expect(payroll.status(), "Generation must be denied by authorization, not validation").toBe(403);
      const thr = await api.post("thr/generate", {
        data: {
          religion_event: "idul_fitri",
          year: new Date().getFullYear(),
          religion_holiday_date: `${new Date().getFullYear()}-12-25`,
        },
      });
      expect(thr.status(), "HR may read THR but must not generate it").toBe(403);
      if (role !== "hr") {
        await page.goto("/admin/payroll/thr");
        await expect(page).toHaveURL(/\/admin\/dashboard$/);
        expect((await api.get("thr")).status()).toBe(403);
      } else {
        const thrResponse = page.waitForResponse((response) =>
          /\/thr\?/.test(response.url()) && response.request().method() === "GET",
        );
        await page.goto("/admin/payroll/thr");
        expect((await thrResponse).status()).toBe(200);
        await expect(page.getByRole("heading", { name: "THR Management", exact: true })).toBeVisible();
        await expect(page.getByRole("button", { name: "Generate THR", exact: true })).toHaveCount(0);
      }
    } finally {
      await api.dispose();
    }
  });
}

test("Staff reads an owned paid payslip but Manager cannot retrieve or download it", async ({ page, browser }) => {
  await loginAsRole(page, "employee");
  const staffApi = await authenticatedApi(page);
  const managerContext = await browser.newContext();
  const managerPage = await managerContext.newPage();
  await loginAsRole(managerPage, "manager");
  const managerApi = await authenticatedApi(managerPage);
  try {
    const list = await staffApi.get("my-payslips?row_per_page=100");
    expect(list.status()).toBe(200);
    const slips = (await list.json()).data.data;
    expect(slips.length, "Fixture requires a paid payroll detail for agung@teamsync.com").toBeGreaterThan(0);
    const slip = slips[0];
    expect(slip.employee_email).toBe(roleCredentials.employee.email);
    const ownedResponse = page.waitForResponse((response) => new URL(response.url()).pathname === new URL(`my-payslips/${slip.id}`, apiBase).pathname && response.request().method() === "GET");
    await page.goto(`/admin/my-payslips/${slip.id}`);
    expect((await ownedResponse).status()).toBe(200);
    await expect(page.getByText(slip.employee_name, { exact: true }).first()).toBeVisible();
    await expect(page.getByTestId("payslip-detail-download")).toBeVisible();

    // Ownership deliberately returns 404 rather than revealing another employee's payslip.
    const foreignResponse = managerPage.waitForResponse((response) => new URL(response.url()).pathname === new URL(`my-payslips/${slip.id}`, apiBase).pathname && response.request().method() === "GET");
    await managerPage.goto(`/admin/my-payslips/${slip.id}`);
    expect((await foreignResponse).status()).toBe(404);
    await expect(managerPage.getByText("Payroll detail not found", { exact: true })).toBeVisible();
    expect((await managerApi.get(`payslips/${slip.id}/download`)).status()).toBe(404);
    const owned = await staffApi.get(`my-payslips/${slip.id}`);
    expect(owned.status()).toBe(200);
    expect((await owned.json()).data.employee_email).toBe(roleCredentials.employee.email);
  } finally {
    await staffApi.dispose();
    await managerApi.dispose();
    await managerContext.close();
  }
});

test("Manager accesses a non-led project while nonmember Staff is denied tasks and project editing", async ({ browser }) => {
  const adminContext = await browser.newContext();
  const adminPage = await adminContext.newPage();
  await loginAsRole(adminPage, "superadmin");
  const adminApi = await authenticatedApi(adminPage);
  const managerContext = await browser.newContext();
  const staffContext = await browser.newContext();
  const managerPage = await managerContext.newPage();
  const staffPage = await staffContext.newPage();
  await loginAsRole(managerPage, "manager");
  await loginAsRole(staffPage, "employee");
  const managerApi = await authenticatedApi(managerPage);
  const staffApi = await authenticatedApi(staffPage);
  let projectId: number | undefined;
  try {
    const me = await adminApi.get("me");
    expect(me.status()).toBe(200);
    const leaderId = (await me.json()).data.employee_profile.id;
    const name = `Role boundary project ${Date.now()}`;
    const created = await adminApi.post("projects", {
      data: {
        name, type: "web_development", priority: "medium", status: "active",
        start_date: new Date().toISOString().slice(0, 10),
        end_date: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
        description: "Role boundary fixture", project_leader_id: leaderId, teams: [],
      },
    });
    expect(created.status()).toBe(201);
    const project = (await created.json()).data;
    projectId = project.id;
    const tasksResponse = managerPage.waitForResponse((response) => new URL(response.url()).pathname.endsWith("/project-tasks") && new URL(response.url()).searchParams.get("project_id") === String(project.id));
    await managerPage.goto(`/admin/projects/${project.id}`);
    expect((await tasksResponse).status()).toBe(200);
    await expect(managerPage.getByRole("heading", { name, exact: true })).toBeVisible();
    await expect(managerPage.getByRole("heading", { name: "Project Tasks", exact: true })).toBeVisible();
    const persisted = await managerApi.get(`projects/${project.id}`);
    expect(persisted.status()).toBe(200);
    expect((await persisted.json()).data.leader.id).toBe(leaderId);

    expect((await staffApi.get(`projects/${project.id}/tasks`)).status()).toBe(403);
    await staffPage.goto(`/admin/projects/${project.id}/edit`);
    await expect(staffPage).toHaveURL(/\/admin\/dashboard$/);
    await expect(staffPage.locator("main")).toBeVisible();
    expect((await staffApi.put(`projects/${project.id}`, { data: { name: "Unauthorized change" } })).status()).toBe(403);
    const unchanged = await adminApi.get(`projects/${project.id}`);
    expect(unchanged.status()).toBe(200);
    expect((await unchanged.json()).data.name).toBe(name);
  } finally {
    if (projectId !== undefined) {
      const deleted = await adminApi.delete(`projects/${projectId}`);
      expect(deleted.ok(), "Remove only the project created by this test").toBe(true);
    }
    await adminApi.dispose();
    await managerApi.dispose();
    await staffApi.dispose();
    await adminContext.close();
    await managerContext.close();
    await staffContext.close();
  }
});

for (const role of ["employee", "manager", "hr", "finance", "superadmin"] as const) {
  test(`${role} sees only their own payslip cards or the server-confirmed empty state`, async ({ page }) => {
    await loginAsRole(page, role);
    const responsePromise = page.waitForResponse((response) =>
      new URL(response.url()).pathname.endsWith("/my-payslips") && response.request().method() === "GET",
    );
    await page.goto("/admin/my-payroll");
    const response = await responsePromise;
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body.success).toBe(true);
    expect(Array.isArray(body.data.data)).toBe(true);
    await expect(page.getByRole("heading", { name: "My Payroll", exact: true, level: 2 })).toBeVisible();
    await expect(page.locator('[data-testid^="my-payroll-card-"]')).toHaveCount(body.data.data.length);
    for (const slip of body.data.data) {
      expect(slip.employee_email).toBe(roleCredentials[role].email);
      const card = page.getByTestId(`my-payroll-card-${slip.id}`);
      await expect(card).toBeVisible();
      await expect(card).toContainText("Paid");
      await expect(page.getByTestId(`my-payroll-view-${slip.id}`)).toBeVisible();
      await expect(page.getByTestId(`my-payroll-download-${slip.id}`)).toBeVisible();
    }
    if (body.data.data.length === 0) {
      expect(body.data.meta.total).toBe(0);
      await expect(page.getByTestId("my-payroll-empty-processing")).toBeVisible();
    } else {
      await expect(page.getByTestId("my-payroll-empty")).toHaveCount(0);
    }
  });
}

for (const role of ["finance", "superadmin"] as const) {
  test(`${role} uses payroll settings, readiness, adjustments, policies, comparison and report export`, async ({ page }) => {
    await loginAsRole(page, role);
    const api = await authenticatedApi(page);
    let policyId: number | undefined;
    try {
      const settingsResponse = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith("/payroll-settings") && response.request().method() === "GET");
      await page.goto("/admin/payroll/settings");
      const settings = await settingsResponse;
      expect(settings.status()).toBe(200);
      const original = (await settings.json()).data;
      await expect(page.getByTestId("payroll-settings-payday-day")).toHaveValue(String(original.payday_day));
      const savedResponse = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith("/payroll-settings") && response.request().method() === "PUT");
      await page.getByTestId("payroll-settings-save").click();
      const saved = await savedResponse;
      expect(saved.status()).toBe(200);
      expect((await saved.json()).data.payday_day).toBe(original.payday_day);

      const readinessResponse = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith("/payrolls/readiness-dashboard"));
      await page.goto("/admin/payroll/readiness");
      const readiness = await readinessResponse;
      expect(readiness.status()).toBe(200);
      const readinessData = (await readiness.json()).data;
      await expect(page.getByTestId("readiness-total")).toHaveText(String(readinessData.summary.total_employees));
      await expect(page.getByTestId("readiness-ready")).toHaveText(String(readinessData.summary.ready_employees));

      const adjustmentsResponse = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith("/payroll-adjustments"));
      await page.goto("/admin/payroll/adjustments");
      const adjustments = await adjustmentsResponse;
      expect(adjustments.status()).toBe(200);
      const adjustmentData = (await adjustments.json()).data;
      await expect(page.getByRole("heading", { name: "Payroll Adjustment Queue", exact: true })).toBeVisible();
      await expect(page.locator("tbody tr")).toHaveCount(adjustmentData.data.length);
      if (adjustmentData.data.length === 0) {
        expect(adjustmentData.total).toBe(0);
        await expect(page.getByText("Data penyesuaian payroll kosong", { exact: true })).toBeVisible();
      }
      const filteredResponse = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith("/payroll-adjustments") && new URL(response.url()).searchParams.get("status") === "pending");
      await page.getByRole("combobox").selectOption("pending");
      const filtered = await filteredResponse;
      expect(filtered.status()).toBe(200);
      for (const adjustment of (await filtered.json()).data.data) expect(adjustment.status).toBe("pending");

      const policiesResponse = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith("/payroll-approval-policies") && response.request().method() === "GET");
      await page.goto("/admin/payroll/approval-matrix");
      expect((await policiesResponse).status()).toBe(200);
      await page.getByRole("button", { name: "Add Policy", exact: true }).click();
      const form = page.locator("form").filter({ has: page.getByRole("button", { name: "Save Policy", exact: true }) });
      const policyName = `Role policy ${role} ${Date.now()}`;
      await form.locator('input[type="text"]').first().fill(policyName);
      await form.locator('input[placeholder="finance-manager"]').fill("finance");
      await form.getByLabel("Active policy").uncheck();
      const createdResponse = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith("/payroll-approval-policies") && response.request().method() === "POST");
      await form.getByRole("button", { name: "Save Policy", exact: true }).click();
      const created = await createdResponse;
      expect(created.status()).toBe(201);
      const policy = (await created.json()).data;
      policyId = policy.id;
      expect(policy.name).toBe(policyName);
      expect(policy.is_active).toBe(false);
      await expect(page.getByRole("row").filter({ hasText: policyName })).toContainText("Inactive");

      const comparisonResponse = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith("/payrolls/compare"));
      await page.goto("/admin/payroll/comparison");
      const comparison = await comparisonResponse;
      expect(comparison.status()).toBe(200);
      const comparisonData = (await comparison.json()).data;
      await expect(page.getByRole("heading", { name: "Month-over-Month Comparison", exact: true })).toBeVisible();
      if (comparisonData.month1.found || comparisonData.month2.found) {
        await expect(page.getByRole("columnheader", { name: comparisonData.month1.period, exact: true })).toBeVisible();
        await expect(page.getByRole("row").filter({ hasText: "Employee Count" })).toContainText(String(comparisonData.month2.employee_count));
      } else {
        await expect(page.getByText("No payroll data found", { exact: true })).toBeVisible();
      }

      await page.goto("/admin/payroll");
      await page.getByTestId("payroll-export-report-open").click();
      const exportResponse = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith("/payrolls/export-report"));
      const downloadPromise = page.waitForEvent("download");
      await page.getByTestId("payroll-report-submit").click();
      const exported = await exportResponse;
      expect(exported.status()).toBe(200);
      expect((await exported.body()).length).toBeGreaterThan(0);
      const download = await downloadPromise;
      expect(download.suggestedFilename()).toMatch(/\.xlsx$/);
      expect(await download.failure()).toBeNull();
    } finally {
      if (policyId !== undefined) expect((await api.delete(`payroll-approval-policies/${policyId}`)).ok()).toBe(true);
      await api.dispose();
    }
  });
}

for (const role of ["employee", "manager", "hr"] as const) {
  test(`${role} respects payroll settings, policy, adjustment, comparison and export boundaries`, async ({ page }) => {
    await loginAsRole(page, role);
    const api = await authenticatedApi(page);
    const month = new Date().toISOString().slice(0, 7);
    try {
      for (const route of ["settings", "approval-matrix", "adjustments", "comparison"]) {
        await page.goto(`/admin/payroll/${route}`);
        await expect(page).toHaveURL(/\/admin\/dashboard$/);
      }
      for (const endpoint of ["payroll-settings", "payroll-settings/history", "payroll-approval-policies", "payroll-adjustments", `payrolls/compare?month1=${month}&month2=${month}`, `payrolls/export-report?period_type=monthly&month=${month}&status=all&report_type=summary`]) {
        expect((await api.get(endpoint)).status(), `${role}: ${endpoint}`).toBe(403);
      }
      expect((await api.put("payroll-settings", { data: { payday_day: 25 } })).status()).toBe(403);
      expect((await api.post("payroll-approval-policies", { data: { name: "Unauthorized", min_amount: 0, required_role: "finance", approval_order: 1 } })).status()).toBe(403);
      expect((await api.post("payroll-adjustments/1/approve")).status()).toBe(403);
      expect((await api.put("payroll-approval-policies/1", { data: { name: "Unauthorized" } })).status()).toBe(403);
      expect((await api.delete("payroll-approval-policies/1")).status()).toBe(403);
      const readinessResponse = await api.get(`payrolls/readiness-dashboard?salary_month=${month}`);
      expect(readinessResponse.status()).toBe(role === "hr" ? 200 : 403);
      if (role === "hr") {
        const responsePromise = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith("/payrolls/readiness-dashboard"));
        await page.goto("/admin/payroll/readiness");
        const response = await responsePromise;
        expect(response.status()).toBe(200);
        await expect(page.getByTestId("readiness-total")).toHaveText(String((await response.json()).data.summary.total_employees));
      } else {
        await page.goto("/admin/payroll/readiness");
        await expect(page).toHaveURL(/\/admin\/dashboard$/);
      }
    } finally {
      await api.dispose();
    }
  });
}

test("HR oversees project tasks read-only while Finance starts and submits its assigned task", async ({ page, browser }) => {
  await loginAsRole(page, "manager");
  const managerApi = await authenticatedApi(page);
  const financeContext = await browser.newContext();
  const hrContext = await browser.newContext();
  const financePage = await financeContext.newPage();
  const hrPage = await hrContext.newPage();
  await loginAsRole(financePage, "finance");
  await loginAsRole(hrPage, "hr");
  const financeApi = await authenticatedApi(financePage);
  const hrApi = await authenticatedApi(hrPage);
  let taskId: number | undefined;
  try {
    const me = await financeApi.get("me");
    expect(me.status()).toBe(200);
    const financeId = (await me.json()).data.employee_profile.id;
    const name = `Finance member task ${Date.now()}`;
    const payload = { project_id: 1, name, priority: "medium", status: "todo", assignee_id: financeId };
    const created = await managerApi.post("project-tasks", { data: payload });
    expect(created.status()).toBe(201);
    taskId = (await created.json()).data.id;
    for (const actorPage of [hrPage, financePage]) {
      const tasksResponse = actorPage.waitForResponse((response) => new URL(response.url()).pathname.endsWith("/project-tasks") && new URL(response.url()).searchParams.get("project_id") === "1");
      await actorPage.goto("/admin/projects/1");
      expect((await tasksResponse).status()).toBe(200);
      await expect(actorPage.getByText(name, { exact: true })).toBeVisible();
      await expect(actorPage.getByRole("button", { name: "Create New Task", exact: true })).toHaveCount(0);
    }
    expect((await hrApi.post("project-tasks", { data: payload })).status()).toBe(403);
    expect((await hrApi.put(`project-tasks/${taskId}`, { data: { status: "in_progress" } })).status()).toBe(403);
    expect((await hrApi.delete(`project-tasks/${taskId}`)).status()).toBe(403);
    expect((await financeApi.post("project-tasks", { data: payload })).status()).toBe(403);
    expect((await financeApi.put(`project-tasks/${taskId}`, { data: { name: "Unauthorized rename" } })).status()).toBe(403);
    expect((await financeApi.delete(`project-tasks/${taskId}`)).status()).toBe(403);
    await financePage.getByText(name, { exact: true }).click();
    for (const [button, status] of [["Start Task", "in_progress"], ["Task Selesai - Submit for Review", "review"]] as const) {
      const updateResponse = financePage.waitForResponse((response) => new URL(response.url()).pathname.endsWith(`/project-tasks/${taskId}`) && response.request().method() === "POST");
      await financePage.getByRole("button", { name: button, exact: true }).click();
      const updated = await updateResponse;
      expect(updated.status()).toBe(200);
      expect((await updated.json()).data.status).toBe(status);
    }
    const persisted = await financeApi.get(`project-tasks/${taskId}`);
    expect(persisted.status()).toBe(200);
    expect((await persisted.json()).data).toMatchObject({ name, status: "review", assignee_id: financeId });
  } finally {
    // Restore only this test's task to deletable todo through the supported workflow.
    if (taskId !== undefined) {
      const current = await managerApi.get(`project-tasks/${taskId}`);
      expect(current.status()).toBe(200);
      let status = (await current.json()).data.status;
      if (status === "in_progress") {
        expect((await financeApi.put(`project-tasks/${taskId}`, { data: { status: "review" } })).status()).toBe(200);
        status = "review";
      }
      if (status === "review") {
        expect((await managerApi.put(`project-tasks/${taskId}`, { data: { status: "rejected", rejected_reason: "E2E cleanup" } })).status()).toBe(200);
        status = "rejected";
      }
      if (status === "rejected") {
        const manager = await managerApi.get("me");
        expect(manager.status()).toBe(200);
        const managerId = (await manager.json()).data.employee_profile.id;
        expect((await managerApi.put(`project-tasks/${taskId}`, { data: { assignee_id: managerId } })).status()).toBe(200);
      }
      expect((await managerApi.delete(`project-tasks/${taskId}`)).ok()).toBe(true);
    }
    await managerApi.dispose();
    await financeApi.dispose();
    await hrApi.dispose();
    await financeContext.close();
    await hrContext.close();
  }
});
