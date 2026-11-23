import { execSync } from "node:child_process";
import path from "node:path";

const backendDir = process.env.E2E_BE_DIR
  ? path.resolve(process.cwd(), process.env.E2E_BE_DIR)
  : path.resolve(process.cwd(), "../team-sync-be");

const composeCommand = process.env.E2E_BE_COMPOSE_CMD ?? "docker compose";

const runInBackendContainer = (artisanCommand: string) => {
  const command = `cd "${backendDir}" && ${composeCommand} exec -T web ${artisanCommand}`;
  execSync(command, { stdio: "inherit" });
};

const e2eQueues = process.env.E2E_QUEUE_NAMES ?? "default,meetings";

export const processQueueOnce = () => {
  runInBackendContainer(
    `php artisan queue:work --once --queue=${e2eQueues} --tries=1 --timeout=600`
  );
};

// Select the worker before executing jobs; worker failures must not trigger a retry on another DB.
export const drainQueue = (maxJobs = 10) => {
  let containerAvailable = false;
  try {
    containerAvailable = execSync(
      `cd "${backendDir}" && ${composeCommand} ps --status running --quiet web`,
      { encoding: "utf8", stdio: "pipe", timeout: 15_000 }
    ).trim().length > 0;
  } catch {
    // Compose is unavailable in host-only E2E environments.
  }

  const worker = containerAvailable ? `${composeCommand} exec -T web php` : "php";
  for (let i = 0; i < maxJobs; i++) {
    execSync(
      `cd "${backendDir}" && ${worker} artisan queue:work --once --queue=${e2eQueues} --tries=1 --timeout=600 --stop-when-empty`,
      { stdio: "pipe", timeout: 605_000 }
    );
  }
};
