import fs from "node:fs";
import path from "node:path";
import type { Page } from "@playwright/test";

const evidenceDir = path.resolve(process.cwd(), "playwright-artifacts");

export const captureEvidence = async (page: Page, fileName: string) => {
  fs.mkdirSync(evidenceDir, { recursive: true });
  const targetPath = path.join(evidenceDir, fileName);

  await page.screenshot({
    path: targetPath,
    fullPage: true,
  });
};
