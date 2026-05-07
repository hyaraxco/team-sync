import { test as base, expect } from "@playwright/test";
import { loginAsRole, type RoleName } from "../../helpers/auth";
import { UserFactory } from "../factories/user.factory";

/**
 * Extended test fixtures for Team Sync E2E tests.
 *
 * Usage:
 *   import { test, expect } from '../support/fixtures';
 *   test('my test', async ({ authenticatedPage, userFactory }) => { ... });
 */

type TeamSyncFixtures = {
    /** A page already logged in as the specified role */
    authenticatedPage: ReturnType<typeof createAuthenticatedPage>;
    /** Factory for creating test users via API */
    userFactory: UserFactory;
    /** Login as a specific role on the current page */
    loginAs: (role: RoleName) => Promise<void>;
};

function createAuthenticatedPage() {
    return {
        page: null as any,
        role: "hr" as RoleName,
    };
}

export const test = base.extend<TeamSyncFixtures>({
    authenticatedPage: async ({ page }, use) => {
        // Default: login as HR (most common E2E actor)
        await loginAsRole(page, "hr");
        await use({ page, role: "hr" });
    },

    userFactory: async ({}, use) => {
        const factory = new UserFactory();
        await use(factory);
        // Auto-cleanup after test
        await factory.cleanup();
    },

    loginAs: async ({ page }, use) => {
        const login = async (role: RoleName) => {
            await loginAsRole(page, role);
        };
        await use(login);
    },
});

export { expect };
