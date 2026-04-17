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

export const processQueueOnce = () => {
  runInBackendContainer(
    "php artisan queue:work --once --queue=default --tries=1 --timeout=600"
  );
};
