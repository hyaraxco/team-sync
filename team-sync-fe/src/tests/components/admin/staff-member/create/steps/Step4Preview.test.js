import { describe, expect, it } from "vitest";

/**
 * Step4Preview parseSalaryNumber logic extracted for direct testing.
 * The function is defined in Step4Preview.vue <script setup> block.
 * We replicate it here to test the pure logic.
 */
const parseSalaryNumber = (value) => {
    if (value === null || value === undefined) return null;

    const raw = String(value).trim();
    if (!raw) return null;

    // Pattern: decimal number like "1.500.000,50" or "1000.50"
    // If it matches digits.digits (like "1000.50"), treat as float then trunc
    if (/^\d+\.\d{1,2}$/.test(raw)) {
        const parsed = Number(raw);
        return Number.isFinite(parsed) ? Math.trunc(parsed) : null;
    }

    // Indonesian thousand separator: "1.000.000" or "1.000.000,50"
    if (/^\d{1,3}(\.\d{3})+(,\d+)?$/.test(raw)) {
        const parsed = Number(raw.replace(/\./g, "").replace(",", "."));
        return Number.isFinite(parsed) ? Math.trunc(parsed) : null;
    }

    // Raw digits: "1000000" or mixed chars with digits
    const digits = raw.replace(/[^0-9]/g, "");
    if (!digits) return null;

    const parsed = parseInt(digits, 10);
    return Number.isFinite(parsed) ? parsed : null;
};

describe("Step4Preview - parseSalaryNumber", () => {
    // --- Decimal format (e.g. "1.000.000,50") ---
    it("handles Indonesian decimal format with comma (1.000.000,50)", () => {
        expect(parseSalaryNumber("1.000.000,50")).toBe(1000000);
    });

    it("handles decimal with short fractional part without dots (1000,50) falls to raw digits", () => {
        // "1000,50" doesn't match Indonesian thousand separator (no dots) or float (no dot),
        // so it falls to raw digits path: strips non-digits -> "100050" -> 100050
        expect(parseSalaryNumber("1000,50")).toBe(100050);
    });

    // --- Indonesian thousand separator (e.g. "1.000.000") ---
    it("handles Indonesian thousand separator (1.000.000)", () => {
        expect(parseSalaryNumber("1.000.000")).toBe(1000000);
    });

    it("handles 5-digit formatted (10.000)", () => {
        expect(parseSalaryNumber("10.000")).toBe(10000);
    });

    it("handles 6-digit formatted (100.000)", () => {
        expect(parseSalaryNumber("100.000")).toBe(100000);
    });

    // --- Float-like decimals (e.g. "1000.50") ---
    it("handles float-like format (1000.50)", () => {
        expect(parseSalaryNumber("1000.50")).toBe(1000);
    });

    it("handles float-like single decimal (1000.9)", () => {
        expect(parseSalaryNumber("1000.9")).toBe(1000);
    });

    // --- Raw digits ---
    it("handles raw digits (1000000)", () => {
        expect(parseSalaryNumber("1000000")).toBe(1000000);
    });

    it("handles raw digits with non-digit chars (Rp 5.000.000)", () => {
        expect(parseSalaryNumber("Rp 5.000.000")).toBe(5000000);
    });

    it("handles plain number as string (42)", () => {
        expect(parseSalaryNumber("42")).toBe(42);
    });

    // --- Empty string, null, undefined ---
    it("returns null for empty string", () => {
        expect(parseSalaryNumber("")).toBeNull();
    });

    it("returns null for whitespace-only string", () => {
        expect(parseSalaryNumber("   ")).toBeNull();
    });

    it("returns null for null", () => {
        expect(parseSalaryNumber(null)).toBeNull();
    });

    it("returns null for undefined", () => {
        expect(parseSalaryNumber(undefined)).toBeNull();
    });

    it("returns null for non-numeric string", () => {
        expect(parseSalaryNumber("abc")).toBeNull();
    });

    // --- Edge cases ---
    it("truncates decimal part for float format (1500.99)", () => {
        expect(parseSalaryNumber("1500.99")).toBe(1500);
    });

    it("handles single digit", () => {
        expect(parseSalaryNumber("5")).toBe(5);
    });

    it("handles zero", () => {
        expect(parseSalaryNumber("0")).toBe(0);
    });

    it("handles zero with formatting (0,00)", () => {
        expect(parseSalaryNumber("0,00")).toBe(0);
    });
});
