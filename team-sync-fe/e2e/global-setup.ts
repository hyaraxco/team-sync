import type { FullConfig } from "@playwright/test";

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


export default async function globalSetup(config: FullConfig) {
  const apiBaseUrl =
    process.env.VITE_API_BASE_URL ??
    (config.projects[0]?.use as { apiBaseUrl?: string } | undefined)?.apiBaseUrl ??
    defaultApiBaseUrl;

  await verifyBackendHealth(apiBaseUrl);
}
