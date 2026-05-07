/**
 * User factory for E2E test data creation.
 *
 * Creates test entities via the API and tracks them for cleanup.
 * Uses deterministic data generation (no faker dependency needed
 * since the backend seeds realistic data via MinimalPayrollE2ESeeder).
 *
 * Usage:
 *   const factory = new UserFactory();
 *   const user = factory.buildUserPayload({ name: 'Custom Name' });
 *   await factory.cleanup();
 */

export interface UserPayload {
    name: string;
    email: string;
    password: string;
    role: string;
}

export class UserFactory {
    private createdIds: number[] = [];
    private counter = 0;

    /**
     * Build a user payload with sensible defaults.
     * Override any field by passing partial overrides.
     */
    buildUserPayload(overrides: Partial<UserPayload> = {}): UserPayload {
        this.counter += 1;
        const timestamp = Date.now();

        return {
            name: `E2E Test User ${this.counter}`,
            email: `e2e-user-${timestamp}-${this.counter}@teamsync.test`,
            password: "teamsync-e2e-password",
            role: "staff",
            ...overrides,
        };
    }

    /**
     * Track a created entity ID for later cleanup.
     */
    track(id: number): void {
        this.createdIds.push(id);
    }

    /**
     * Get all tracked entity IDs.
     */
    getTrackedIds(): number[] {
        return [...this.createdIds];
    }

    /**
     * Cleanup all tracked entities.
     * In this project, E2E tests run against a seeded database
     * that is reset before each E2E run via e2e:prepare:be,
     * so cleanup is a no-op by default. This method exists
     * for future use if per-test cleanup becomes necessary.
     */
    async cleanup(): Promise<void> {
        this.createdIds = [];
        this.counter = 0;
    }
}
