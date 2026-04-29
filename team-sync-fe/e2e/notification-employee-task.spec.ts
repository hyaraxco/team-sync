import { expect, request, test, type APIRequestContext, type Page } from "@playwright/test";
import { loginAsRole, roleCredentials } from "./helpers/auth";
import { drainQueue } from "./helpers/backend";
import { captureEvidence } from "./helpers/evidence";

const apiBaseUrl = (process.env.VITE_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1").replace(
  /\/?$/,
  "/"
);

type ApiEnvelope<T> = {
  success: boolean;
  message: string;
  data: T;
};

type EmployeeListPayload = {
  data: Array<{
    id: number;
    user?: {
      email?: string;
    };
  }>;
  meta: Record<string, unknown>;
};

type ProjectPayload = {
  id: number;
};

type TaskPayload = {
  id: number;
};

type NotificationPayload = {
  id: string;
  title: string;
  is_read: boolean;
  data?: {
    task_id?: number;
    task_name?: string;
  };
};

const getTokenCookie = async (page: Page): Promise<string> => {
  const cookies = await page.context().cookies();
  const tokenCookie = cookies.find((cookie) => cookie.name === "token");

  if (!tokenCookie?.value) {
    throw new Error("Authentication token cookie is missing after login.");
  }

  return tokenCookie.value;
};

const createApiContext = async (token: string): Promise<APIRequestContext> => {
  return request.newContext({
    baseURL: apiBaseUrl,
    extraHTTPHeaders: {
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: `Bearer ${token}`,
    },
  });
};

const expectApiSuccess = async <T>(response: Awaited<ReturnType<APIRequestContext["get"]>>, label: string): Promise<T> => {
  expect(response.ok(), `${label} failed with HTTP ${response.status()}`).toBeTruthy();

  const json = (await response.json()) as ApiEnvelope<T>;
  expect(json.success, `${label} returned success=false`).toBe(true);

  return json.data;
};

test.describe.serial("Employee task assignment notifications", () => {
  test.setTimeout(180_000);

  test("manager assigns task and employee receives, opens, and reads notification", async ({ browser }) => {
    const managerContext = await browser.newContext();
    const managerPage = await managerContext.newPage();

    await loginAsRole(managerPage, "manager");
    const managerToken = await getTokenCookie(managerPage);
    const managerApi = await createApiContext(managerToken);

    const employeesData = await expectApiSuccess<EmployeeListPayload>(
      await managerApi.get("staff-members/all/paginated?row_per_page=50"),
      "Fetch employee list"
    );

    const employeeProfile = employeesData.data.find(
      (item) => item.user?.email === roleCredentials.employee.email
    );

    if (!employeeProfile) {
      throw new Error("Seeded employee profile agung@teamsync.com was not found.");
    }

    const timestamp = Date.now();
    const taskName = `E2E Task Assignment ${timestamp}`;

    const projectData = await expectApiSuccess<ProjectPayload>(
      await managerApi.post("projects", {
        data: {
          name: `E2E Project ${timestamp}`,
          type: "web_development",
          priority: "medium",
          status: "active",
          start_date: new Date().toISOString().slice(0, 10),
          end_date: null,
          description: "Project created for employee notification E2E.",
          project_leader_id: employeeProfile.id,
          teams: [],
        },
      }),
      "Create project"
    );

    const taskData = await expectApiSuccess<TaskPayload>(
      await managerApi.post("project-tasks", {
        data: {
          project_id: projectData.id,
          name: taskName,
          description: "Validate assignment notification flow.",
          assignee_id: employeeProfile.id,
          priority: "medium",
          status: "todo",
          due_date: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().slice(0, 10),
        },
      }),
      "Create assigned task"
    );

    await managerApi.dispose();
    await managerContext.close();

    drainQueue();

    const employeeContext = await browser.newContext();
    const employeePage = await employeeContext.newPage();

    await loginAsRole(employeePage, "employee");
    await expect(employeePage.getByTestId("header-notification-toggle")).toBeVisible();

    // Retry polling: open panel, check notification, reload if not found
    let taskNotificationFound = false;
    for (let attempt = 0; attempt < 5; attempt++) {
      await employeePage.getByTestId("header-notification-toggle").click();
      await expect(employeePage.getByTestId("header-notification-panel")).toBeVisible();

      const taskNotificationLocator = employeePage
        .locator('[data-testid^="notification-select-"]')
        .filter({ hasText: taskName })
        .first();

      try {
        await expect(taskNotificationLocator).toBeVisible({ timeout: 5_000 });
        taskNotificationFound = true;
        break;
      } catch {
        // Notification not yet delivered — drain queue again and reload
        drainQueue(3);
        await employeePage.reload();
        await employeePage.waitForTimeout(1_000);
      }
    }

    if (!taskNotificationFound) {
      // Open panel one last time for the final assertion
      await employeePage.getByTestId("header-notification-toggle").click();
      await expect(employeePage.getByTestId("header-notification-panel")).toBeVisible();
    }

    const taskNotification = employeePage
      .locator('[data-testid^="notification-select-"]')
      .filter({ hasText: taskName })
      .first();

    await expect(taskNotification).toBeVisible({ timeout: 10_000 });
    await taskNotification.click();

    await expect(employeePage).toHaveURL(new RegExp(`/admin/projects/${projectData.id}$`));

    const employeeToken = await getTokenCookie(employeePage);
    const employeeApi = await createApiContext(employeeToken);

    const myNotifications = await expectApiSuccess<NotificationPayload[]>(
      await employeeApi.get("my-notifications?limit=20"),
      "Fetch employee notifications"
    );

    const targetNotification = myNotifications.find(
      (item) => (item.data?.task_id ?? 0) === taskData.id
    );

    expect(targetNotification, "Task assignment notification should exist").toBeTruthy();
    expect(targetNotification?.title).toBe("New Task Assigned");
    expect(targetNotification?.is_read).toBe(true);

    await captureEvidence(employeePage, "employee-task-notification-read.png");

    await employeeApi.dispose();
    await employeeContext.close();
  });
});