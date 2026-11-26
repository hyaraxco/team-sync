import { defineConfig } from "@playwright/test";

const CHROME =
  process.env.LZO_CHROME ||
  `${process.env.HOME}/Library/Caches/ms-playwright/chromium-1248/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing`;

export default defineConfig({
    testDir: "./e2e",
    globalSetup: "./e2e/global-setup.ts",
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 120_000,
    expect: { timeout: 15_000 },
    reporter: [["list"]],
    use: {
        baseURL: process.env.E2E_FE_BASE_URL ?? "http://127.0.0.1:4173",
        headless: true,
        launchOptions: { executablePath: CHROME },
        trace: "off",
        screenshot: "only-on-failure",
        actionTimeout: 20_000,
        navigationTimeout: 40_000,
    },
});
