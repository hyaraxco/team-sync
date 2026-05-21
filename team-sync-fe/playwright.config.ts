import { defineConfig } from "@playwright/test";

const feBaseUrl = process.env.E2E_FE_BASE_URL ?? "http://127.0.0.1:4173";
const apiBaseUrl = process.env.VITE_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1";

export default defineConfig({
    testDir: "./e2e",
    globalSetup: "./e2e/global-setup.ts",
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    timeout: 60_000,
    expect: {
        timeout: 10_000,
    },
    reporter: [
        ["list"],
        ["html", { open: "never", outputFolder: "playwright-report" }],
        ["junit", { outputFile: "test-results/junit-results.xml" }],
    ],
    use: {
        baseURL: feBaseUrl,
        headless: false,
        trace: "retain-on-failure",
        screenshot: "only-on-failure",
        video: "retain-on-failure",
        actionTimeout: 15_000,
        navigationTimeout: 30_000,
    },
    webServer: {
        command: `VITE_API_BASE_URL=${apiBaseUrl} bun run dev --host 127.0.0.1 --port 4173`,
        url: feBaseUrl,
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
    },
});
