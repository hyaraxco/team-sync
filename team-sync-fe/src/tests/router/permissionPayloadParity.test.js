import { beforeEach, describe, expect, it, vi } from "vitest";
import { hasRoutePermissionAccess } from "@/router/permissionAccess";

/**
 * team-sync-4ok: payload parity between the route guard and the permission
 * helper for the payload shape the /me endpoint actually returns.
 *
 * UserResource::getAllPermissions()->pluck('name') serialises permissions as
 * plain name strings, so `can()/canOneOf()` (Array.includes) and
 * `hasRoutePermissionAccess()` must agree on that payload.
 *
 * Limitation, not a contract: `hasRoutePermissionAccess` also tolerates
 * `{name}` objects via normalizePermissions while `can()` does not. That shape
 * never occurs in the /me contract, so it is not asserted here and must not be
 * pinned as expected behaviour; if the payload ever changes, the helper needs
 * its own normalisation.
 */

const authState = vi.hoisted(() => ({ user: null }));

vi.mock("@/stores/auth", () => ({
    useAuthStore: () => authState,
}));

import { can, canOneOf } from "@/helpers/permissionHelper";

const setPermissions = (permissions) => {
    authState.user = { permissions };
};

describe("permission payload parity (guard vs helper)", () => {
    beforeEach(() => {
        authState.user = null;
    });

    it("allows and denies the same permissions on the observed /me string payload", () => {
        const observedPayload = ["dashboard-menu", "project-menu", "project-list", "task-menu", "task-list"];

        setPermissions(observedPayload);

        expect(can("project-menu")).toBe(true);
        expect(canOneOf(["project-create", "project-menu"])).toBe(true);

        expect(can("payroll-create")).toBe(false);
        expect(canOneOf(["payroll-create", "settings-finance-manage"])).toBe(false);
    });

    it("agrees with the route guard for a sampled set of route permissions", () => {
        // Single-permission metas only: these routes declare requiredPermission,
        // not requiredAnyPermissions, so can() and the guard are comparable.
        const observedPayload = ["dashboard-menu", "project-menu", "project-list"];
        const sampledMetas = [
            { requiresAuth: true, requiredPermission: "project-menu" },
            { requiresAuth: true, requiredPermission: "project-list" },
            { requiresAuth: true, requiredPermission: "project-create" },
            { requiresAuth: true, requiredPermission: "staff-member-menu" },
        ];

        setPermissions(observedPayload);

        for (const meta of sampledMetas) {
            expect(hasRoutePermissionAccess(observedPayload, meta)).toBe(can(meta.requiredPermission));
        }
    });

    it("reads the latest user after the permission set is replaced", () => {
        // Verifies the helper resolves live state rather than a cached snapshot,
        // which is what makes a re-login or role revocation effective.
        setPermissions(["project-menu", "payroll-create"]);
        expect(can("payroll-create")).toBe(true);

        setPermissions(["project-menu"]);

        expect(can("payroll-create")).toBe(false);
        expect(can("project-menu")).toBe(true);
        expect(hasRoutePermissionAccess(["project-menu"], { requiresAuth: true, requiredPermission: "payroll-create" }))
            .toBe(false);
    });

    it("returns false when the session has no user", () => {
        authState.user = null;
        expect(can("project-menu")).toBe(false);
        expect(canOneOf(["project-menu"])).toBe(false);
    });
});
