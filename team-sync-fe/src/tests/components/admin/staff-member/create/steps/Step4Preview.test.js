import { describe, expect, it } from "vitest";
import { parseSalaryNumber } from "@/utils/salaryUtils";

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
