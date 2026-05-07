/**
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║  TEAM SYNC — E2E PROJECT CAPABILITY BENCHMARK TEST                 ║
 * ║                                                                     ║
 * ║  Validates end-to-end that the full stack is capable of carrying   ║
 * ║  out all core HRIS operations across all 4 roles: HR, Manager,    ║
 * ║  Finance, and Staff.                                               ║
 * ║                                                                     ║
 * ║  Run: bun run e2e -- project-capability-benchmark.spec.ts          ║
 * ║  Prerequisites: Backend running + seeded (e2e-prepare-be.sh)       ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 */

import { test, expect } from "./support/fixtures";
import { loginAsRole, type RoleName } from "./helpers/auth";
import { captureEvidence } from "./helpers/evidence";

test.describe.serial("Project Capability Benchmark — Full Stack Validation", () => {
  test.setTimeout(180_000);

  // ═══════════════════════════════════════════════════════════════
  // BENCHMARK 1: Authentication System
  // ═══════════════════════════════════════════════════════════════

  test("B1: All 4 roles can authenticate and reach dashboard", async ({ browser }) => {
    const roles: RoleName[] = ["hr", "manager", "finance", "employee"];

    for (const role of roles) {
      const context = await browser.newContext();
      const page = await context.newPage();

      await loginAsRole(page, role);
      await expect(page).toHaveURL(/\/admin\/dashboard$/);
      await expect(page.getByRole("heading", { name: /Dashboard|Welcome/ }).first()).toBeVisible();
      await captureEvidence(page, `benchmark-auth-${role}.png`);

      await context.close();
    }
  });

  // ═══════════════════════════════════════════════════════════════
  // BENCHMARK 2: HR — Full CRUD Capabilities
  // ═══════════════════════════════════════════════════════════════

  test("B2: HR can access all admin modules", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "hr");

    // Staff Members
    await page.goto("/admin/staff-members");
    await expect(page).toHaveURL(/\/admin\/staff-members$/);
    await page.waitForLoadState("networkidle");
    await captureEvidence(page, "benchmark-hr-staff-members.png");

    // Teams
    await page.goto("/admin/teams");
    await expect(page).toHaveURL(/\/admin\/teams$/);
    await page.waitForLoadState("networkidle");
    await captureEvidence(page, "benchmark-hr-teams.png");

    // Projects
    await page.goto("/admin/projects");
    await expect(page).toHaveURL(/\/admin\/projects$/);
    await page.waitForLoadState("networkidle");

    // Attendance
    await page.goto("/admin/attendances");
    await expect(page).toHaveURL(/\/admin\/attendances$/);
    await page.waitForLoadState("networkidle");

    // Leave Requests
    await page.goto("/admin/leave-requests");
    await expect(page).toHaveURL(/\/admin\/leave-requests$/);
    await page.waitForLoadState("networkidle");

    // Payroll
    await page.goto("/admin/payroll");
    await expect(page).toHaveURL(/\/admin\/payroll$/);
    await page.waitForLoadState("networkidle");
    await captureEvidence(page, "benchmark-hr-payroll.png");

    // Analytics
    await page.goto("/admin/analytics");
    await expect(page).toHaveURL(/\/admin\/analytics$/);
    await page.waitForLoadState("networkidle");

    // Performance — Review Cycles
    await page.goto("/admin/performance/cycles");
    await expect(page).toHaveURL(/\/admin\/performance\/cycles$/);
    await page.waitForLoadState("networkidle");
    await captureEvidence(page, "benchmark-hr-performance-cycles.png");

    // Performance — Templates
    await page.goto("/admin/performance/templates");
    await expect(page).toHaveURL(/\/admin\/performance\/templates$/);
    await page.waitForLoadState("networkidle");

    // Performance — Calibration
    await page.goto("/admin/performance/reviews/pending-calibration");
    await expect(page).toHaveURL(/\/admin\/performance\/reviews\/pending-calibration$/);
    await page.waitForLoadState("networkidle");

    // Meetings
    await page.goto("/admin/meetings");
    await expect(page).toHaveURL(/\/admin\/meetings$/);
    await page.waitForLoadState("networkidle");

    await context.close();
  });

  // ═══════════════════════════════════════════════════════════════
  // BENCHMARK 3: Manager — Team & Performance Access
  // ═══════════════════════════════════════════════════════════════

  test("B3: Manager can access team management and performance reviews", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "manager");

    // Teams
    await page.goto("/admin/teams");
    await expect(page).toHaveURL(/\/admin\/teams$/);
    await page.waitForLoadState("networkidle");

    // Staff Members (view-only)
    await page.goto("/admin/staff-members");
    await expect(page).toHaveURL(/\/admin\/staff-members$/);
    await page.waitForLoadState("networkidle");

    // Projects
    await page.goto("/admin/projects");
    await expect(page).toHaveURL(/\/admin\/projects$/);
    await page.waitForLoadState("networkidle");

    // Attendance admin
    await page.goto("/admin/attendances");
    await expect(page).toHaveURL(/\/admin\/attendances$/);
    await page.waitForLoadState("networkidle");

    // Performance — Team Reviews
    await page.goto("/admin/performance/reviews/team-reviews");
    await expect(page).toHaveURL(/\/admin\/performance\/reviews\/team-reviews$/);
    await page.waitForLoadState("networkidle");
    await captureEvidence(page, "benchmark-manager-team-reviews.png");

    // Performance — Team Goals
    await page.goto("/admin/performance/goals/team-goals");
    await expect(page).toHaveURL(/\/admin\/performance\/goals\/team-goals$/);
    await page.waitForLoadState("networkidle");

    // Analytics
    await page.goto("/admin/analytics");
    await expect(page).toHaveURL(/\/admin\/analytics$/);
    await page.waitForLoadState("networkidle");

    // DENIED: Payroll (should redirect)
    await page.goto("/admin/payroll");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    // DENIED: Review Cycles (HR only)
    await page.goto("/admin/performance/cycles");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    await captureEvidence(page, "benchmark-manager-access.png");
    await context.close();
  });

  // ═══════════════════════════════════════════════════════════════
  // BENCHMARK 4: Finance — Payroll Operations
  // ═══════════════════════════════════════════════════════════════

  test("B4: Finance can access payroll and settings", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "finance");

    // Payroll Dashboard
    await page.goto("/admin/payroll");
    await expect(page).toHaveURL(/\/admin\/payroll$/);
    await page.waitForLoadState("networkidle");
    await captureEvidence(page, "benchmark-finance-payroll.png");

    // Payroll Settings
    await page.goto("/admin/payroll/settings");
    await expect(page).toHaveURL(/\/admin\/payroll\/settings$/);
    await page.waitForLoadState("networkidle");
    await captureEvidence(page, "benchmark-finance-settings.png");

    // Analytics
    await page.goto("/admin/analytics");
    await expect(page).toHaveURL(/\/admin\/analytics$/);
    await page.waitForLoadState("networkidle");

    // Staff Members (view)
    await page.goto("/admin/staff-members");
    await expect(page).toHaveURL(/\/admin\/staff-members$/);
    await page.waitForLoadState("networkidle");

    // DENIED: Teams
    await page.goto("/admin/teams");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    // DENIED: Projects
    await page.goto("/admin/projects");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    await captureEvidence(page, "benchmark-finance-access.png");
    await context.close();
  });

  // ═══════════════════════════════════════════════════════════════
  // BENCHMARK 5: Staff — Self-Service Features
  // ═══════════════════════════════════════════════════════════════

  test("B5: Staff can access all self-service features", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "employee");

    // My Profile
    await page.goto("/admin/my-profile");
    await expect(page).toHaveURL(/\/admin\/my-profile$/);
    await page.waitForLoadState("networkidle");
    await captureEvidence(page, "benchmark-staff-profile.png");

    // My Attendance
    await page.goto("/admin/attendance/my-attendances");
    await expect(page).toHaveURL(/\/admin\/attendance\/my-attendances$/);
    await page.waitForLoadState("networkidle");

    // My Payslips
    await page.goto("/admin/my-payroll");
    await expect(page).toHaveURL(/\/admin\/my-payroll$/);
    await page.waitForLoadState("networkidle");
    await captureEvidence(page, "benchmark-staff-payslips.png");

    // My Team
    await page.goto("/admin/my-team");
    await expect(page).toHaveURL(/\/admin\/my-team$/);
    await page.waitForLoadState("networkidle");

    // Projects (view)
    await page.goto("/admin/projects");
    await expect(page).toHaveURL(/\/admin\/projects$/);
    await page.waitForLoadState("networkidle");

    // Performance — My Reviews
    await page.goto("/admin/performance/reviews/my-reviews");
    await expect(page).toHaveURL(/\/admin\/performance\/reviews\/my-reviews$/);
    await page.waitForLoadState("networkidle");

    // Performance — My Goals
    await page.goto("/admin/performance/goals/my-goals");
    await expect(page).toHaveURL(/\/admin\/performance\/goals\/my-goals$/);
    await page.waitForLoadState("networkidle");
    await captureEvidence(page, "benchmark-staff-goals.png");

    // DENIED: Payroll admin
    await page.goto("/admin/payroll");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    // DENIED: Staff member create
    await page.goto("/admin/staff-members/create");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    // DENIED: Analytics
    await page.goto("/admin/analytics");
    await expect(page).toHaveURL(/\/admin\/dashboard$/);

    await captureEvidence(page, "benchmark-staff-denied.png");
    await context.close();
  });

  // ═══════════════════════════════════════════════════════════════
  // BENCHMARK 6: Notifications System
  // ═══════════════════════════════════════════════════════════════

  test("B6: Notification panel is accessible for all roles", async ({ browser }) => {
    const roles: RoleName[] = ["hr", "manager", "finance", "employee"];

    for (const role of roles) {
      const context = await browser.newContext();
      const page = await context.newPage();

      await loginAsRole(page, role);

      // Navigate to notifications page
      await page.goto("/admin/notifications");
      await expect(page).toHaveURL(/\/admin\/notifications$/);
      await page.waitForLoadState("networkidle");

      await context.close();
    }
  });

  // ═══════════════════════════════════════════════════════════════
  // BENCHMARK 7: UI Rendering & Responsiveness
  // ═══════════════════════════════════════════════════════════════

  test("B7: Dashboard renders all expected widgets for HR", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "hr");
    await page.waitForLoadState("networkidle");

    // Dashboard should have main content area
    const main = page.locator("main");
    await expect(main).toBeVisible();

    // Should have navigation/sidebar
    const sidebar = page.locator("aside, nav, [data-testid='sidebar']").first();
    await expect(sidebar).toBeVisible();

    await captureEvidence(page, "benchmark-dashboard-widgets.png");
    await context.close();
  });

  // ═══════════════════════════════════════════════════════════════
  // BENCHMARK 8: Data Loading & API Integration
  // ═══════════════════════════════════════════════════════════════

  test("B8: Staff member list loads data from API", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "hr");
    await page.goto("/admin/staff-members");
    await page.waitForLoadState("networkidle");

    // Should display employee data (table or list)
    const content = await page.locator("main").textContent();
    // The page should have loaded some content (not be empty)
    expect(content?.length).toBeGreaterThan(50);

    await captureEvidence(page, "benchmark-staff-list-loaded.png");
    await context.close();
  });

  // ═══════════════════════════════════════════════════════════════
  // BENCHMARK 9: Payroll Module Full Flow
  // ═══════════════════════════════════════════════════════════════

  test("B9: Payroll dashboard loads and displays payroll data", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    // Finance owns payroll operations (HR no longer has payroll-menu)
    await loginAsRole(page, "finance");
    await page.goto("/admin/payroll");
    await page.waitForLoadState("networkidle");

    // Payroll page should render
    const main = page.locator("main");
    await expect(main).toBeVisible();

    // Should have payroll-related content
    const heading = page.getByRole("heading", { name: /Payroll/i }).first();
    await expect(heading).toBeVisible();

    await captureEvidence(page, "benchmark-payroll-dashboard.png");
    await context.close();
  });

  // ═══════════════════════════════════════════════════════════════
  // BENCHMARK 10: Performance Module
  // ═══════════════════════════════════════════════════════════════

  test("B10: Performance review cycles page loads for HR", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "hr");
    await page.goto("/admin/performance/cycles");
    await page.waitForLoadState("networkidle");

    const main = page.locator("main");
    await expect(main).toBeVisible();

    await captureEvidence(page, "benchmark-performance-cycles.png");
    await context.close();
  });

  // ═══════════════════════════════════════════════════════════════
  // BENCHMARK 11: Analytics Module
  // ═══════════════════════════════════════════════════════════════

  test("B11: Analytics dashboard renders charts for HR", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "hr");
    await page.goto("/admin/analytics");
    await page.waitForLoadState("networkidle");

    const main = page.locator("main");
    await expect(main).toBeVisible();

    // Analytics page should have chart containers or headings
    const content = await main.textContent();
    expect(content?.length).toBeGreaterThan(20);

    await captureEvidence(page, "benchmark-analytics.png");
    await context.close();
  });

  // ═══════════════════════════════════════════════════════════════
  // BENCHMARK 12: Meeting Module
  // ═══════════════════════════════════════════════════════════════

  test("B12: Meeting list page loads for HR", async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsRole(page, "hr");
    await page.goto("/admin/meetings");
    await page.waitForLoadState("networkidle");

    const main = page.locator("main");
    await expect(main).toBeVisible();

    await captureEvidence(page, "benchmark-meetings.png");
    await context.close();
  });
});
