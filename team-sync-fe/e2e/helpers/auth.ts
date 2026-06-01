import { expect, type Page } from "@playwright/test";

const password = process.env.E2E_PASSWORD || "teamsync";

export const roleCredentials = {
  manager: {
    email: process.env.E2E_MANAGER_EMAIL || "yudhis@teamsync.com",
    password,
  },
  hr: {
    email: process.env.E2E_HR_EMAIL || "tasyia@teamsync.com",
    password,
  },
  finance: {
    email: process.env.E2E_FINANCE_EMAIL || "dwimeta@teamsync.com",
    password,
  },
  employee: {
    email: process.env.E2E_EMPLOYEE_EMAIL || "agung@teamsync.com",
    password,
  },
} as const;

export type RoleName = keyof typeof roleCredentials;

export const loginAsRole = async (page: Page, role: RoleName) => {
  const credentials = roleCredentials[role];

  await page.goto("/auth/login");
  await page.locator('input[name="email"]').fill(credentials.email);
  await page.locator('input[name="password"]').fill(credentials.password);
  await page.getByTestId("login-submit").click();

  try {
    await expect(page).toHaveURL(/\/admin\/dashboard$/, { timeout: 12_000 });
  } catch {
    const currentUrl = page.url();
    const inlineError = await page
      .locator('[data-testid="login-error"], .text-red-500, .alert-danger, .text-red-700, [role="alert"]')
      .first()
      .textContent()
      .catch(() => null);

    throw new Error(
      `Login failed for role "${role}". Current URL: ${currentUrl}. ` +
        `Possible cause: backend or seeded dataset is not ready. ` +
        `Run "bun run e2e:prepare:be" and retry. ` +
        `UI error: ${inlineError?.trim() || "<none>"}`
    );
  }
};
