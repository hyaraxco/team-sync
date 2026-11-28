import { test, expect } from "./support/fixtures";
import { loginAsRole, type RoleName } from "./helpers/auth";
import type { Browser, BrowserContext, Page } from "@playwright/test";

const apiBase = (process.env.VITE_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1").replace(/\/$/, "");
const roles: RoleName[] = ["employee", "manager", "hr", "finance", "superadmin"];

interface PerformanceActor {
  context: BrowserContext;
  page: Page;
  user: { id: number };
  profile: { id: number };
}

async function actor(browser: Browser, role: RoleName) {
  const context = await browser.newContext();
  try {
    const page = await context.newPage();
    await loginAsRole(page, role);
    const token = (await context.cookies()).find((cookie) => cookie.name === "token")?.value;
    expect(token, `${role} login must issue a token`).toBeTruthy();
    await context.setExtraHTTPHeaders({ Authorization: `Bearer ${token}`, Accept: "application/json" });
    const response = await page.request.get(`${apiBase}/me`);
    expect(response.status()).toBe(200);
    const user = (await response.json()).data;
    const profile = user.employee_profile ?? user.employeeProfile;
    expect(profile?.id, `${role} must have an employee profile`).toBeTruthy();
    return { context, page, user, profile };
  } catch (error) {
    await context.close();
    throw error;
  }
}

async function visitList(page: Page, route: string, endpoint: string, heading: string) {
  const pending = page.waitForResponse((response) =>
    new URL(response.url()).pathname === `${new URL(apiBase).pathname}/performance/${endpoint}` && response.request().method() === "GET",
  );
  await page.goto(`/admin/performance/${route}`);
  const response = await pending;
  expect(response.status()).toBe(200);
  await expect(page.locator("main").getByRole("heading", { name: heading, exact: true })).toBeVisible();
  return (await response.json()).data.data;
}

// Outcome-rule/template CRUD, reviewer overrides and TOPSIS remain in performance-{outcome-rules,templates,reviewer-override,topsis}.spec.ts.
test.describe("Five-role performance behavior", () => {
  test.setTimeout(120_000);

  for (const role of roles) {
    test(`${role} creates an own goal and acknowledges feedback addressed to their employee profile`, async ({ browser }) => {
      const recipient = await actor(browser, role);
      const giver = await actor(browser, role === "hr" ? "superadmin" : "hr");
      try {
        const title = `RBAC ${role} goal ${Date.now()}`;
        await visitList(recipient.page, "goals/my-goals", "goals/my-goals", "My Goals");
        await recipient.page.getByRole("button", { name: "Create Goal", exact: true }).first().click();
        const form = recipient.page.locator("form");
        await form.locator('input[type="text"]').first().fill(title);
        await form.locator('input[type="date"]').nth(0).fill("2026-01-01");
        await form.locator('input[type="date"]').nth(1).fill("2026-01-02");
        const created = recipient.page.waitForResponse((response) =>
          response.url() === `${apiBase}/performance/goals` && response.request().method() === "POST",
        );
        await form.getByRole("button", { name: "Create Goal", exact: true }).click();
        const createResponse = await created;
        expect(createResponse.status()).toBe(201);
        const goal = (await createResponse.json()).data;
        expect(goal.staff_member_id).toBe(recipient.profile.id);
        await expect(recipient.page.getByRole("heading", { name: title, exact: true })).toBeVisible();
        const persisted = await recipient.page.request.get(`${apiBase}/performance/goals/${goal.id}`);
        expect(persisted.status()).toBe(200);
        expect((await persisted.json()).data).toMatchObject({ title, staff_member_id: recipient.profile.id });

        const content = `RBAC feedback for ${role} ${Date.now()}`;
        await giver.page.goto("/admin/performance/feedback/give");
        await giver.page.locator('select[name="staff_member_id"]').selectOption(String(recipient.profile.id));
        await giver.page.locator('textarea[name="content"]').fill(content);
        const sent = giver.page.waitForResponse((response) =>
          response.url() === `${apiBase}/performance/feedback` && response.request().method() === "POST",
        );
        await giver.page.locator('form button[type="submit"]').click();
        const sendResponse = await sent;
        expect(sendResponse.status()).toBe(201);
        const feedback = (await sendResponse.json()).data;
        expect(feedback.staff_member_id).toBe(recipient.profile.id);
        await expect(giver.page.getByText("Feedback sent", { exact: true })).toBeVisible();
        const received = await visitList(recipient.page, "feedback/received", "feedback/received", "Feedback Received");
        expect(received).toEqual(expect.arrayContaining([expect.objectContaining({ id: feedback.id, content })]));
        const card = recipient.page.locator("div.ml-16").filter({ hasText: content });
        await expect(card.getByText(content, { exact: true })).toBeVisible();
        const acknowledged = recipient.page.waitForResponse((response) =>
          response.url() === `${apiBase}/performance/feedback/${feedback.id}/acknowledge` && response.request().method() === "POST",
        );
        await card.getByRole("button", { name: "Acknowledge", exact: true }).click();
        expect((await acknowledged).status()).toBe(200);
        await expect(card.getByText("Acknowledged", { exact: true })).toBeVisible();
        const saved = await recipient.page.request.get(`${apiBase}/performance/feedback/${feedback.id}`);
        expect(saved.status()).toBe(200);
        expect((await saved.json()).data.acknowledged_at).toBeTruthy();
        const forbidden = await giver.page.request.post(`${apiBase}/performance/feedback/${feedback.id}/acknowledge`);
        expect(forbidden.status()).toBe(403);
        const removed = await recipient.page.request.delete(`${apiBase}/performance/goals/${goal.id}`);
        expect(removed.status()).toBe(200);
      } finally {
        await Promise.all([recipient.context.close(), giver.context.close()]);
      }
    });
  }

  test("Staff self-assessment advances through assigned Manager review and HR calibration while all five roles see only their own reviews", async ({ browser }) => {
    const actors: (PerformanceActor & { role: RoleName })[] = [];
    try {
      for (const role of roles) actors.push({ role, ...await actor(browser, role) });
      const employee = actors.find((entry) => entry.role === "employee")!;
      const manager = actors.find((entry) => entry.role === "manager")!;
      const hr = actors.find((entry) => entry.role === "hr")!;
      const cycleName = `RBAC review journey ${Date.now()}`;
      const created = await hr.page.request.post(`${apiBase}/performance/cycles`, { data: {
        name: cycleName, cycle_type: "annual", start_date: "2026-01-01", end_date: "2099-12-31",
        review_period_start: "2025-01-01", review_period_end: "2025-12-31",
      } });
      expect(created.status()).toBe(201);
      const cycle = (await created.json()).data;
      const activated = await hr.page.request.put(`${apiBase}/performance/cycles/${cycle.id}`, { data: { status: "active" } });
      expect(activated.status()).toBe(200);
      expect((await activated.json()).data.status).toBe("active");
      const generated = await hr.page.request.post(`${apiBase}/performance/cycles/${cycle.id}/generate-reviews`);
      expect(generated.status()).toBe(200);
      expect((await generated.json()).data.generated_count).toBeGreaterThanOrEqual(5);
      await hr.page.goto(`/admin/performance/cycles/${cycle.id}`);
      await expect(hr.page.getByRole("heading", { name: cycleName, exact: true })).toBeVisible();
      await expect(hr.page.getByText("Generated Reviews", { exact: true })).toBeVisible();

      let employeeReviewId = 0;
      let financeReviewId = 0;
      for (const entry of actors) {
        const reviews = await visitList(entry.page, "reviews/my-reviews", "reviews/my-reviews", "My Performance Reviews");
        expect(reviews.length).toBeGreaterThan(0);
        expect(reviews.every((review: { staff_member_id: number }) => review.staff_member_id === entry.profile.id)).toBe(true);
        const own = reviews.find((review: { cycle_id: number }) => review.cycle_id === cycle.id);
        expect(own, `${entry.role} must receive a review in the generated cycle`).toBeTruthy();
        await expect(entry.page.getByRole("heading", { name: cycleName, exact: true })).toBeVisible();
        if (entry.role === "employee") employeeReviewId = own.id;
        if (entry.role === "finance") financeReviewId = own.id;
      }
      const assigned = await hr.page.request.put(`${apiBase}/performance/reviews/${employeeReviewId}/assign-reviewer`, {
        data: { reviewer_id: manager.profile.id },
      });
      expect(assigned.status()).toBe(200);
      expect((await assigned.json()).data.reviewer_id).toBe(manager.profile.id);
      const sectionsResponse = await employee.page.request.get(`${apiBase}/performance/reviews/sections`);
      expect(sectionsResponse.status()).toBe(200);
      const sections = (await sectionsResponse.json()).data;
      expect(sections.length, "Seed PerformanceReviewSectionSeeder before running the review journey").toBeGreaterThan(0);
      const responses = sections.map((section: { id: number }) => ({ section_id: section.id, rating: 4 }));
      for (const entry of actors.filter((entry) => ["employee", "finance"].includes(entry.role))) {
        for (const operation of ["manager-assessment", "calibrate"]) {
          const denied = await entry.page.request.post(`${apiBase}/performance/reviews/${employeeReviewId}/${operation}`, { data: { responses } });
          expect(denied.status(), `${entry.role} cannot ${operation}`).toBe(403);
        }
      }
      const notOwner = actors.find((entry) => entry.role === "finance")!;
      const deniedReview = await notOwner.page.request.get(`${apiBase}/performance/reviews/${employeeReviewId}`);
      expect(deniedReview.status()).toBe(403);

      await employee.page.goto(`/admin/performance/reviews/${employeeReviewId}`);
      await employee.page.getByRole("button", { name: "Self Assessment", exact: true }).click();
      const selfRatings = employee.page.getByRole("button", { name: "4", exact: true });
      await expect(selfRatings).toHaveCount(sections.length);
      for (const rating of await selfRatings.all()) await rating.click();
      const selfSubmitted = employee.page.waitForResponse((response) => response.url().endsWith(`/reviews/${employeeReviewId}/self-assessment`) && response.request().method() === "POST");
      await employee.page.getByRole("button", { name: "Submit Self Assessment", exact: true }).click();
      await employee.page.getByRole("button", { name: "Yes, Submit", exact: true }).click();
      const selfResponse = await selfSubmitted;
      expect(selfResponse.status()).toBe(200);
      expect((await selfResponse.json()).data.status).toBe("pending_manager");
      await expect(employee.page.getByRole("button", { name: "Submit Self Assessment", exact: true })).toHaveCount(0);

      const teamReviews = await visitList(manager.page, "reviews/team-reviews", "reviews/team-reviews", "Team Performance Reviews");
      expect(teamReviews.length).toBeGreaterThan(0);
      expect(teamReviews.every((review: { reviewer_id: number }) => review.reviewer_id === manager.profile.id)).toBe(true);
      expect(teamReviews).toEqual(expect.arrayContaining([expect.objectContaining({ id: employeeReviewId, status: "pending_manager" })]));
      await expect(manager.page.locator("table tbody tr").filter({ hasText: cycleName }).first()).toBeVisible();
      await manager.page.goto(`/admin/performance/reviews/${employeeReviewId}`);
      await manager.page.getByRole("button", { name: "Manager Assessment", exact: true }).click();
      const managerRatings = manager.page.getByRole("button", { name: "4", exact: true });
      await expect(managerRatings).toHaveCount(sections.length);
      for (const rating of await managerRatings.all()) await rating.click();
      const managerSubmitted = manager.page.waitForResponse((response) => response.url().endsWith(`/reviews/${employeeReviewId}/manager-assessment`) && response.request().method() === "POST");
      await manager.page.getByRole("button", { name: "Submit Manager Assessment", exact: true }).click();
      await manager.page.getByRole("button", { name: "Yes, Submit", exact: true }).click();
      const managerResponse = await managerSubmitted;
      expect(managerResponse.status()).toBe(200);
      expect((await managerResponse.json()).data.status).toBe("pending_calibration");
      await expect(manager.page.getByRole("button", { name: "Submit Manager Assessment", exact: true })).toHaveCount(0);
      const pending = await visitList(hr.page, "reviews/pending-calibration", "reviews/pending-calibration", "Pending Calibration");
      expect(pending).toEqual(expect.arrayContaining([expect.objectContaining({ id: employeeReviewId })]));
      const managerCalibration = await manager.page.request.post(`${apiBase}/performance/reviews/${employeeReviewId}/calibrate`, { data: { responses } });
      expect(managerCalibration.status()).toBe(403);

      await hr.page.goto(`/admin/performance/reviews/${employeeReviewId}`);
      await expect(hr.page.locator("main p").getByText(cycleName, { exact: true }).first()).toBeVisible();
      await hr.page.getByRole("button", { name: "Calibration", exact: true }).click();
      const calibrated = hr.page.waitForResponse((response) => response.url().endsWith(`/reviews/${employeeReviewId}/calibrate`) && response.request().method() === "POST");
      await hr.page.getByRole("button", { name: "Finalize Calibration", exact: true }).click();
      await hr.page.getByRole("button", { name: "Yes, Submit", exact: true }).click();
      const calibrationResponse = await calibrated;
      expect(calibrationResponse.status()).toBe(200);
      expect((await calibrationResponse.json()).data).toMatchObject({ status: "completed", calibrated_by: hr.user.id });
      await expect(hr.page.getByText("Final Calibrated Rating", { exact: true })).toBeVisible();
      const persisted = await employee.page.request.get(`${apiBase}/performance/reviews/${employeeReviewId}`);
      expect(persisted.status()).toBe(200);
      const review = (await persisted.json()).data;
      expect(review).toMatchObject({ status: "completed", calibrated_by: hr.user.id });
      expect(review.responses.length).toBeGreaterThan(0);
      expect(review.responses.every((response: { self_rating: number; manager_rating: number }) => Number(response.self_rating) === 4 && Number(response.manager_rating) === 4)).toBe(true);
      await employee.page.reload();
      await employee.page.getByRole("button", { name: "Calibration", exact: true }).click();
      await expect(employee.page.getByText("Final Calibrated Rating", { exact: true })).toBeVisible();
      const cancelled = await hr.page.request.put(`${apiBase}/performance/cycles/${cycle.id}`, { data: { status: "cancelled" } });
      expect(cancelled.status()).toBe(200);
      const reassigned = await hr.page.request.put(`${apiBase}/performance/reviews/${financeReviewId}/assign-reviewer`, { data: { reviewer_id: hr.profile.id } });
      expect(reassigned.status()).toBe(200);
      const unrelatedReview = await manager.page.request.get(`${apiBase}/performance/reviews/${financeReviewId}`);
      expect(unrelatedReview.status(), "Manager must not read an unrelated review assigned to HR").toBe(403);
    } finally {
      await Promise.all(actors.map((entry) => entry.context.close()));
    }
  });

  for (const role of ["employee", "finance"] as const) {
    test(`${role} is denied manager scope, feedback giving, cycle management and calibration by both API and frontend`, async ({ browser }) => {
      const restricted = await actor(browser, role);
      try {
        for (const endpoint of ["goals/team-goals", "reviews/team-reviews", "reviews/pending-calibration", "cycles", "feedback/given"]) {
          const response = await restricted.page.request.get(`${apiBase}/performance/${endpoint}`);
          expect(response.status(), `${role}: GET ${endpoint}`).toBe(403);
        }
        const create = await restricted.page.request.post(`${apiBase}/performance/cycles`, { data: {
          name: "Unauthorized cycle", cycle_type: "annual", start_date: "2026-01-01", end_date: "2026-12-31",
          review_period_start: "2025-01-01", review_period_end: "2025-12-31",
        } });
        expect(create.status()).toBe(403);
        const feedback = await restricted.page.request.post(`${apiBase}/performance/feedback`, { data: {
          staff_member_id: restricted.profile.id, feedback_type: "positive", content: "Unauthorized feedback",
        } });
        expect(feedback.status()).toBe(403);
        for (const route of ["goals/team-goals", "reviews/team-reviews", "reviews/pending-calibration", "cycles/create", "feedback/give"]) {
          await restricted.page.goto(`/admin/performance/${route}`);
          await expect(restricted.page).toHaveURL(/\/admin\/dashboard$/);
        }
      } finally {
        await restricted.context.close();
      }
    });
  }

  test("Manager cannot administer cycles or access the calibration queue through API or routes", async ({ browser }) => {
    const manager = await actor(browser, "manager");
    try {
      for (const endpoint of ["cycles", "reviews/pending-calibration"]) {
        const response = await manager.page.request.get(`${apiBase}/performance/${endpoint}`);
        expect(response.status(), `Manager: GET ${endpoint}`).toBe(403);
      }
      const create = await manager.page.request.post(`${apiBase}/performance/cycles`, { data: {
        name: "Unauthorized Manager cycle", cycle_type: "annual", start_date: "2026-01-01", end_date: "2026-12-31",
        review_period_start: "2025-01-01", review_period_end: "2025-12-31",
      } });
      expect(create.status()).toBe(403);
      for (const route of ["cycles", "cycles/create", "reviews/pending-calibration"]) {
        await manager.page.goto(`/admin/performance/${route}`);
        await expect(manager.page).toHaveURL(/\/admin\/dashboard$/);
      }
    } finally {
      await manager.context.close();
    }
  });

  for (const role of roles) {
    test(`${role} has permission-scoped template, outcome-rule and TOPSIS reads`, async ({ browser }) => {
      const reader = await actor(browser, role);
      const administrator = await actor(browser, "hr");
      let cycleId: number | undefined;
      try {
        const created = await administrator.page.request.post(`${apiBase}/performance/cycles`, { data: {
          name: `RBAC ${role} empty ranking ${Date.now()}`, cycle_type: "annual",
          start_date: "2026-01-01", end_date: "2026-12-31",
          review_period_start: "2025-01-01", review_period_end: "2025-12-31",
        } });
        expect(created.status()).toBe(201);
        const cycle = (await created.json()).data;
        cycleId = cycle.id;
        const activated = await administrator.page.request.put(`${apiBase}/performance/cycles/${cycle.id}`, { data: { status: "active" } });
        expect(activated.status()).toBe(200);
        expect((await activated.json()).data.status).toBe("active");
        const completed = await administrator.page.request.put(`${apiBase}/performance/cycles/${cycle.id}`, { data: { status: "completed" } });
        expect(completed.status()).toBe(200);
        expect((await completed.json()).data.status).toBe("completed");
        const allowed = role === "hr" || role === "superadmin";
        for (const family of [
          { route: "templates", endpoint: "templates", field: "name", empty: "No templates found" },
          { route: "outcome-rules", endpoint: "outcome-rules", field: "label", empty: "No outcome rules configured" },
        ] as const) {
          const response = await reader.page.request.get(`${apiBase}/performance/${family.endpoint}`);
          expect(response.status(), `${role}: GET ${family.endpoint}`).toBe(allowed ? 200 : 403);
          if (allowed) {
            const records = (await response.json()).data;
            expect(Array.isArray(records)).toBe(true);
            const pending = reader.page.waitForResponse((entry) =>
              new URL(entry.url()).pathname === `${new URL(apiBase).pathname}/performance/${family.endpoint}` && entry.request().method() === "GET",
            );
            await reader.page.goto(`/admin/performance/${family.route}`);
            expect((await pending).status()).toBe(200);
            if (records.length) {
              await expect(reader.page.getByText(records[0][family.field], { exact: true }).first()).toBeVisible();
            } else {
              await expect(reader.page.getByText(family.empty, { exact: true })).toBeVisible();
            }
          } else {
            await reader.page.goto(`/admin/performance/${family.route}`);
            await expect(reader.page).toHaveURL(/\/admin\/dashboard$/);
          }
        }
        const rankingResponse = await reader.page.request.get(`${apiBase}/performance/cycles/${cycle.id}/topsis-ranking`);
        expect(rankingResponse.status(), `${role}: GET TOPSIS ranking`).toBe(allowed ? 422 : 403);
        if (allowed) {
          const emptyRanking = await rankingResponse.json();
          expect(emptyRanking.data).toMatchObject({ cycle_id: cycle.id, total_completed: 0, ranking: [] });
          const pending = reader.page.waitForResponse((entry) =>
            new URL(entry.url()).pathname === `${new URL(apiBase).pathname}/performance/cycles/${cycle.id}/topsis-ranking` && entry.request().method() === "GET",
          );
          await reader.page.goto(`/admin/performance/cycles/${cycle.id}`);
          const visibleRanking = await pending;
          expect(visibleRanking.status()).toBe(422);
          expect((await visibleRanking.json()).data).toMatchObject({ cycle_id: cycle.id, total_completed: 0, ranking: [] });
          await expect(reader.page.getByText(emptyRanking.message, { exact: true })).toBeVisible();
        } else {
          await reader.page.goto(`/admin/performance/cycles/${cycle.id}`);
          await expect(reader.page).toHaveURL(/\/admin\/dashboard$/);
        }
      } finally {
        if (cycleId !== undefined) {
          const removed = await administrator.page.request.delete(`${apiBase}/performance/cycles/${cycleId}`);
          expect(removed.status()).toBe(200);
        }
        await Promise.all([reader.context.close(), administrator.context.close()]);
      }
    });
  }

  test("Manager assigns a goal inside their led team and cannot read or assign an unrelated Finance goal", async ({ browser }) => {
    const manager = await actor(browser, "manager");
    const employee = await actor(browser, "employee");
    const finance = await actor(browser, "finance");
    const goals: { page: Page; id: number }[] = [];
    try {
      const teamsResponse = await manager.page.request.get(`${apiBase}/teams?leader_id=${manager.user.id}`);
      expect(teamsResponse.status()).toBe(200);
      const teams = (await teamsResponse.json()).data;
      const ledTeams = teams.filter((team: { leader?: { id: number } }) => team.leader?.id === manager.user.id);
      expect(ledTeams.length, "Manager fixture must lead a team").toBeGreaterThan(0);
      const members: number[] = [];
      for (const team of ledTeams) {
        const detail = await manager.page.request.get(`${apiBase}/teams/${team.id}`);
        expect(detail.status()).toBe(200);
        for (const member of (await detail.json()).data.members) {
          if (member.left_at === null) members.push(member.staff_member.id);
        }
      }
      expect(members, "Staff fixture must be an active member of a Manager-led team").toContain(employee.profile.id);
      expect(members, "Finance fixture must remain outside the Manager-led teams").not.toContain(finance.profile.id);
      const payload = { title: `RBAC assigned goal ${Date.now()}`, goal_type: "development", start_date: "2026-01-01", due_date: "2026-01-02" };
      const assigned = await manager.page.request.post(`${apiBase}/performance/goals`, { data: { ...payload, staff_member_id: employee.profile.id } });
      expect(assigned.status()).toBe(201);
      const goal = (await assigned.json()).data;
      goals.push({ page: employee.page, id: goal.id });
      expect(goal).toMatchObject({ staff_member_id: employee.profile.id, assigned_by: manager.profile.id });
      const outsider = await finance.page.request.post(`${apiBase}/performance/goals`, { data: { ...payload, title: `RBAC private Finance ${Date.now()}`, staff_member_id: finance.profile.id } });
      expect(outsider.status()).toBe(201);
      const otherGoal = (await outsider.json()).data;
      goals.push({ page: finance.page, id: otherGoal.id });
      const teamGoals = await visitList(manager.page, "goals/team-goals", "goals/team-goals", "Team Goals");
      expect(teamGoals).toEqual(expect.arrayContaining([expect.objectContaining({ id: goal.id })]));
      expect(teamGoals.some((entry: { id: number }) => entry.id === otherGoal.id)).toBe(false);
      await expect(manager.page.getByRole("heading", { name: payload.title, exact: true })).toBeVisible();
      await expect(manager.page.getByRole("heading", { name: otherGoal.title, exact: true })).toHaveCount(0);
      const own = await visitList(employee.page, "goals/my-goals", "goals/my-goals", "My Goals");
      expect(own).toEqual(expect.arrayContaining([expect.objectContaining({ id: goal.id })]));
      await expect(employee.page.getByRole("heading", { name: payload.title, exact: true })).toBeVisible();
      const read = await manager.page.request.get(`${apiBase}/performance/goals/${otherGoal.id}`);
      for (const role of ["hr", "superadmin"] as const) {
        const oversight = await actor(browser, role);
        try {
          const visibleGoals = await visitList(oversight.page, "goals/team-goals", "goals/team-goals", "Team Goals");
          expect(visibleGoals, `${role} has company-wide goal oversight across both seeded teams`).toEqual(expect.arrayContaining([
            expect.objectContaining({ id: goal.id, staff_member_id: employee.profile.id }),
            expect.objectContaining({ id: otherGoal.id, staff_member_id: finance.profile.id }),
          ]));
          await expect(oversight.page.getByRole("heading", { name: payload.title, exact: true })).toBeVisible();
          await expect(oversight.page.getByRole("heading", { name: otherGoal.title, exact: true })).toBeVisible();
          for (const record of [goal, otherGoal]) {
            const read = await oversight.page.request.get(`${apiBase}/performance/goals/${record.id}`);
            expect(read.status()).toBe(200);
            expect((await read.json()).data).toMatchObject({ id: record.id, staff_member_id: record.staff_member_id });
          }
        } finally {
          await oversight.context.close();
        }
      }
      expect(read.status()).toBe(403);
      const assign = await manager.page.request.post(`${apiBase}/performance/goals`, { data: { ...payload, staff_member_id: finance.profile.id } });
      expect(assign.status()).toBe(403);
    } finally {
      for (const goal of goals) {
        const removed = await goal.page.request.delete(`${apiBase}/performance/goals/${goal.id}`);
        expect(removed.status()).toBe(200);
      }
      await Promise.all([manager.context.close(), employee.context.close(), finance.context.close()]);
    }
  });

  test("Superadmin creates a persisted review cycle through the administration UI", async ({ browser }) => {
    const admin = await actor(browser, "superadmin");
    try {
      await admin.page.goto("/admin/performance/cycles/create");
      const name = `RBAC Superadmin cycle ${Date.now()}`;
      const form = admin.page.locator("form");
      await form.locator('input[type="text"]').first().fill(name);
      const dates = form.locator('input[type="date"]');
      await dates.nth(0).fill("2025-01-01");
      await dates.nth(1).fill("2025-12-31");
      await dates.nth(2).fill("2026-01-01");
      await dates.nth(3).fill("2026-12-31");
      const created = admin.page.waitForResponse((response) => response.url() === `${apiBase}/performance/cycles` && response.request().method() === "POST");
      await form.locator('button[type="submit"]').click();
      const response = await created;
      expect(response.status()).toBe(201);
      const cycle = (await response.json()).data;
      await expect(admin.page).toHaveURL(/\/admin\/performance\/cycles$/);
      await expect(admin.page.getByText(name, { exact: true })).toBeVisible();
      const persisted = await admin.page.request.get(`${apiBase}/performance/cycles/${cycle.id}`);
      expect(persisted.status()).toBe(200);
      expect((await persisted.json()).data).toMatchObject({ name, status: "draft" });
      const deleted = await admin.page.request.delete(`${apiBase}/performance/cycles/${cycle.id}`);
      expect(deleted.status()).toBe(200);
    } finally {
      await admin.context.close();
    }
  });
});
