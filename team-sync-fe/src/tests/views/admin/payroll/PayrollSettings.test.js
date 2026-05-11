import { describe, expect, it } from "vitest";

/**
 * PayrollSettings historyComparisonRows logic extracted for direct testing.
 * We replicate the key functions from PayrollSettings.vue to test the pure logic.
 */

const trackedVersionFields = [
    "payday_day",
    "attendance_cutoff_day",
    "working_days_mode",
    "default_working_days",
    "absent_deduction_rate",
    "rounding_mode",
    "rounding_unit",
    "note_template",
];

const versionFieldLabels = {
    payday_day: "Payday day",
    attendance_cutoff_day: "Attendance cut-off",
    working_days_mode: "Working days mode",
    default_working_days: "Default working days",
    absent_deduction_rate: "Absent deduction rate",
    rounding_mode: "Rounding mode",
    rounding_unit: "Rounding unit",
    note_template: "Note template",
};

const formatWorkingDaysMode = (mode) => {
    if (mode === "auto_business_days") return "Auto business days";
    if (mode === "fixed") return "Fixed";
    return "Unknown";
};

const formatComparisonValue = (field, value) => {
    if (value === null || value === undefined || value === "") return "-";

    if (field === "working_days_mode") {
        return formatWorkingDaysMode(value);
    }

    if (field === "absent_deduction_rate") {
        return Number(value).toFixed(2);
    }

    if (field === "note_template") {
        const normalized = String(value).trim();
        return normalized.length > 80 ? `${normalized.slice(0, 80)}...` : normalized;
    }

    return String(value);
};

const normalizeComparisonValue = (field, value) => {
    if (field === "absent_deduction_rate") {
        return Number(value || 0).toFixed(2);
    }

    if (field === "note_template") {
        return String(value || "").trim();
    }

    return String(value ?? "");
};

const computeHistoryComparisonRows = (selectedVersion, previousVersion) => {
    if (!selectedVersion || !previousVersion) {
        return [];
    }

    return trackedVersionFields
        .map((field) => {
            const currentValue = selectedVersion[field];
            const previousValue = previousVersion[field];
            const hasChanged =
                normalizeComparisonValue(field, currentValue) !== normalizeComparisonValue(field, previousValue);

            if (!hasChanged) {
                return null;
            }

            return {
                field,
                label: versionFieldLabels[field] || field,
                previous: formatComparisonValue(field, previousValue),
                current: formatComparisonValue(field, currentValue),
            };
        })
        .filter(Boolean);
};

describe("PayrollSettings - historyComparisonRows", () => {
    it("returns empty array when selected version is null", () => {
        expect(computeHistoryComparisonRows(null, { id: 1 })).toEqual([]);
    });

    it("returns empty array when previous version is null", () => {
        expect(computeHistoryComparisonRows({ id: 1 }, null)).toEqual([]);
    });

    it("returns empty array when both are null", () => {
        expect(computeHistoryComparisonRows(null, null)).toEqual([]);
    });

    it("diffs two settings versions with changes", () => {
        const v2 = {
            payday_day: 25,
            attendance_cutoff_day: 25,
            working_days_mode: "auto_business_days",
            default_working_days: 22,
            absent_deduction_rate: 1,
            rounding_mode: "nearest",
            rounding_unit: 1000,
            note_template: "Working days: {working_days}",
        };
        const v1 = {
            payday_day: 24,
            attendance_cutoff_day: 24,
            working_days_mode: "fixed",
            default_working_days: 20,
            absent_deduction_rate: 1.5,
            rounding_mode: "floor",
            rounding_unit: 500,
            note_template: "Alpha {absent_days}",
        };

        const rows = computeHistoryComparisonRows(v2, v1);

        // All 8 fields differ
        expect(rows).toHaveLength(8);
        expect(rows.map((r) => r.field)).toEqual(trackedVersionFields);
    });

    it("normalizes absent_deduction_rate to 2 decimal places", () => {
        const v2 = { absent_deduction_rate: 1 };
        const v1 = { absent_deduction_rate: 1.0 };

        const rows = computeHistoryComparisonRows(v2, v1);

        // Both normalize to "1.00" — no change
        expect(rows).toHaveLength(0);
    });

    it("detects change in absent_deduction_rate with different values", () => {
        const v2 = { absent_deduction_rate: 1 };
        const v1 = { absent_deduction_rate: 1.5 };

        const rows = computeHistoryComparisonRows(v2, v1);

        expect(rows).toHaveLength(1);
        expect(rows[0].field).toBe("absent_deduction_rate");
        expect(rows[0].previous).toBe("1.50");
        expect(rows[0].current).toBe("1.00");
    });

    it("filters unchanged rows", () => {
        const v2 = {
            payday_day: 25,
            attendance_cutoff_day: 25,
            working_days_mode: "auto_business_days",
            default_working_days: 22,
            absent_deduction_rate: 1,
            rounding_mode: "nearest",
            rounding_unit: 1000,
            note_template: "Same template",
        };
        const v1 = {
            payday_day: 25,
            attendance_cutoff_day: 25,
            working_days_mode: "auto_business_days",
            default_working_days: 22,
            absent_deduction_rate: 1,
            rounding_mode: "nearest",
            rounding_unit: 1000,
            note_template: "Same template",
        };

        const rows = computeHistoryComparisonRows(v2, v1);
        expect(rows).toHaveLength(0);
    });

    it("formats working_days_mode to human-readable label", () => {
        const v2 = { working_days_mode: "fixed" };
        const v1 = { working_days_mode: "auto_business_days" };

        const rows = computeHistoryComparisonRows(v2, v1);

        expect(rows).toHaveLength(1);
        expect(rows[0].previous).toBe("Auto business days");
        expect(rows[0].current).toBe("Fixed");
    });

    it("truncates long note_template at 80 chars", () => {
        const longTemplate = "A".repeat(100);
        const v2 = { note_template: longTemplate };
        const v1 = { note_template: "Short" };

        const rows = computeHistoryComparisonRows(v2, v1);

        expect(rows).toHaveLength(1);
        expect(rows[0].current).toHaveLength(83); // 80 chars + "..."
        expect(rows[0].current).toMatch(/\.\.\.$/);
    });

    it("handles null values in comparison", () => {
        const v2 = { payday_day: 25, note_template: null };
        const v1 = { payday_day: 25, note_template: null };

        const rows = computeHistoryComparisonRows(v2, v1);
        expect(rows).toHaveLength(0);
    });

    it("handles undefined values in comparison", () => {
        const v2 = { payday_day: 25, rounding_unit: undefined };
        const v1 = { payday_day: 25, rounding_unit: 1000 };

        const rows = computeHistoryComparisonRows(v2, v1);

        expect(rows).toHaveLength(1);
        expect(rows[0].field).toBe("rounding_unit");
        expect(rows[0].previous).toBe("1000");
        expect(rows[0].current).toBe("-");
    });

    it("returns correct labels for each field", () => {
        const v2 = {
            payday_day: 26,
            attendance_cutoff_day: 26,
            working_days_mode: "fixed",
            default_working_days: 20,
            absent_deduction_rate: 2,
            rounding_mode: "ceil",
            rounding_unit: 500,
            note_template: "New template",
        };
        const v1 = {
            payday_day: 25,
            attendance_cutoff_day: 25,
            working_days_mode: "auto_business_days",
            default_working_days: 22,
            absent_deduction_rate: 1,
            rounding_mode: "nearest",
            rounding_unit: 1000,
            note_template: "Old template",
        };

        const rows = computeHistoryComparisonRows(v2, v1);

        expect(rows[0].label).toBe("Payday day");
        expect(rows[1].label).toBe("Attendance cut-off");
        expect(rows[2].label).toBe("Working days mode");
        expect(rows[3].label).toBe("Default working days");
        expect(rows[4].label).toBe("Absent deduction rate");
        expect(rows[5].label).toBe("Rounding mode");
        expect(rows[6].label).toBe("Rounding unit");
        expect(rows[7].label).toBe("Note template");
    });
});
