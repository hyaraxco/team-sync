import js from "@eslint/js";
import pluginVue from "eslint-plugin-vue";
import eslintConfigPrettier from "eslint-config-prettier";
import tseslint from "typescript-eslint";
import globals from "globals";

export default [
    // Base JS recommended rules
    js.configs.recommended,

    // TypeScript parser support (for legacy lang="ts" Vue files)
    ...tseslint.configs.recommended.map((config) => ({
        ...config,
        files: ["**/*.ts", "**/*.vue"],
    })),

    // Vue 3 essential rules (catches real bugs, not style)
    ...pluginVue.configs["flat/essential"],

    // Prettier disables conflicting formatting rules
    eslintConfigPrettier,

    // Global settings
    {
        languageOptions: {
            ecmaVersion: "latest",
            sourceType: "module",
            globals: {
                ...globals.browser,
                ...globals.node,
            },
        },
        rules: {
            // Relax rules for existing codebase
            "no-unused-vars": ["warn", { argsIgnorePattern: "^_", varsIgnorePattern: "^_", caughtErrorsIgnorePattern: "^_" }],
            "no-console": ["warn", { allow: ["warn", "error"] }],
            "no-debugger": "error",
            "no-useless-escape": "warn",
            "no-useless-catch": "warn",

            // TypeScript — relax for JS-first project with some TS leftovers
            "@typescript-eslint/no-explicit-any": "off",
            "@typescript-eslint/no-unused-vars": "off",
            "@typescript-eslint/only-throw-error": "off",
            "preserve-caught-error": "off",

            // Vue-specific
            "vue/multi-word-component-names": "off",
            "vue/no-reserved-component-names": "off",
            "vue/require-default-prop": "off",
            "vue/no-unused-vars": "warn",
            "vue/no-parsing-error": "warn",
            "vue/valid-attribute-name": "warn",
        },
    },

    // Vue files: TS parser + disable no-unused-vars (can't see template usage)
    {
        files: ["**/*.vue"],
        languageOptions: {
            parserOptions: {
                parser: tseslint.parser,
            },
        },
        rules: {
            "no-unused-vars": "off",
            "@typescript-eslint/no-unused-vars": "off",
        },
    },

    // Test files: allow globals (describe, it, expect, vi, etc.)
    {
        files: ["src/**/*.test.js", "src/tests/**/*.js", "e2e/**/*.ts"],
        languageOptions: {
            globals: {
                ...globals.jest,
                vi: "readonly",
                describe: "readonly",
                it: "readonly",
                expect: "readonly",
                beforeEach: "readonly",
                afterEach: "readonly",
                beforeAll: "readonly",
                afterAll: "readonly",
            },
        },
    },

    // Ignore patterns
    {
        ignores: [
            "dist/",
            "node_modules/",
            "*.min.js",
            "coverage/",
            "e2e-results/",
            "playwright-report/",
        ],
    },
];
