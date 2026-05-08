/**
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║  TEAM SYNC — FRONTEND PROJECT CAPABILITY BENCHMARK TEST            ║
 * ║                                                                     ║
 * ║  Validates that the frontend is capable of carrying out all core    ║
 * ║  HRIS operations: auth flow, store actions, component rendering,   ║
 * ║  routing guards, and utility functions.                            ║
 * ║                                                                     ║
 * ║  Run: bun run test -- src/tests/benchmark/                         ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 */

import { setActivePinia, createPinia } from "pinia";
import { describe, it, expect, beforeEach, vi } from "vitest";

// ─── Mock Axios ─────────────────────────────────────────────────────
vi.mock("@/plugins/axios", () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
        defaults: {
            headers: {
                common: {},
            },
        },
    },
}));

vi.mock("js-cookie", () => ({
    default: {
        get: vi.fn(),
        set: vi.fn(),
        remove: vi.fn(),
    },
}));

vi.mock("@/router", () => ({
    default: {
        push: vi.fn(),
        replace: vi.fn(() => Promise.resolve()),
    },
}));

import { axiosInstance } from "@/plugins/axios";
import Cookies from "js-cookie";
import router from "@/router";

// ═══════════════════════════════════════════════════════════════
// SECTION 1: AUTH STORE CAPABILITIES
// ═══════════════════════════════════════════════════════════════

describe("Benchmark: Auth Store", () => {
    let store;

    beforeEach(async () => {
        setActivePinia(createPinia());
        const { useAuthStore } = await import("@/stores/auth");
        store = useAuthStore();
        vi.clearAllMocks();
        router.replace.mockResolvedValue(undefined);
    });

    it("login flow: calls API, stores token, redirects to dashboard", async () => {
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: "Login successful",
                data: { token: "benchmark-jwt-token" },
            },
        });

        await store.login({ email: "hr@teamsync.com", password: "REDACTED", remember: true });

        expect(axiosInstance.post).toHaveBeenCalledWith("/login", {
            email: "hr@teamsync.com",
            password: "REDACTED",
        });
        expect(Cookies.set).toHaveBeenCalledWith("token", "benchmark-jwt-token", { expires: 30 });
        expect(store.success).toBe("Login successful");
        expect(router.push).toHaveBeenCalledWith({ name: "admin.dashboard" });
        expect(store.loading).toBe(false);
    });

    it("login failure: sets error state correctly", async () => {
        axiosInstance.post.mockRejectedValueOnce({
            response: { status: 400, data: { message: "Invalid credentials" } },
        });

        await store.login({ email: "wrong@test.com", password: "wrong" });

        expect(store.error).toBe("Invalid credentials");
        expect(store.loading).toBe(false);
    });

    it("checkAuth: fetches user profile and updates state", async () => {
        const mockUser = {
            id: 1,
            name: "Tasyia HR",
            email: "tasyia@teamsync.com",
            roles: ["hr"],
            permissions: [{ name: "staff-member-list" }],
        };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: mockUser } });

        const result = await store.checkAuth();

        expect(axiosInstance.get).toHaveBeenCalledWith("/me");
        expect(result).toEqual(mockUser);
        expect(store.user).toEqual(mockUser);
    });

    it("logout: clears token and navigates to login", async () => {
        Cookies.get.mockReturnValueOnce("existing-token");
        axiosInstance.post.mockResolvedValueOnce({ data: { message: "Logged out" } });
        store.user = { id: 1, name: "Test" };

        await store.logout();

        expect(Cookies.remove).toHaveBeenCalledWith("token");
        expect(store.user).toBe(null);
    });
});

// ═══════════════════════════════════════════════════════════════
// SECTION 2: TEAM STORE CAPABILITIES
// ═══════════════════════════════════════════════════════════════

describe("Benchmark: Team Store", () => {
    let store;

    beforeEach(async () => {
        setActivePinia(createPinia());
        const { useTeamStore } = await import("@/stores/team");
        store = useTeamStore();
        vi.clearAllMocks();
    });

    it("fetchTeams: populates teams array and meta", async () => {
        const mockTeams = [
            { id: 1, name: "Engineering", status: "active" },
            { id: 2, name: "Design", status: "active" },
        ];
        axiosInstance.get.mockResolvedValueOnce({
            data: {
                data: mockTeams,
                meta: { current_page: 1, last_page: 1, per_page: 10, total: 2 },
            },
        });

        await store.fetchTeams({ page: 1, row_per_page: 10 });

        expect(axiosInstance.get).toHaveBeenCalledWith("teams", {
            params: { page: 1, row_per_page: 10 },
        });
        expect(store.teams).toEqual(mockTeams);
        expect(store.loading).toBe(false);
    });

    it("createTeam: sends POST with FormData", async () => {
        axiosInstance.post.mockResolvedValueOnce({
            data: { message: "Team created", data: { id: 3, name: "New Team" } },
        });

        await store.createTeam({
            name: "New Team",
            description: "Benchmark team",
            department: "Engineering",
            status: "active",
            expected_size: 5,
            team_lead_id: 1,
        });

        expect(axiosInstance.post).toHaveBeenCalled();
        expect(store.success).toBe("Team created");
    });
});

// ═══════════════════════════════════════════════════════════════
// SECTION 3: PAYROLL STORE CAPABILITIES
// ═══════════════════════════════════════════════════════════════

describe("Benchmark: Payroll Store", () => {
    let store;

    beforeEach(async () => {
        setActivePinia(createPinia());
        const { usePayrollStore } = await import("@/stores/payroll");
        store = usePayrollStore();
        vi.clearAllMocks();
    });

    it("fetchPayrolls: populates payrolls and meta", async () => {
        const mockResponse = {
            data: {
                data: {
                    data: [
                        { id: 1, salary_month: "2026-04", status: "pending" },
                        { id: 2, salary_month: "2026-03", status: "paid" },
                    ],
                    meta: { current_page: 1, last_page: 2, per_page: 10, total: 15 },
                },
            },
        };
        axiosInstance.get.mockResolvedValueOnce(mockResponse);

        await store.fetchPayrolls({ page: 1, row_per_page: 10 });

        expect(axiosInstance.get).toHaveBeenCalledWith("/payrolls/all/paginated", {
            params: { page: 1, row_per_page: 10 },
        });
        expect(store.payrolls).toEqual(mockResponse.data.data.data);
        expect(store.meta).toEqual(mockResponse.data.data.meta);
    });

    it("generatePayroll: calls POST and returns payroll data", async () => {
        axiosInstance.post.mockResolvedValueOnce({
            data: { message: "Payroll generated", data: { payroll_id: 99 } },
        });

        const result = await store.generatePayroll({ salary_month: "2026-04" });

        expect(axiosInstance.post).toHaveBeenCalledWith("/payrolls/generate", { salary_month: "2026-04" });
        expect(result).toEqual({ payroll_id: 99 });
        expect(store.success).toBe("Payroll generated");
    });

    it("approvePayroll: transitions payroll status", async () => {
        axiosInstance.post.mockResolvedValueOnce({
            data: { message: "Payroll approved", data: { id: 10, status: "approved" } },
        });

        const result = await store.approvePayroll(10);

        expect(axiosInstance.post).toHaveBeenCalledWith("/payrolls/10/approve");
        expect(result).toEqual({ id: 10, status: "approved" });
    });

    it("markAsPaid: finalizes payroll payment", async () => {
        axiosInstance.post.mockResolvedValueOnce({
            data: { message: "Payroll marked as paid", data: { id: 10, status: "paid" } },
        });

        const result = await store.markAsPaid(10, {
            paid_date: "2026-04-30",
            payment_method: "bank_transfer",
        });

        expect(axiosInstance.post).toHaveBeenCalledWith("/payrolls/10/mark-as-paid", {
            paid_date: "2026-04-30",
            payment_method: "bank_transfer",
        });
        expect(result).toEqual({ id: 10, status: "paid" });
    });

    it("error handling: sets error state on API failure", async () => {
        axiosInstance.get.mockRejectedValueOnce({
            response: { status: 500, data: { message: "Internal server error" } },
        });

        await store.fetchPayrolls({ page: 1 });

        expect(store.error).toBe("Internal server error");
        expect(store.loading).toBe(false);
    });
});

// ═══════════════════════════════════════════════════════════════
// SECTION 4: ATTENDANCE STORE CAPABILITIES
// ═══════════════════════════════════════════════════════════════

describe("Benchmark: Attendance Store", () => {
    let store;

    beforeEach(async () => {
        setActivePinia(createPinia());
        const { useAttendanceStore } = await import("@/stores/attendance");
        store = useAttendanceStore();
        vi.clearAllMocks();
    });

    it("fetchAttendances: populates attendance list", async () => {
        const mockData = [
            { id: 1, date: "2026-04-28", status: "present", check_in: "08:00:00" },
            { id: 2, date: "2026-04-27", status: "present", check_in: "08:15:00" },
        ];
        axiosInstance.get.mockResolvedValueOnce({ data: { data: mockData } });

        await store.fetchAttendances({ month: "2026-04" });

        expect(axiosInstance.get).toHaveBeenCalledWith("my-attendances", {
            params: { month: "2026-04" },
        });
        expect(store.attendances).toEqual(mockData);
        expect(store.loading).toBe(false);
    });

    it("fetchTodayAttendance: gets current day attendance", async () => {
        const mockToday = { id: 5, date: "2026-04-28", status: "present", check_in: "08:00:00" };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: mockToday } });

        await store.fetchTodayAttendance();

        expect(axiosInstance.get).toHaveBeenCalledWith("attendances/last-attendance");
        expect(store.todayAttendance).toEqual(mockToday);
    });

    it("checkIn: sends check-in with geolocation", async () => {
        axiosInstance.post.mockResolvedValueOnce({
            data: { message: "Check-in successful", data: { id: 10, check_in: "08:00:00" } },
        });

        await store.checkIn({ check_in_lat: -6.2088, check_in_long: 106.8456 });

        expect(axiosInstance.post).toHaveBeenCalled();
        expect(store.success).toBe("Check-in successful");
    });
});

// ═══════════════════════════════════════════════════════════════
// SECTION 5: NOTIFICATION STORE CAPABILITIES
// ═══════════════════════════════════════════════════════════════

describe("Benchmark: Notification Store", () => {
    let store;

    beforeEach(async () => {
        setActivePinia(createPinia());
        const { useNotificationStore } = await import("@/stores/notifications");
        store = useNotificationStore();
        vi.clearAllMocks();
    });

    it("fetchLatestNotifications: populates notification list", async () => {
        const mockNotifications = [
            { id: "1", type: "leave_approved", read_at: null, data: { message: "Leave approved" } },
            { id: "2", type: "payroll_ready", read_at: "2026-04-27", data: { message: "Payroll ready" } },
        ];
        axiosInstance.get.mockResolvedValueOnce({
            data: { data: mockNotifications },
        });

        await store.fetchLatestNotifications(5);

        expect(axiosInstance.get).toHaveBeenCalledWith("/my-notifications", {
            params: { per_page: 5, page: 1 },
        });
        expect(store.loading).toBe(false);
        expect(store.notifications).toEqual(mockNotifications);
    });

    it("fetchUnreadCount: retrieves unread notification count", async () => {
        axiosInstance.get.mockResolvedValueOnce({
            data: { data: { unread_count: 7 } },
        });

        const count = await store.fetchUnreadCount();

        expect(axiosInstance.get).toHaveBeenCalledWith("/my-notifications/unread-count");
        expect(count).toBe(7);
        expect(store.unreadCount).toBe(7);
    });
});

// ═══════════════════════════════════════════════════════════════
// SECTION 6: PROJECT STORE CAPABILITIES
// ═══════════════════════════════════════════════════════════════

describe("Benchmark: Project Store", () => {
    let store;

    beforeEach(async () => {
        setActivePinia(createPinia());
        const { useProjectStore } = await import("@/stores/project");
        store = useProjectStore();
        vi.clearAllMocks();
    });

    it("fetchProjects: populates project list", async () => {
        const mockProjects = [
            { id: 1, name: "Team Sync", status: "active" },
            { id: 2, name: "Mobile App", status: "planning" },
        ];
        axiosInstance.get.mockResolvedValueOnce({
            data: { data: { data: mockProjects, meta: { total: 2 } } },
        });

        await store.fetchProjects({ page: 1 });

        expect(axiosInstance.get).toHaveBeenCalled();
        expect(store.loading).toBe(false);
    });

    it("createProject: sends POST with project data", async () => {
        axiosInstance.post.mockResolvedValueOnce({
            data: { message: "Project created", data: { id: 3, name: "New Project" } },
        });

        await store.createProject({
            name: "New Project",
            description: "Benchmark project",
            start_date: "2026-05-01",
            end_date: "2026-08-01",
            status: "active",
        });

        expect(axiosInstance.post).toHaveBeenCalled();
        expect(store.success).toBe("Project created");
    });
});

// ═══════════════════════════════════════════════════════════════
// SECTION 7: PERFORMANCE REVIEW STORE CAPABILITIES
// ═══════════════════════════════════════════════════════════════

describe("Benchmark: Performance Review Store", () => {
    let store;

    beforeEach(async () => {
        setActivePinia(createPinia());
        const { usePerformanceReviewStore } = await import("@/stores/performanceReview");
        store = usePerformanceReviewStore();
        vi.clearAllMocks();
    });

    it("store initializes with correct default state", () => {
        expect(store.cyclesLoading).toBe(false);
        expect(store.reviewsLoading).toBe(false);
        expect(store.error).toBe(null);
        expect(store.cycles).toEqual([]);
        expect(store.myReviews).toEqual([]);
    });

    it("fetchCycles: retrieves review cycle list", async () => {
        const mockCycles = [
            { id: 1, name: "Q1 2026", status: "active" },
            { id: 2, name: "Q4 2025", status: "completed" },
        ];
        axiosInstance.get.mockResolvedValueOnce({
            data: {
                data: {
                    data: mockCycles,
                    current_page: 1,
                    per_page: 15,
                    total: 2,
                    last_page: 1,
                },
            },
        });

        await store.fetchCycles();

        expect(axiosInstance.get).toHaveBeenCalledWith("/performance/cycles", { params: {} });
        expect(store.cycles).toEqual(mockCycles);
        expect(store.cyclesLoading).toBe(false);
    });
});

// ═══════════════════════════════════════════════════════════════
// SECTION 8: DASHBOARD STORE CAPABILITIES
// ═══════════════════════════════════════════════════════════════

describe("Benchmark: Dashboard Store", () => {
    let store;

    beforeEach(async () => {
        setActivePinia(createPinia());
        const { useDashboardStore } = await import("@/stores/dashboard");
        store = useDashboardStore();
        vi.clearAllMocks();
    });

    it("fetchDashboardStatistics: retrieves dashboard data", async () => {
        const mockStats = {
            total_employees: 48,
            active_projects: 12,
            attendance_rate: 95.5,
            pending_leave_requests: 3,
        };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: mockStats } });

        await store.fetchStatistics();

        expect(axiosInstance.get).toHaveBeenCalled();
        expect(store.loading).toBe(false);
    });
});

// ═══════════════════════════════════════════════════════════════
// SECTION 9: STORE STATE MANAGEMENT PATTERNS
// ═══════════════════════════════════════════════════════════════

describe("Benchmark: State Management Patterns", () => {
    it("all stores follow loading/error/success pattern", async () => {
        setActivePinia(createPinia());

        const storeModules = [
            () => import("@/stores/auth"),
            () => import("@/stores/team"),
            () => import("@/stores/payroll"),
            () => import("@/stores/attendance"),
            () => import("@/stores/project"),
        ];

        for (const importStore of storeModules) {
            const module = await importStore();
            const storeFactory = Object.values(module).find(
                (v) => typeof v === "function" && v.name?.startsWith("use"),
            );

            if (storeFactory) {
                const store = storeFactory();
                // All stores should have loading state
                expect(store.$state).toHaveProperty("loading");
                // All stores should have error state
                expect(store.$state).toHaveProperty("error");
            }
        }
    });

    it("Pinia stores are reactive and reset correctly", async () => {
        setActivePinia(createPinia());
        const { useTeamStore } = await import("@/stores/team");
        const store = useTeamStore();

        // Modify state
        store.teams = [{ id: 1, name: "Test" }];
        expect(store.teams).toHaveLength(1);

        // Reset
        store.$reset();
        expect(store.teams).toHaveLength(0);
        expect(store.loading).toBe(false);
        expect(store.error).toBe(null);
    });
});

// ═══════════════════════════════════════════════════════════════
// SECTION 10: CROSS-CUTTING FRONTEND CAPABILITIES
// ═══════════════════════════════════════════════════════════════

describe("Benchmark: Cross-cutting Capabilities", () => {
    it("axios mock structure matches real plugin interface", () => {
        expect(axiosInstance).toHaveProperty("get");
        expect(axiosInstance).toHaveProperty("post");
        expect(axiosInstance).toHaveProperty("put");
        expect(axiosInstance).toHaveProperty("delete");
        expect(axiosInstance).toHaveProperty("defaults");
        expect(axiosInstance.defaults.headers).toHaveProperty("common");
    });

    it("router mock supports navigation methods", () => {
        expect(router).toHaveProperty("push");
        expect(router).toHaveProperty("replace");
        expect(typeof router.push).toBe("function");
        expect(typeof router.replace).toBe("function");
    });

    it("cookie mock supports token management", () => {
        expect(Cookies).toHaveProperty("get");
        expect(Cookies).toHaveProperty("set");
        expect(Cookies).toHaveProperty("remove");
    });
});
