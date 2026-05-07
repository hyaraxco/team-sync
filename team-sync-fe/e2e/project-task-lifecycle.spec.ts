/**
 * E2E: Project & Task Lifecycle — Full Stack Validation
 *
 * Covers: project list, task board, task CRUD, status transitions,
 * role-based access, assignee scoping (project member only).
 *
 * Run: bun run e2e -- project-task-lifecycle.spec.ts
 */

import { test, expect } from "./support/fixtures";
import { request, type APIRequestContext, type Page } from "@playwright/test";
import { loginAsRole, roleCredentials } from "./helpers/auth";
import { captureEvidence } from "./helpers/evidence";

const apiBase = (process.env.VITE_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1").replace(/\/?$/, "/");

const getToken = async (page: Page) => {
  const c = (await page.context().cookies()).find((c) => c.name === "token");
  if (!c?.value) throw new Error("No auth token");
  return c.value;
};

const makeApi = (token: string) =>
  request.newContext({
    baseURL: apiBase,
    extraHTTPHeaders: { Accept: "application/json", "Content-Type": "application/json", Authorization: `Bearer ${token}` },
  });

const ok = async <T>(res: Awaited<ReturnType<APIRequestContext["get"]>>, label: string): Promise<T> => {
  expect(res.ok(), `${label}: HTTP ${res.status()}`).toBeTruthy();
  const j = await res.json();
  expect(j.success, `${label}: success=false`).toBe(true);
  return j.data as T;
};

let projectId: number;
let taskId: number;
let mgrProfileId: number;
const ts = Date.now();

test.describe.serial("Project & Task Lifecycle", () => {
  test.setTimeout(120_000);

  // ── T1: Manager sees project list ──────────────────────────────

  test("T1: Manager sees project list", async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await loginAsRole(page, "manager");
    await page.goto("/admin/projects");
    await expect(page).toHaveURL(/\/admin\/projects$/);
    await page.waitForLoadState("networkidle");
    const main = page.locator("main");
    await expect(main).toBeVisible();
    expect((await main.textContent())?.length).toBeGreaterThan(20);
    await captureEvidence(page, "task-t1.png");
    await ctx.close();
  });

  // ── T2: Manager creates project via API ────────────────────────

  test("T2: Manager creates project via API", async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await loginAsRole(page, "manager");
    const api = await makeApi(await getToken(page));
    const me = await ok<any>(await api.get("me"), "me");
    mgrProfileId = me.employee_profile?.id ?? me.employeeProfile?.id;
    const name = `E2E Project ${ts}`;
    const d = await ok<{ id: number }>(
      await api.post("projects", {
        data: {
          name, type: "web_development", priority: "high", status: "active",
          start_date: new Date().toISOString().slice(0, 10),
          end_date: new Date(Date.now() + 90 * 86400000).toISOString().slice(0, 10),
          description: "E2E test.", project_leader_id: mgrProfileId, teams: [],
        },
      }),
      "Create project"
    );
    projectId = d.id;
    await page.goto("/admin/projects");
    await page.waitForLoadState("networkidle");
    await expect(page.getByText(name).first()).toBeVisible({ timeout: 10_000 });
    await captureEvidence(page, "task-t2.png");
    await api.dispose();
    await ctx.close();
  });

  // ── T3: Manager sees task board columns ────────────────────────

  test("T3: Manager sees task board columns", async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await loginAsRole(page, "manager");
    await page.goto(`/admin/projects/${projectId}`);
    await page.waitForLoadState("networkidle");
    await expect(page.getByText("To Do").first()).toBeVisible();
    await expect(page.getByText("In Progress").first()).toBeVisible();
    await expect(page.getByText("Review").first()).toBeVisible();
    await expect(page.getByText("Done").first()).toBeVisible();
    await expect(page.getByRole("button", { name: /Create New Task/i })).toBeVisible();
    await captureEvidence(page, "task-t3.png");
    await ctx.close();
  });

  // ── T4: Manager creates task via UI modal ──────────────────────

  test("T4: Manager creates task via UI modal", async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await loginAsRole(page, "manager");
    await page.goto(`/admin/projects/${projectId}`);
    await page.waitForLoadState("networkidle");
    await page.getByRole("button", { name: /Create New Task/i }).click();

    const modal = page.locator(".fixed, [role='dialog']").filter({ hasText: "Create New Task" });
    await expect(modal).toBeVisible({ timeout: 5_000 });

    const taskName = `E2E UI Task ${ts}`;
    await page.locator('input[placeholder="Enter task name"]').fill(taskName);
    await page.locator('textarea[placeholder="Enter task description"]').fill("Created via E2E.");
    await page.locator('input[type="date"]').fill(
      new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10)
    );
    await page.getByRole("button", { name: /^Create Task$/i }).click();
    await expect(page.getByText(taskName).first()).toBeVisible({ timeout: 10_000 });
    await captureEvidence(page, "task-t4.png");
    await ctx.close();
  });

  // ── T5: Manager creates assigned task via API ──────────────────

  test("T5: Manager creates assigned task via API (self=PL)", async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await loginAsRole(page, "manager");
    const api = await makeApi(await getToken(page));
    const name = `E2E Assigned ${ts}`;
    const d = await ok<{ id: number }>(
      await api.post("project-tasks", {
        data: {
          project_id: projectId, name, description: "Assigned to PL.",
          assignee_id: mgrProfileId, priority: "medium", status: "todo",
          due_date: new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10),
        },
      }),
      "Create assigned task"
    );
    taskId = d.id;
    await page.goto(`/admin/projects/${projectId}`);
    await page.waitForLoadState("networkidle");
    await expect(page.getByText(name).first()).toBeVisible({ timeout: 10_000 });
    await captureEvidence(page, "task-t5.png");
    await api.dispose();
    await ctx.close();
  });

  // ── T6: API rejects non-project-member assignee ────────────────

  test("T6: API rejects assigning to non-project-member", async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await loginAsRole(page, "manager");
    const api = await makeApi(await getToken(page));

    // Use HR to find employee profile
    const hrCtx = await browser.newContext();
    const hrPage = await hrCtx.newPage();
    await loginAsRole(hrPage, "hr");
    const hrApi = await makeApi(await getToken(hrPage));
    const list = await ok<{ data: Array<{ id: number; user?: { email?: string } }> }>(
      await hrApi.get("staff-members/all/paginated?row_per_page=50"), "Staff list"
    );
    const nonMember = list.data.find((s) => s.user?.email === roleCredentials.employee.email);
    expect(nonMember).toBeTruthy();

    const res = await api.post("project-tasks", {
      data: {
        project_id: projectId, name: `Fail ${ts}`,
        assignee_id: nonMember!.id, priority: "medium", status: "todo",
      },
    });
    expect(res.status()).toBe(403);
    const body = await res.json();
    expect(body.message || body.error).toContain("must be a member of the project");

    await captureEvidence(page, "task-t6.png");
    await hrApi.dispose();
    await hrCtx.close();
    await api.dispose();
    await ctx.close();
  });

  // ── T7: Staff sees seeded project ──────────────────────────────

  test("T7: Staff sees seeded project and task board", async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await loginAsRole(page, "employee");
    await page.goto("/admin/projects");
    await page.waitForLoadState("networkidle");
    await expect(page.getByText("Team Sync HRIS Platform").first()).toBeVisible({ timeout: 10_000 });
    await page.getByText("Team Sync HRIS Platform").first().click();
    await page.waitForLoadState("networkidle");
    await expect(page.getByText("Project Tasks").first()).toBeVisible();
    await expect(page.getByText("To Do").first()).toBeVisible();
    await captureEvidence(page, "task-t7.png");
    await ctx.close();
  });

  // ── T8: Staff full lifecycle: todo → in_progress → review ─────

  test("T8: Staff transitions own task todo->in_progress->review", async ({ browser }) => {
    // Manager creates a todo task assigned to employee in seeded project 1
    const mCtx = await browser.newContext();
    const mPage = await mCtx.newPage();
    await loginAsRole(mPage, "manager");
    const mApi = await makeApi(await getToken(mPage));

    const eCtx = await browser.newContext();
    const ePage = await eCtx.newPage();
    await loginAsRole(ePage, "employee");
    const eApi = await makeApi(await getToken(ePage));
    const me = await ok<any>(await eApi.get("me"), "me");
    const empId = me.employee_profile?.id ?? me.employeeProfile?.id;

    const tName = `E2E Staff Flow ${ts}`;
    const created = await ok<{ id: number }>(
      await mApi.post("project-tasks", {
        data: { project_id: 1, name: tName, assignee_id: empId, priority: "medium", status: "todo" },
      }),
      "Create task for staff"
    );
    const tid = created.id;
    await mApi.dispose();
    await mCtx.close();

    // Staff: todo -> in_progress
    let r = await eApi.put(`project-tasks/${tid}`, { data: { status: "in_progress" } });
    expect(r.ok(), `todo->in_progress: ${r.status()}`).toBeTruthy();

    // Staff: in_progress -> review
    r = await eApi.put(`project-tasks/${tid}`, { data: { status: "review" } });
    expect(r.ok(), `in_progress->review: ${r.status()}`).toBeTruthy();

    await ePage.goto("/admin/projects/1");
    await ePage.waitForLoadState("networkidle");
    await captureEvidence(ePage, "task-t8.png");
    await eApi.dispose();
    await eCtx.close();
  });

  // ── T9: Manager approves task review → done ────────────────────

  test("T9: Manager approves task review->done", async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await loginAsRole(page, "manager");
    const api = await makeApi(await getToken(page));

    const tasks = await ok<any[]>(await api.get("projects/1/tasks"), "tasks");
    const reviewTask = tasks.find((t: any) => t.status === "review");
    expect(reviewTask).toBeTruthy();

    const r = await api.put(`project-tasks/${reviewTask.id}`, { data: { status: "done" } });
    expect(r.ok(), `review->done: ${r.status()}`).toBeTruthy();

    await page.goto("/admin/projects/1");
    await page.waitForLoadState("networkidle");
    await captureEvidence(page, "task-t9.png");
    await api.dispose();
    await ctx.close();
  });

  // ── T10: Manager rejects task with reason ──────────────────────

  test("T10: Manager rejects task with reason", async ({ browser }) => {
    const mCtx = await browser.newContext();
    const mPage = await mCtx.newPage();
    await loginAsRole(mPage, "manager");
    const mApi = await makeApi(await getToken(mPage));

    const eCtx = await browser.newContext();
    const ePage = await eCtx.newPage();
    await loginAsRole(ePage, "employee");
    const eApi = await makeApi(await getToken(ePage));
    const me = await ok<any>(await eApi.get("me"), "me");
    const empId = me.employee_profile?.id ?? me.employeeProfile?.id;

    const tName = `E2E Reject Flow ${ts}`;
    const created = await ok<{ id: number }>(
      await mApi.post("project-tasks", {
        data: { project_id: 1, name: tName, assignee_id: empId, priority: "high", status: "todo" },
      }),
      "Create task"
    );
    const tid = created.id;

    // Staff: todo -> in_progress -> review
    await eApi.put(`project-tasks/${tid}`, { data: { status: "in_progress" } });
    await eApi.put(`project-tasks/${tid}`, { data: { status: "review" } });
    await eApi.dispose();
    await eCtx.close();

    // Manager: review -> rejected (with reason)
    const r = await mApi.put(`project-tasks/${tid}`, {
      data: { status: "rejected", rejected_reason: "Needs more unit tests." },
    });
    expect(r.ok(), `review->rejected: ${r.status()}`).toBeTruthy();

    await captureEvidence(mPage, "task-t10.png");
    await mApi.dispose();
    await mCtx.close();
  });

  // ── T11: Staff cannot transition other's task ──────────────────

  test("T11: Staff cannot transition task not assigned to them", async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await loginAsRole(page, "employee");
    const api = await makeApi(await getToken(page));

    const me = await ok<any>(await api.get("me"), "me");
    const myId = me.employee_profile?.id ?? me.employeeProfile?.id;

    const tasks = await ok<any[]>(await api.get("projects/1/tasks"), "tasks");
    const otherTask = tasks.find(
      (t: any) => t.assignee_id && t.assignee_id !== myId && (t.status === "todo" || t.status === "pending")
    );

    if (otherTask) {
      const r = await api.put(`project-tasks/${otherTask.id}`, { data: { status: "in_progress" } });
      expect(r.status()).toBeGreaterThanOrEqual(403);
    }

    await captureEvidence(page, "task-t11.png");
    await api.dispose();
    await ctx.close();
  });

  // ── T12: Staff cannot access non-member project ────────────────

  test("T12: Staff cannot access project they are not member of", async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await loginAsRole(page, "employee");
    const api = await makeApi(await getToken(page));

    // Try to access the E2E project (manager-only, no teams)
    const res = await api.get(`projects/${projectId}/tasks`);
    // Should be 403 (EnsureProjectMembership middleware)
    expect(res.status()).toBe(403);

    await captureEvidence(page, "task-t12.png");
    await api.dispose();
    await ctx.close();
  });

  // ── T13: Finance cannot access projects ────────────────────────

  test("T13: Finance is redirected from projects page", async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await loginAsRole(page, "finance");

    await page.goto("/admin/projects");
    // Finance has no project-menu permission → redirected to dashboard
    await expect(page).toHaveURL(/\/admin\/dashboard$/, { timeout: 10_000 });

    await captureEvidence(page, "task-t13.png");
    await ctx.close();
  });

  // ── T14: Manager deletes task via API ──────────────────────────

  test("T14: Manager can delete task via API", async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await loginAsRole(page, "manager");
    const api = await makeApi(await getToken(page));

    // Create a throwaway task to delete
    const d = await ok<{ id: number }>(
      await api.post("project-tasks", {
        data: {
          project_id: projectId, name: `E2E Delete Me ${ts}`,
          priority: "low", status: "todo",
        },
      }),
      "Create task to delete"
    );

    const delRes = await api.delete(`project-tasks/${d.id}`);
    expect(delRes.ok(), `Delete failed: ${delRes.status()}`).toBeTruthy();

    // Verify task is gone
    await page.goto(`/admin/projects/${projectId}`);
    await page.waitForLoadState("networkidle");
    await expect(page.getByText(`E2E Delete Me ${ts}`)).not.toBeVisible();

    await captureEvidence(page, "task-t14.png");
    await api.dispose();
    await ctx.close();
  });
});
