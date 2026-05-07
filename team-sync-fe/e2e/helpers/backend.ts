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

export const drainQueue = (maxJobs = 10) => {
  for (let i = 0; i < maxJobs; i++) {
    try {
      execSync(
        `cd "${backendDir}" && ${composeCommand} exec -T web php artisan queue:work --once --queue=${e2eQueues} --tries=1 --timeout=600 --stop-when-empty`,
        { stdio: "pipe", timeout: 605_000 }
      );
    } catch {
      break;
    }
  }
};
