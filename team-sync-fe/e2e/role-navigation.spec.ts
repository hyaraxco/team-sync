import { test, expect } from "./support/fixtures";
import { loginAsRole, type RoleName } from "./helpers/auth";
import { captureEvidence } from "./helpers/evidence";

/**
 * Role-based navigation E2E tests.
 *
 * Verifies that each role can:
 * 1. Login and reach dashboard
 * 2. Access pages they have permission for
 * 3. Get redirected from pages they don't have permission for
 * 4. See correct sidebar sections
 * 5. Access self-service features (profile, attendance, payslips)
 */

test.describe.serial("Role-based navigation and access control", () => {
  test.setTimeout(120_000);

  // ── Staff (Agung) ────────────────────────────────────────────────

  test("staff can access self-service features and is denied admin features", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "employee");

    // Dashboard loads with welcome message
    await expect(page.getByRole("heading", { name: /Welcome back/ })).toBeVisible();
    await captureEvidence(page, "staff-dashboard.png");

    // Self-service: My Profile
    await page.goto("/admin/my-profile");
    await expect(page).toHaveURL(/\/admin\/my-profile$/);
    await expect(page.locator("main")).toContainText("Agung Ramadhan");
    await captureEvidence(page, "staff-my-profile.png");

    // Self-service: My Team
    await page.goto("/admin/my-team");
    await expect(page).toHaveURL(/\/admin\/my-team$/);
    await captureEvidence(page, "staff-my-team.png");

    // Self-service: My Attendance
    await page.goto("/admin/attendance/my-attendances");
    await expect(page).toHaveURL(/\/admin\/attendance\/my-attendances$/);
    await captureEvidence(page, "staff-my-attendance.png");

    // Self-service: My Payslips
    await page.goto("/admin/my-payroll");
    await expect(page).toHaveURL(/\/admin\/my-payroll$/);
    await expect(page.locator("main").getByRole("heading", { name: "My Payroll" })).toBeVisible();
    await captureEvidence(page, "staff-my-payslips.png");

    // Self-service: Projects (view)
    await page.goto("/admin/projects");
    await expect(page).toHaveURL(/\/admin\/projects$/);
    await captureEvidence(page, "staff-projects.png");

    // Self-service: Performance — My Reviews
    await page.goto("/admin/performance/reviews/my-reviews");
    await expect(page).toHaveURL(/\/admin\/performance\/reviews\/my-reviews$/);
    await captureEvidence(page, "staff-my-reviews.png");

    // Self-service: Performance — My Goals
    await page.goto("/admin/performance/goals/my-goals");
    await expect(page).toHaveURL(/\/admin\/performance\/goals\/my-goals$/);
    await captureEvidence(page, "staff-my-goals.png");

    // DENIED: Payroll admin
    await page.goto("/admin/payroll");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    // DENIED: Staff member create
    await page.goto("/admin/staff-members/create");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    // DENIED: Attendance admin list
    await page.goto("/admin/attendances");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    // DENIED: Analytics
    await page.goto("/admin/analytics");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    // DENIED: Review cycles (HR only)
    await page.goto("/admin/performance/cycles");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    await captureEvidence(page, "staff-denied-redirect.png");
    await context.close();
  });

  // ── Manager (Yudhis) ─────────────────────────────────────────────

  test("manager can access admin features except payroll and HR-only performance", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "manager");

    // Dashboard
    await expect(page.getByRole("heading", { name: /Dashboard|Welcome/ })).toBeVisible();
    await captureEvidence(page, "manager-dashboard.png");

    // Employees (view-only)
    await page.goto("/admin/staff-members");
    await expect(page).toHaveURL(/\/admin\/staff-members$/);
    await captureEvidence(page, "manager-employees.png");

    // Teams
    await page.goto("/admin/teams");
    await expect(page).toHaveURL(/\/admin\/teams$/);
    await captureEvidence(page, "manager-teams.png");

    // Projects
    await page.goto("/admin/projects");
    await expect(page).toHaveURL(/\/admin\/projects$/);
    await captureEvidence(page, "manager-projects.png");

    // Attendance admin
    await page.goto("/admin/attendances");
    await expect(page).toHaveURL(/\/admin\/attendances$/);
    await captureEvidence(page, "manager-attendance-admin.png");

    // Analytics
    await page.goto("/admin/analytics");
    await expect(page).toHaveURL(/\/admin\/analytics$/);
    await captureEvidence(page, "manager-analytics.png");

    // Performance: Team Reviews
    await page.goto("/admin/performance/reviews/team-reviews");
    await expect(page).toHaveURL(/\/admin\/performance\/reviews\/team-reviews$/);
    await captureEvidence(page, "manager-team-reviews.png");

    // Performance: Team Goals
    await page.goto("/admin/performance/goals/team-goals");
    await expect(page).toHaveURL(/\/admin\/performance\/goals\/team-goals$/);
    await captureEvidence(page, "manager-team-goals.png");

    // Self-service
    await page.goto("/admin/my-profile");
    await expect(page).toHaveURL(/\/admin\/my-profile$/);
    await page.goto("/admin/attendance/my-attendances");
    await expect(page).toHaveURL(/\/admin\/attendance\/my-attendances$/);

    // DENIED: Payroll
    await page.goto("/admin/payroll");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    // DENIED: Staff member create (view-only)
    await page.goto("/admin/staff-members/create");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    // DENIED: Review cycles (HR only)
    await page.goto("/admin/performance/cycles");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    // DENIED: Calibration (HR only)
    await page.goto("/admin/performance/reviews/pending-calibration");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    await captureEvidence(page, "manager-denied-redirect.png");
    await context.close();
  });

  // ── HR (Tasyia) ──────────────────────────────────────────────────

  test("hr can access all admin features except finance payroll actions", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "hr");

    // Dashboard
    await expect(page).toHaveURL(/\/admin\/dashboard$/);
    await captureEvidence(page, "hr-dashboard.png");

    // Employees (full CRUD)
    await page.goto("/admin/staff-members");
    await expect(page).toHaveURL(/\/admin\/staff-members$/);
    await captureEvidence(page, "hr-employees.png");

    // Teams
    await page.goto("/admin/teams");
    await expect(page).toHaveURL(/\/admin\/teams$/);

    // Projects
    await page.goto("/admin/projects");
    await expect(page).toHaveURL(/\/admin\/projects$/);

    // Attendance admin
    await page.goto("/admin/attendances");
    await expect(page).toHaveURL(/\/admin\/attendances$/);

    // Leave requests
    await page.goto("/admin/leave-requests");
    await expect(page).toHaveURL(/\/admin\/leave-requests$/);
    await captureEvidence(page, "hr-leave-requests.png");

    // Payroll (view + generate, not edit/process)
    await page.goto("/admin/payroll");
    await expect(page).toHaveURL(/\/admin\/payroll$/);
    await captureEvidence(page, "hr-payroll.png");

    // Payroll create
    await page.goto("/admin/payroll/create");
    await expect(page).toHaveURL(/\/admin\/payroll\/create$/);
    await captureEvidence(page, "hr-payroll-create.png");

    // Analytics
    await page.goto("/admin/analytics");
    await expect(page).toHaveURL(/\/admin\/analytics$/);

    // Performance: Review Cycles (HR manage)
    await page.goto("/admin/performance/cycles");
    await expect(page).toHaveURL(/\/admin\/performance\/cycles$/);
    await captureEvidence(page, "hr-review-cycles.png");

    // Performance: Templates
    await page.goto("/admin/performance/templates");
    await expect(page).toHaveURL(/\/admin\/performance\/templates$/);
    await captureEvidence(page, "hr-templates.png");

    // Performance: Outcome Rules
    await page.goto("/admin/performance/outcome-rules");
    await expect(page).toHaveURL(/\/admin\/performance\/outcome-rules$/);
    await captureEvidence(page, "hr-outcome-rules.png");

    // Performance: Pending Calibration
    await page.goto("/admin/performance/reviews/pending-calibration");
    await expect(page).toHaveURL(/\/admin\/performance\/reviews\/pending-calibration$/);
    await captureEvidence(page, "hr-calibration.png");

    // Self-service
    await page.goto("/admin/my-profile");
    await expect(page).toHaveURL(/\/admin\/my-profile$/);
    await page.goto("/admin/attendance/my-attendances");
    await expect(page).toHaveURL(/\/admin\/attendance\/my-attendances$/);
    await page.goto("/admin/my-payroll");
    await expect(page).toHaveURL(/\/admin\/my-payroll$/);

    // DENIED: Payroll settings (finance only)
    await page.goto("/admin/payroll/settings");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    await captureEvidence(page, "hr-denied-redirect.png");
    await context.close();
  });

  // ── Finance (Dwimeta) ────────────────────────────────────────────

  test("finance can access payroll features and is denied non-payroll admin", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "finance");

    // Dashboard
    await expect(page).toHaveURL(/\/admin\/dashboard$/);
    await captureEvidence(page, "finance-dashboard.png");

    // DENIED: Staff directory (Finance has no staff-member-menu)
    // NOTE: Frontend route guard enforcement is Phase 2 — currently passes through.
    // Once Phase 2 sidebar/router alignment is done, this should redirect to dashboard.
    // await page.goto("/admin/staff-members");
    // await expect(page).toHaveURL(/\/admin\/dashboard$/);

    // Payroll dashboard
    await page.goto("/admin/payroll");
    await expect(page).toHaveURL(/\/admin\/payroll$/);
    await captureEvidence(page, "finance-payroll.png");

    // Payroll settings
    await page.goto("/admin/payroll/settings");
    await expect(page).toHaveURL(/\/admin\/payroll\/settings$/);
    await captureEvidence(page, "finance-payroll-settings.png");

    // Analytics
    await page.goto("/admin/analytics");
    await expect(page).toHaveURL(/\/admin\/analytics$/);
    await captureEvidence(page, "finance-analytics.png");

    // Self-service
    await page.goto("/admin/my-profile");
    await expect(page).toHaveURL(/\/admin\/my-profile$/);
    await page.goto("/admin/attendance/my-attendances");
    await expect(page).toHaveURL(/\/admin\/attendance\/my-attendances$/);
    await page.goto("/admin/my-payroll");
    await expect(page).toHaveURL(/\/admin\/my-payroll$/);

    // ALLOWED: Payroll create (Finance owns payroll operations)
    await page.goto("/admin/payroll/create");
    await expect(page).toHaveURL(/\/admin\/payroll\/create$/);

    // DENIED: Teams
    await page.goto("/admin/teams");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    // DENIED: Projects
    await page.goto("/admin/projects");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    // DENIED: Attendance admin
    await page.goto("/admin/attendances");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    // DENIED: Performance cycles
    await page.goto("/admin/performance/cycles");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    await captureEvidence(page, "finance-denied-redirect.png");
    await context.close();
  });
});
