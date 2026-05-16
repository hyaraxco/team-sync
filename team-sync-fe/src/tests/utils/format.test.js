import { describe, it, expect, vi, beforeEach } from "vitest";

const { authStoreMock } = vi.hoisted(() => ({
    authStoreMock: {
        user: null,
    },
}));

vi.mock("@/stores/auth", () => ({
    useAuthStore: () => authStoreMock,
}));

const { formatToClientTimezone } = await import("@/helpers/format");

describe("formatToClientTimezone", () => {
    beforeEach(() => {
        authStoreMock.user = null;
    });

    it("uses company_timezone from auth store when available", () => {
        authStoreMock.user = { company_timezone: "Asia/Makassar" };
        const result = formatToClientTimezone("2024-01-15T10:00:00Z");
        // Asia/Makassar is UTC+8, so 10:00 UTC -> 18:00 WITA
        expect(result).toContain("18:00");
    });

    it("falls back to Asia/Jakarta when user has no company_timezone", () => {
        authStoreMock.user = { name: "Test User" };
        const result = formatToClientTimezone("2024-01-15T10:00:00Z");
        // Asia/Jakarta is UTC+7, so 10:00 UTC -> 17:00 WIB
        expect(result).toContain("17:00");
    });

    it("falls back to Asia/Jakarta when user is null", () => {
        authStoreMock.user = null;
        const result = formatToClientTimezone("2024-01-15T10:00:00Z");
        // Asia/Jakarta is UTC+7, so 10:00 UTC -> 17:00 WIB
        expect(result).toContain("17:00");
    });

    it("uses custom format when provided", () => {
        authStoreMock.user = { company_timezone: "Asia/Jakarta" };
        const result = formatToClientTimezone("2024-01-15T10:00:00Z", "yyyy-MM-dd");
        expect(result).toBe("2024-01-15");
    });

    it("formats in Indonesian locale", () => {
        authStoreMock.user = { company_timezone: "Asia/Jakarta" };
        const result = formatToClientTimezone("2024-01-15T10:00:00Z", "dd MMM yyyy");
        expect(result).toBe("15 Jan 2024");
    });
});
