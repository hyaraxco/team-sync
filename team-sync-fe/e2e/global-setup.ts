import type { FullConfig } from "@playwright/test";
import { roleCredentials } from "./helpers/auth";

const defaultApiBaseUrl = "http://127.0.0.1:8000/api/v1";

const sleep = (ms: number) =>
  new Promise((resolve) => {
    setTimeout(resolve, ms);
  });

const withTimeout = async (input: RequestInfo | URL, init: RequestInit = {}, timeoutMs = 10_000) => {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);
  try {
    return await fetch(input, {
      ...init,
      signal: controller.signal,
    });
  } finally {
    clearTimeout(timeout);
  }
};

const verifyBackendHealth = async (apiBaseUrl: string) => {
  const healthUrl = `${apiBaseUrl.replace(/\/api\/v1\/?$/, "")}/up`;
  let lastError = "unknown error";

  for (let attempt = 1; attempt <= 20; attempt += 1) {
    try {
      const response = await withTimeout(healthUrl, {}, 5_000);
      if (response.ok) {
        return;
      }
      lastError = `HTTP ${response.status}`;
    } catch (error) {
      lastError = error instanceof Error ? error.message : String(error);
    }

    await sleep(1_000);
  }

  throw new Error(
    `E2E precheck failed: backend health endpoint is not ready (${healthUrl}). Last error: ${lastError}.`
  );
};

const verifySeededLogins = async (apiBaseUrl: string) => {
  const loginUrl = `${apiBaseUrl.replace(/\/$/, "")}/login`;

  for (const [role, credentials] of Object.entries(roleCredentials)) {
    const response = await withTimeout(
      loginUrl,
      {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
        body: JSON.stringify(credentials),
      },
      10_000
    );

    if (!response.ok) {
      let responseBody = "";
      try {
        responseBody = await response.text();
      } catch {
        responseBody = "<response body unavailable>";
      }

      throw new Error(
        `E2E precheck failed: seeded login for role "${role}" is unavailable (${response.status}). ` +
          `Run "bun run e2e:prepare:be" first. URL: ${loginUrl}. Body: ${responseBody.slice(0, 300)}`
      );
    }
  }
};

export default async function globalSetup(config: FullConfig) {
  const apiBaseUrl =
    process.env.VITE_API_BASE_URL ??
    (config.projects[0]?.use as { apiBaseUrl?: string } | undefined)?.apiBaseUrl ??
    defaultApiBaseUrl;

  await verifyBackendHealth(apiBaseUrl);
  await verifySeededLogins(apiBaseUrl);
}
