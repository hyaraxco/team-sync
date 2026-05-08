import { describe, expect, it, vi } from "vitest";
vi.mock("@/views/staff-member/MyAttendance.vue", () => ({ default: {} }));
vi.mock("@/views/staff-member/ClockInOut.vue", () => ({ default: {} }));
vi.mock("@/stores/auth", () => ({
    useAuthStore: () => ({ token: null, user: null }),
}));
vi.mock("@/router", () => ({ default: { beforeEach: vi.fn() } }));

vi.mock("@/views/admin/attendance/AttendanceList.vue", () => ({
    default: {},
}));

import attendanceRoutes from "@/router/attendance";
import { hasRoutePermissionAccess } from "@/router/permissionAccess";

const routeMeta = (name) => attendanceRoutes.find((route) => route.name === name)?.meta ?? {};

describe("attendance route access", () => {
    it("requires attendance-menu for admin attendance list", () => {
        expect(hasRoutePermissionAccess(["attendance-menu"], routeMeta("admin.attendances"))).toBe(true);
        expect(hasRoutePermissionAccess(["attendance-list"], routeMeta("admin.attendances"))).toBe(false);
    });

    it("allows employee attendance workspace when attendance permissions exist", () => {
        expect(
            hasRoutePermissionAccess(["attendance-my-attendances"], routeMeta("staffMember.attendance.my-attendances")),
        ).toBe(true);
        expect(
            hasRoutePermissionAccess(["attendance-check-in"], routeMeta("staffMember.attendance.my-attendances")),
        ).toBe(true);
    });

    it("requires check-in or check-out permission for the clock alias route", () => {
        expect(hasRoutePermissionAccess(["attendance-check-out"], routeMeta("staffMember.attendance.clock"))).toBe(
            true,
        );
        expect(hasRoutePermissionAccess(["attendance-my-attendances"], routeMeta("staffMember.attendance.clock"))).toBe(
            false,
        );
    });

    it("keeps the clock alias redirecting into My Attendance via beforeEnter", () => {
        const clockRoute = attendanceRoutes.find((route) => route.name === "staffMember.attendance.clock");

        expect(clockRoute.beforeEnter?.()).toEqual({
            name: "staffMember.attendance.my-attendances",
            query: { action: "clock" },
        });
    });
});
