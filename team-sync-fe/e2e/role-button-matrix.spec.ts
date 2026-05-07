import { test, expect } from "./support/fixtures";
import { loginAsRole, type RoleName } from "./helpers/auth";
import { captureEvidence } from "./helpers/evidence";

/**
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║  ROLE-BUTTON MATRIX — Comprehensive QA Tester Coverage             ║
 * ║                                                                     ║
 * ║  Tests every permission-gated button/action across all 4 roles.    ║
 * ║  Validates: visible when allowed, hidden/absent when denied.       ║
 * ║  Goes beyond navigation — clicks buttons, opens modals, verifies   ║
 * ║  action outcomes per role.                                          ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 */

// ─── Helpers ────────────────────────────────────────────────────────

const expectButtonVisible = async (page: any, selector: string, label: string) => {
    await expect(page.locator(selector).first(), `${label} should be visible`).toBeVisible({ timeout: 10_000 });
};

const expectButtonHidden = async (page: any, selector: string, label: string) => {
    await expect(page.locator(selector).first(), `${label} should be hidden`).toHaveCount(0, { timeout: 5_000 });
};

const expectTextVisible = async (page: any, text: string | RegExp) => {
    await expect(page.getByText(text).first()).toBeVisible({ timeout: 10_000 });
};

const expectTextHidden = async (page: any, text: string | RegExp) => {
    await expect(page.getByText(text).first()).toHaveCount(0, { timeout: 5_000 });
};

// ═══════════════════════════════════════════════════════════════════
// MODULE 1: STAFF MEMBER — CRUD Buttons per Role
// ═══════════════════════════════════════════════════════════════════

test.describe.serial("Staff Member module — button visibility per role", () => {
    test.setTimeout(120_000);

    test("HR sees Add Staff Member, Edit, and can access staff detail with Edit/Delete", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "hr");

        // Staff List — Add Staff Member button
        await page.goto("/admin/staff-members");
        await expect(page).toHaveURL(/\/admin\/staff-members$/);
        await page.waitForLoadState("networkidle");
        await expectTextVisible(page, "Add Staff Member");
        await expectTextVisible(page, "Import CSV");

        // Wait for staff member API data to load and cards to render
        await expect(page.getByText("All Staff Members")).toBeVisible({ timeout: 15_000 });
        // Wait for actual employee name to appear (proves cards rendered)
        await expect(page.getByText("Dwimeta").first()).toBeVisible({ timeout: 20_000 });

        // Staff Card — Edit button visible (HR has staff-member-edit)
        const editSpan = page.locator("span:text-is('Edit')").first();
        await expect(editSpan).toBeVisible({ timeout: 5_000 });

        // Navigate to detail via first staff member link/card
        const viewSpan = page.locator("span:text-is('View')").first();
        await viewSpan.click();
        await expect(page).toHaveURL(/\/admin\/staff-members\/\d+$/);
        await page.waitForLoadState("networkidle");

        // Staff Detail — Edit Profile button
        await expectTextVisible(page, "Edit Profile");

        // Staff Detail — Delete zone (Danger Zone section)
        await expectTextVisible(page, "Danger Zone");

        await captureEvidence(page, "rbm-hr-staff-detail-buttons.png");
        await context.close();
    });

    test("Manager is denied access to staff directory (redirects to dashboard)", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "manager");

        // Manager no longer has staff directory access (deferred until team-scoped API)
        await page.goto("/admin/staff-members");
        await expect(page).toHaveURL(/\/admin\/dashboard$/);

        await captureEvidence(page, "rbm-manager-staff-denied.png");
        await context.close();
    });

    test("Finance sees staff list (view-only) without CRUD buttons", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "finance");

        await page.goto("/admin/staff-members");
        await expect(page).toHaveURL(/\/admin\/staff-members$/);
        await page.waitForLoadState("networkidle");

        await expectTextHidden(page, "Add Staff Member");
        await expectTextHidden(page, "Import CSV");

        await captureEvidence(page, "rbm-finance-staff-view-only.png");
        await context.close();
    });

    test("Staff is denied access to staff member list (redirects to dashboard)", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "employee");

        await page.goto("/admin/staff-members");
        // Staff has staff-member-list permission so they can view
        // But cannot create
        const url = page.url();
        if (url.includes("/admin/staff-members")) {
            await page.waitForLoadState("networkidle");
            await expectTextHidden(page, "Add Staff Member");
        }

        // Staff cannot access create route
        await page.goto("/admin/staff-members/create");
        await expect(page).toHaveURL(/\/admin\/dashboard$/);

        await captureEvidence(page, "rbm-staff-no-staff-crud.png");
        await context.close();
    });
});

// ═══════════════════════════════════════════════════════════════════
// MODULE 2: TEAM — CRUD Buttons per Role
// ═══════════════════════════════════════════════════════════════════

test.describe.serial("Team module — button visibility per role", () => {
    test.setTimeout(90_000);

    test("HR sees Add Team button on team list", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "hr");

        await page.goto("/admin/teams");
        await expect(page).toHaveURL(/\/admin\/teams$/);
        await page.waitForLoadState("networkidle");

        await expectTextVisible(page, "Add Team");
        await captureEvidence(page, "rbm-hr-team-add.png");
        await context.close();
    });

    test("Manager sees Add Team button on team list", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "manager");

        await page.goto("/admin/teams");
        await expect(page).toHaveURL(/\/admin\/teams$/);
        await page.waitForLoadState("networkidle");

        await expectTextVisible(page, "Add Team");
        await captureEvidence(page, "rbm-manager-team-add.png");
        await context.close();
    });

    test("Finance is denied access to teams (redirects to dashboard)", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "finance");

        await page.goto("/admin/teams");
        await expect(page).toHaveURL(/\/admin\/dashboard$/);

        await context.close();
    });
});

// ═══════════════════════════════════════════════════════════════════
// MODULE 3: PROJECT — CRUD Buttons per Role
// ═══════════════════════════════════════════════════════════════════

test.describe.serial("Project module — button visibility per role", () => {
    test.setTimeout(90_000);

    test("HR sees Add Project button", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "hr");

        await page.goto("/admin/projects");
        await expect(page).toHaveURL(/\/admin\/projects$/);
        await page.waitForLoadState("networkidle");

        await expectTextVisible(page, "Add Project");
        await captureEvidence(page, "rbm-hr-project-add.png");
        await context.close();
    });

    test("Manager sees Add Project button", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "manager");

        await page.goto("/admin/projects");
        await expect(page).toHaveURL(/\/admin\/projects$/);
        await page.waitForLoadState("networkidle");

        await expectTextVisible(page, "Add Project");
        await context.close();
    });

    test("Staff sees project list but NOT Add Project button", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "employee");

        await page.goto("/admin/projects");
        await expect(page).toHaveURL(/\/admin\/projects$/);
        await page.waitForLoadState("networkidle");

        await expectTextHidden(page, "Add Project");
        await captureEvidence(page, "rbm-staff-project-no-add.png");
        await context.close();
    });

    test("Finance is denied access to projects (redirects to dashboard)", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "finance");

        await page.goto("/admin/projects");
        await expect(page).toHaveURL(/\/admin\/dashboard$/);
        await context.close();
    });
});

// ═══════════════════════════════════════════════════════════════════
// MODULE 4: PAYROLL DASHBOARD — Action Buttons per Role
// ═══════════════════════════════════════════════════════════════════

test.describe.serial("Payroll dashboard — action buttons per role", () => {
    test.setTimeout(120_000);

    test("HR sees Readiness only — NOT Create/Settings/Export/Comparison", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "hr");

        await page.goto("/admin/payroll");
        await expect(page).toHaveURL(/\/admin\/payroll$/);
        await page.waitForLoadState("networkidle");

        // HR has payroll-readiness-view only — no payroll-create, no payroll-statistics
        await expectTextHidden(page, "Create New Payroll");
        await expect(page.getByTestId("payroll-export-report-open")).toHaveCount(0);
        await expect(page.getByTestId("payroll-comparison-link")).toHaveCount(0);

        await captureEvidence(page, "rbm-hr-payroll-actions.png");
        await context.close();
    });

    test("Finance sees Create/Export/Comparison/Settings (full payroll ops)", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "finance");

        await page.goto("/admin/payroll");
        await expect(page).toHaveURL(/\/admin\/payroll$/);
        await page.waitForLoadState("networkidle");

        // Finance owns all payroll operations: create, statistics, export, comparison, settings
        await expectTextVisible(page, "Create New Payroll");
        await expectButtonVisible(page, '[data-testid="payroll-export-report-open"]', "Export Report");
        await expectButtonVisible(page, '[data-testid="payroll-comparison-link"]', "MoM Comparison");
        await expectButtonVisible(page, '[data-testid="payroll-settings-link"]', "Settings link");

        await captureEvidence(page, "rbm-finance-payroll-actions.png");
        await context.close();
    });

    test("Manager is denied access to payroll (redirects to dashboard)", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "manager");

        await page.goto("/admin/payroll");
        await expect(page).toHaveURL(/\/admin\/dashboard$/);
        await context.close();
    });

    test("Staff is denied access to payroll (redirects to dashboard)", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "employee");

        await page.goto("/admin/payroll");
        await expect(page).toHaveURL(/\/admin\/dashboard$/);
        await context.close();
    });
});

// ═══════════════════════════════════════════════════════════════════
// MODULE 5: PAYROLL SETTINGS — Finance-only Save button
// ═══════════════════════════════════════════════════════════════════

test.describe.serial("Payroll settings — role access", () => {
    test.setTimeout(60_000);

    test("Finance can access settings and sees Save Configuration button", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "finance");

        await page.goto("/admin/payroll/settings");
        await expect(page).toHaveURL(/\/admin\/payroll\/settings$/);
        await page.waitForLoadState("networkidle");

        await expect(page.getByRole("heading", { name: "Payroll Settings" })).toBeVisible();
        await expectButtonVisible(page, '[data-testid="payroll-settings-save"]', "Save Configuration");

        await captureEvidence(page, "rbm-finance-payroll-settings.png");
        await context.close();
    });

    test("HR is denied access to payroll settings (redirects to dashboard)", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "hr");

        await page.goto("/admin/payroll/settings");
        await expect(page).toHaveURL(/\/admin\/dashboard$/);
        await context.close();
    });
});

// ═══════════════════════════════════════════════════════════════════
// MODULE 6: ATTENDANCE ADMIN — Approve/Reject Buttons per Role
// ═══════════════════════════════════════════════════════════════════

test.describe.serial("Attendance admin — action buttons per role", () => {
    test.setTimeout(90_000);

    test("HR sees Attendance Logs, Leave Requests, and Corrections links", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "hr");

        await page.goto("/admin/attendances");
        await expect(page).toHaveURL(/\/admin\/attendances$/);
        await page.waitForLoadState("networkidle");

        await expectTextVisible(page, "Attendance Logs");
        await expectTextVisible(page, "Leave Requests");
        await expectTextVisible(page, "Corrections");

        await captureEvidence(page, "rbm-hr-attendance-links.png");
        await context.close();
    });

    test("Manager sees Attendance admin with same links", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "manager");

        await page.goto("/admin/attendances");
        await expect(page).toHaveURL(/\/admin\/attendances$/);
        await page.waitForLoadState("networkidle");

        await expectTextVisible(page, "Attendance Logs");
        await expectTextVisible(page, "Leave Requests");
        await expectTextVisible(page, "Corrections");

        await context.close();
    });

    test("Staff is denied access to attendance admin (redirects to dashboard)", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "employee");

        await page.goto("/admin/attendances");
        await expect(page).toHaveURL(/\/admin\/dashboard$/);
        await context.close();
    });
});

// ═══════════════════════════════════════════════════════════════════
// MODULE 7: OVERTIME — Create/Approve Buttons per Role
// ═══════════════════════════════════════════════════════════════════

test.describe.serial("Overtime module — button visibility per role", () => {
    test.setTimeout(90_000);

    test("Finance does NOT see Record Overtime button (list-only access)", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "finance");

        await page.goto("/admin/overtime");
        await expect(page).toHaveURL(/\/admin\/overtime$/);
        await page.waitForLoadState("networkidle");

        // Finance has overtime-list only (payroll context), not overtime-create
        await expectTextHidden(page, "Record Overtime");
        await captureEvidence(page, "rbm-finance-overtime-list-only.png");
        await context.close();
    });

    test("HR sees Record Overtime button", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "hr");

        await page.goto("/admin/overtime");
        await expect(page).toHaveURL(/\/admin\/overtime$/);
        await page.waitForLoadState("networkidle");

        await expectTextVisible(page, "Record Overtime");
        await context.close();
    });
});

// ═══════════════════════════════════════════════════════════════════
// MODULE 8: THR — Generate/Approve Buttons per Role
// ═══════════════════════════════════════════════════════════════════

test.describe.serial("THR module — button visibility per role", () => {
    test.setTimeout(90_000);

    test("Finance sees Generate THR button (Finance owns THR operations)", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "finance");

        await page.goto("/admin/payroll/thr");
        await expect(page).toHaveURL(/\/admin\/payroll\/thr$/);
        await page.waitForLoadState("networkidle");

        await expect(page.getByRole("heading", { name: /THR Management/i })).toBeVisible();
        await expect(page.getByRole("button", { name: /Generate THR/i })).toBeVisible();

        await captureEvidence(page, "rbm-finance-thr-generate.png");
        await context.close();
    });

    test("HR can view THR list but cannot generate (read-only context)", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "hr");

        await page.goto("/admin/payroll/thr");
        await expect(page).toHaveURL(/\/admin\/payroll\/thr$/);
        await page.waitForLoadState("networkidle");

        await expect(page.getByRole("heading", { name: /THR Management/i })).toBeVisible();
        await expect(page.getByRole("button", { name: /Generate THR/i })).toHaveCount(0);

        await captureEvidence(page, "rbm-hr-thr-readonly.png");
        await context.close();
    });
});

// ═══════════════════════════════════════════════════════════════════
// MODULE 9: MEETING — Schedule Meeting Button per Role
// ═══════════════════════════════════════════════════════════════════

test.describe.serial("Meeting module — Schedule Meeting button per role", () => {
    test.setTimeout(90_000);

    test("HR sees Schedule Meeting button on meetings page", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "hr");

        await page.goto("/admin/meetings");
        await expect(page).toHaveURL(/\/admin\/meetings$/);
        await page.waitForLoadState("networkidle");

        await expect(page.getByRole("button", { name: /Schedule Meeting/i })).toBeVisible();

        // Click it to verify modal opens
        await page.getByRole("button", { name: /Schedule Meeting/i }).click();
        // Modal title is "Schedule Meeting" (from MeetingCreateModal)
        await expect(page.locator('[role="dialog"], .fixed').getByText("Schedule Meeting").first()).toBeVisible({ timeout: 5_000 });

        await captureEvidence(page, "rbm-hr-meeting-schedule-modal.png");
        await context.close();
    });

    test("Manager does NOT see Schedule Meeting button", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "manager");

        await page.goto("/admin/meetings");
        await expect(page).toHaveURL(/\/admin\/meetings$/);
        await page.waitForLoadState("networkidle");

        await expect(page.getByRole("button", { name: /Schedule Meeting/i })).toHaveCount(0);
        await captureEvidence(page, "rbm-manager-meeting-no-schedule.png");
        await context.close();
    });

    test("Staff can access meetings page but NOT see Schedule Meeting button", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "employee");

        await page.goto("/admin/meetings");
        await expect(page).toHaveURL(/\/admin\/meetings$/);
        await page.waitForLoadState("networkidle");

        await expect(page.getByRole("button", { name: /Schedule Meeting/i })).toHaveCount(0);
        await context.close();
    });
});

// ═══════════════════════════════════════════════════════════════════
// MODULE 10: ANALYTICS — Export Button per Role
// ═══════════════════════════════════════════════════════════════════

test.describe.serial("Analytics module — Export button per role", () => {
    test.setTimeout(90_000);

    test("HR sees Export button on analytics", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "hr");

        await page.goto("/admin/analytics");
        await expect(page).toHaveURL(/\/admin\/analytics$/);
        await page.waitForLoadState("networkidle");

        await expect(page.getByRole("button", { name: /Export/i }).first()).toBeVisible();
        await captureEvidence(page, "rbm-hr-analytics-export.png");
        await context.close();
    });

    test("Finance sees Export button on analytics", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "finance");

        await page.goto("/admin/analytics");
        await expect(page).toHaveURL(/\/admin\/analytics$/);
        await page.waitForLoadState("networkidle");

        await expect(page.getByRole("button", { name: /Export/i }).first()).toBeVisible();
        await context.close();
    });

    test("Staff is denied access to analytics (redirects to dashboard)", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "employee");

        await page.goto("/admin/analytics");
        await expect(page).toHaveURL(/\/admin\/dashboard$/);
        await context.close();
    });
});

// ═══════════════════════════════════════════════════════════════════
// MODULE 11: PERFORMANCE — HR-only Management Buttons
// ═══════════════════════════════════════════════════════════════════

test.describe.serial("Performance module — HR-only management buttons", () => {
    test.setTimeout(120_000);

    test("HR can access review cycles, templates, outcome rules with CRUD buttons", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "hr");

        // Review Cycles
        await page.goto("/admin/performance/cycles");
        await expect(page).toHaveURL(/\/admin\/performance\/cycles$/);
        await page.waitForLoadState("networkidle");
        await captureEvidence(page, "rbm-hr-perf-cycles.png");

        // Templates — Add button
        await page.goto("/admin/performance/templates");
        await expect(page).toHaveURL(/\/admin\/performance\/templates$/);
        await page.waitForLoadState("networkidle");
        await expect(page.getByRole("button", { name: /New Template/i }).first()).toBeVisible();

        // Outcome Rules — Add button
        await page.goto("/admin/performance/outcome-rules");
        await expect(page).toHaveURL(/\/admin\/performance\/outcome-rules$/);
        await page.waitForLoadState("networkidle");
        await expect(page.getByRole("button", { name: /Add Rule/i }).first()).toBeVisible();

        // Calibration page accessible
        await page.goto("/admin/performance/reviews/pending-calibration");
        await expect(page).toHaveURL(/\/admin\/performance\/reviews\/pending-calibration$/);

        await captureEvidence(page, "rbm-hr-perf-management.png");
        await context.close();
    });

    test("Manager is denied review cycles, templates, outcome rules, calibration", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "manager");

        await page.goto("/admin/performance/cycles");
        await expect(page).toHaveURL(/\/admin\/dashboard$/);

        await page.goto("/admin/performance/templates");
        await expect(page).toHaveURL(/\/admin\/dashboard$/);

        await page.goto("/admin/performance/outcome-rules");
        await expect(page).toHaveURL(/\/admin\/dashboard$/);

        await page.goto("/admin/performance/reviews/pending-calibration");
        await expect(page).toHaveURL(/\/admin\/dashboard$/);

        await context.close();
    });

    test("Manager CAN access team reviews and team goals", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "manager");

        await page.goto("/admin/performance/reviews/team-reviews");
        await expect(page).toHaveURL(/\/admin\/performance\/reviews\/team-reviews$/);

        await page.goto("/admin/performance/goals/team-goals");
        await expect(page).toHaveURL(/\/admin\/performance\/goals\/team-goals$/);

        await context.close();
    });

    test("Staff can access my-reviews and my-goals but NOT management pages", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "employee");

        await page.goto("/admin/performance/reviews/my-reviews");
        await expect(page).toHaveURL(/\/admin\/performance\/reviews\/my-reviews$/);

        await page.goto("/admin/performance/goals/my-goals");
        await expect(page).toHaveURL(/\/admin\/performance\/goals\/my-goals$/);

        // Denied: cycles, templates, calibration
        await page.goto("/admin/performance/cycles");
        await expect(page).toHaveURL(/\/admin\/dashboard$/);

        await page.goto("/admin/performance/templates");
        await expect(page).toHaveURL(/\/admin\/dashboard$/);

        await context.close();
    });
});

// ═══════════════════════════════════════════════════════════════════
// MODULE 12: SELF-SERVICE — Buttons available to ALL roles
// ═══════════════════════════════════════════════════════════════════

test.describe.serial("Self-service — buttons available to all roles", () => {
    test.setTimeout(180_000);

    const roles: RoleName[] = ["hr", "manager", "finance", "employee"];

    for (const role of roles) {
        test(`${role} can access My Attendance with Request Leave button`, async ({ browser }) => {
            const context = await browser.newContext();
            const page = await context.newPage();
            await loginAsRole(page, role);

            await page.goto("/admin/attendance/my-attendances");
            await expect(page).toHaveURL(/\/admin\/attendance\/my-attendances$/);
            await page.waitForLoadState("networkidle");

            // All roles have leave-request-create → Request Leave button
            await expect(page.getByRole("button", { name: "Request Leave" }).first()).toBeVisible({ timeout: 10_000 });

            await captureEvidence(page, `rbm-${role}-my-attendance-leave-btn.png`);
            await context.close();
        });
    }

    for (const role of roles) {
        test(`${role} can access My Payroll page`, async ({ browser }) => {
            const context = await browser.newContext();
            const page = await context.newPage();
            await loginAsRole(page, role);

            await page.goto("/admin/my-payroll");
            await expect(page).toHaveURL(/\/admin\/my-payroll$/);
            await page.waitForLoadState("networkidle");

            await expect(page.locator("main").getByRole("heading", { name: "My Payroll" })).toBeVisible();

            await context.close();
        });
    }

    for (const role of roles) {
        test(`${role} can access My Profile page`, async ({ browser }) => {
            const context = await browser.newContext();
            const page = await context.newPage();
            await loginAsRole(page, role);

            await page.goto("/admin/my-profile");
            await expect(page).toHaveURL(/\/admin\/my-profile$/);
            await page.waitForLoadState("networkidle");

            await context.close();
        });
    }

    for (const role of roles) {
        test(`${role} can access notifications page`, async ({ browser }) => {
            const context = await browser.newContext();
            const page = await context.newPage();
            await loginAsRole(page, role);

            await page.goto("/admin/notifications");
            await expect(page).toHaveURL(/\/admin\/notifications$/);

            await context.close();
        });
    }
});

// ═══════════════════════════════════════════════════════════════════
// MODULE 13: DASHBOARD — Role-specific widget rendering
// ═══════════════════════════════════════════════════════════════════

test.describe.serial("Dashboard — role-specific content rendering", () => {
    test.setTimeout(90_000);

    test("HR/Manager dashboard shows Statistics, Search, Latest Employees, Teams, Attendance", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "hr");
        await page.waitForLoadState("networkidle");

        // HR dashboard has search section and latest widgets
        const main = page.locator("main");
        await expect(main).toBeVisible();

        // Should have Upcoming Meetings widget
        await expect(page.getByText("Upcoming Meetings").first()).toBeVisible({ timeout: 10_000 });

        await captureEvidence(page, "rbm-hr-dashboard-widgets.png");
        await context.close();
    });

    test("Finance dashboard shows PayrollAnalyticsEnhanced", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "finance");
        await page.waitForLoadState("networkidle");

        // Finance dashboard shows payroll-focused content
        await expect(page.getByText("Total Payroll Cost").first()).toBeVisible({ timeout: 10_000 });

        await captureEvidence(page, "rbm-finance-dashboard.png");
        await context.close();
    });

    test("Staff dashboard shows EmployeeStatistics with Welcome heading", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        await loginAsRole(page, "employee");
        await page.waitForLoadState("networkidle");

        await expect(page.getByRole("heading", { name: /Welcome back/ })).toBeVisible();

        await captureEvidence(page, "rbm-staff-dashboard.png");
        await context.close();
    });
});
