import { fileURLToPath, URL } from "node:url";
import { defineConfig } from "vitest/config";
import vue from "@vitejs/plugin-vue";

export default defineConfig({
  cacheDir: process.env.VITE_CACHE_DIR,
  plugins: [vue()],
  resolve: {
    alias: {
      "@": fileURLToPath(new URL("./src", import.meta.url)),
    },
  },
  test: {
    environment: "jsdom",
    globals: true,
    include: ["src/**/*.test.{js,ts}"],
    coverage: {
      provider: "v8",
      include: ["src/**/*.{js,vue}"],
      exclude: ["src/tests/**", "src/**/*.test.js", "src/**/*.spec.js"],
      reporter: ["text", "json", "json-summary", "lcov"],
    },
  },
});
