import { describe, it, expect } from "vitest";
import {
    formatRupiah,
    formatIDR,
    formatRupiahCompact,
    capitalize,
    getJobStatusText,
} from "@/utils/formatUtils";

describe("formatRupiah", () => {
    it("returns 'IDR 0' when amount is falsy", () => {
        expect(formatRupiah(0)).toBe("IDR 0");
        expect(formatRupiah(null)).toBe("IDR 0");
        expect(formatRupiah(undefined)).toBe("IDR 0");
        expect(formatRupiah("")).toBe("IDR 0");
    });

    it("formats a positive amount with thousand separators", () => {
        expect(formatRupiah(1000)).toBe("IDR 1.000");
        expect(formatRupiah(1000000)).toBe("IDR 1.000.000");
        expect(formatRupiah(50000)).toBe("IDR 50.000");
    });

    it("formats small amounts without separators", () => {
        expect(formatRupiah(500)).toBe("IDR 500");
        expect(formatRupiah(1)).toBe("IDR 1");
    });
});

describe("formatIDR", () => {
    // Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR" })
    // uses U+00A0 (non-breaking space) between Rp and the number
    it("returns formatted 'Rp 0' when amount is falsy", () => {
        const result = formatIDR(0);
        expect(result).toMatch(/^Rp/);
        expect(result).toContain("0");
        expect(result).toBe(`Rp\u00A00`);
    });

    it("formats with 'Rp' prefix and thousand separators", () => {
        const result = formatIDR(1000000);
        expect(result).toMatch(/^Rp/);
        expect(result).toContain("1.000.000");
        expect(result).toBe(`Rp\u00A01.000.000`);
    });

    it("formats small amounts", () => {
        const result = formatIDR(500);
        expect(result).toMatch(/^Rp/);
        expect(result).toContain("500");
        expect(result).toBe(`Rp\u00A0500`);
    });

    it("formats null/undefined as zero", () => {
        expect(formatIDR(null)).toMatch(/0/);
        expect(formatIDR(undefined)).toMatch(/0/);
        expect(formatIDR(null)).toBe(`Rp\u00A00`);
        expect(formatIDR(undefined)).toBe(`Rp\u00A00`);
    });
});

describe("formatRupiahCompact", () => {
    it("returns 'IDR 0' when amount is falsy or zero", () => {
        expect(formatRupiahCompact(0)).toBe("IDR 0");
        expect(formatRupiahCompact(null)).toBe("IDR 0");
        expect(formatRupiahCompact(undefined)).toBe("IDR 0");
    });

    it("formats billions with B suffix", () => {
        expect(formatRupiahCompact(1500000000)).toBe("IDR 1.5B");
        expect(formatRupiahCompact(1000000000)).toBe("IDR 1.0B");
    });

    it("formats millions with M suffix", () => {
        expect(formatRupiahCompact(1500000)).toBe("IDR 1.5M");
        expect(formatRupiahCompact(2500000)).toBe("IDR 2.5M");
    });

    it("formats thousands with K suffix", () => {
        expect(formatRupiahCompact(1500)).toBe("IDR 1.5K");
        expect(formatRupiahCompact(50000)).toBe("IDR 50.0K");
    });

    it("formats amounts below 1000 using formatRupiah", () => {
        expect(formatRupiahCompact(500)).toBe("IDR 500");
        expect(formatRupiahCompact(999)).toBe("IDR 999");
    });

    it("handles negative amounts", () => {
        expect(formatRupiahCompact(-1500000)).toBe("IDR -1.5M");
        expect(formatRupiahCompact(-500000000)).toBe("IDR -500.0M");
    });

    it("handles string number input", () => {
        expect(formatRupiahCompact("1500000")).toBe("IDR 1.5M");
    });
});

describe("capitalize", () => {
    it("returns '-' for falsy values", () => {
        expect(capitalize(null)).toBe("-");
        expect(capitalize(undefined)).toBe("-");
        expect(capitalize("")).toBe("-");
        expect(capitalize(0)).toBe("-");
    });

    it("capitalizes a normal string", () => {
        expect(capitalize("hello")).toBe("Hello");
        expect(capitalize("world")).toBe("World");
    });

    it("replaces underscores with spaces", () => {
        // lodash capitalize only capitalizes the first letter
        expect(capitalize("hello_world")).toBe("Hello world");
        expect(capitalize("some_long_string")).toBe("Some long string");
    });

    it("replaces hyphens with spaces", () => {
        expect(capitalize("hello-world")).toBe("Hello world");
        expect(capitalize("some-long-string")).toBe("Some long string");
    });

    it("handles mixed underscores and hyphens", () => {
        expect(capitalize("hello_world-foo")).toBe("Hello world foo");
    });

    it("handles already capitalized strings", () => {
        expect(capitalize("Hello")).toBe("Hello");
    });
});

describe("getJobStatusText", () => {
    it("returns empty string for falsy values", () => {
        expect(getJobStatusText(null)).toBe("");
        expect(getJobStatusText(undefined)).toBe("");
        expect(getJobStatusText("")).toBe("");
    });

    it("maps known statuses to human-readable text", () => {
        expect(getJobStatusText("active")).toBe("Active");
        expect(getJobStatusText("on_leave")).toBe("On Leave");
        expect(getJobStatusText("resigned")).toBe("Resigned");
    });

    it("capitalizes unknown statuses", () => {
        expect(getJobStatusText("probation")).toBe("Probation");
        expect(getJobStatusText("contract")).toBe("Contract");
    });
});
