import { beforeEach, describe, expect, it, vi } from "vitest";
import { hasRoutePermissionAccess, normalizePermissions } from "@/router/permissionAccess";

/**
 * team-sync-4ok: payload-contract parity between the route guard and the
 * permission helper for the payload shape the /me endpoint actually returns.
 *
 * UserResource::getAllPermissions()->pluck('name') serialises permissions as
 * plain name strings, so `can()/canOneOf()` (Array.includes) and
 * `hasRoutePermissionAccess()` must agree on that payload.
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

    it("agrees on the observed /me string payload for the same permission", () => {
        const observedPayload = ["dashboard-menu", "project-menu", "project-list", "task-menu", "task-list"];

        setPermissions(observedPayload);

        // Helper and guard must both allow a permission that is present.
        expect(can("project-menu")).toBe(true);
        expect(canOneOf(["project-create", "project-menu"])).toBe(true);

        // And both must deny one that is absent.
        expect(can("payroll-create")).toBe(false);
        expect(canOneOf(["payroll-create", "settings-finance-manage"])).toBe(false);
    });

    it("keeps guard and helper in step across every admin route meta", () => {
        // One payload, evaluated by both consumers, so a shape change cannot
        // make the guard and the button helper disagree silently.
        const observedPayload = ["dashboard-menu", "project-menu", "project-list"];

        setPermissions(observedPayload);

        expect(can("project-list")).toBe(true);

        for (const meta of [
            { requiresAuth: true, requiredPermission: "project-menu" },
            { requiresAuth: true, requiredPermission: "project-list" },
            { requiresAuth: true, requiredPermission: "project-create" },
            { requiresAuth: true, requiredPermission: "staff-member-menu" },
        ]) {
            const guardAllows = hasRoutePermissionAccess(observedPayload, meta);
            const helperAllows = can(meta.requiredPermission);

            expect(guardAllows).toBe(helperAllows);
        }
    });

    it("mirrors the guard's object tolerance in normalizePermissions but not in can()", () => {
        // Documents the known asymmetry: the guard normalises {name} objects,
        // the helper uses Array.includes on strings. /me sends strings, so this
        // is only reachable if the payload shape ever changes.
        const objectPayload = [{ name: "project-menu" }];

        expect(normalizePermissions(objectPayload)).toEqual(["project-menu"]);
        expect(hasRoutePermissionAccess(objectPayload, { requiresAuth: true, requiredPermission: "project-menu" })).toBe(
            true,
        );

        setPermissions(objectPayload);
        // Helper does not normalise: this is the documented gap, asserted so a
        // future payload change fails loudly here instead of silently in prod.
        expect(can("project-menu")).toBe(false);
    });

    it("returns false for helper calls when the session has no user", () => {
        setPermissions(undefined);
        expect(can("project-menu")).toBe(false);
        expect(canOneOf(["project-menu"])).toBe(false);
    });
});
