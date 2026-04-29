import { expect, test } from "@playwright/test";
import { loginAsRole } from "./helpers/auth";

const tomorrowDateTimeLocal = () => {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(9, 0, 0, 0);

    const year = tomorrow.getFullYear();
    const month = String(tomorrow.getMonth() + 1).padStart(2, "0");
    const day = String(tomorrow.getDate()).padStart(2, "0");
    const hours = String(tomorrow.getHours()).padStart(2, "0");
    const minutes = String(tomorrow.getMinutes()).padStart(2, "0");

    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

test.describe.serial("Schedule meeting flow", () => {
    test.setTimeout(120_000);

    test("hr schedules meeting from quick actions and sees it in meetings list", async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        try {
            const timestamp = Date.now();
            const meetingTitle = `E2E Meeting Test ${timestamp}`;

            await loginAsRole(page, "hr");
            await expect(page).toHaveURL(/\/admin\/dashboard$/);
            await expect(page.getByText("Quick Actions")).toBeVisible();

            const quickActionScheduleButton = page.locator('button[data-action-id="schedule-meeting"]');
            await expect(quickActionScheduleButton).toBeVisible();
            await quickActionScheduleButton.click();

            const scheduleModal = page.getByRole("dialog");
            await expect(scheduleModal.getByText("Schedule Meeting").first()).toBeVisible();

            await scheduleModal.locator('input[placeholder="Meeting title"]').fill(meetingTitle);
            await scheduleModal
                .locator('input[type="datetime-local"]')
                .fill(tomorrowDateTimeLocal());
            await scheduleModal.locator("select").selectOption("60");
            await scheduleModal
                .locator('input[placeholder="Paste GMeet/Zoom link or enter location"]')
                .fill("https://meet.google.com/test-link");

            await scheduleModal.getByLabel("Development").check();

            await scheduleModal
                .getByRole("button", { name: "Schedule Meeting", exact: true })
                .click();

            await expect(scheduleModal).not.toBeVisible({ timeout: 15_000 });

            await page.goto("/admin/meetings");
            await expect(page).toHaveURL(/\/admin\/meetings$/);

            const createdMeetingRow = page.locator("tbody tr").filter({ hasText: meetingTitle }).first();
            await expect(createdMeetingRow).toBeVisible({ timeout: 20_000 });
            await expect(createdMeetingRow.locator("td").first()).toHaveText(meetingTitle);
        } finally {
            await context.close();
        }
    });
});
