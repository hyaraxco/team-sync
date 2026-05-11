import { describe, it, expect } from "vitest";
import {
    getStatusConfig,
    getAttendanceStatusBadge,
    getAttendanceStatusText,
    formatLeaveType,
    formatStatus,
} from "@/utils/attendanceUtils";

describe("getStatusConfig", () => {
    it("returns config for known attendance statuses", () => {
        expect(getStatusConfig("present")).toEqual({
            class: "bg-green-100 text-green-700",
            text: "Present",
        });
        expect(getStatusConfig("absent")).toEqual({
            class: "bg-red-100 text-red-700",
            text: "Absent",
        });
        expect(getStatusConfig("sick_leave")).toEqual({
            class: "bg-yellow-100 text-yellow-700",
            text: "Sick Leave",
        });
        expect(getStatusConfig("weekend")).toEqual({
            class: "bg-gray-100 text-gray-700",
            text: "Weekend",
        });
    });

    it("returns config for leave request statuses", () => {
        expect(getStatusConfig("pending")).toEqual({
            class: "bg-yellow-100 text-yellow-700",
            text: "Pending",
        });
        expect(getStatusConfig("approved")).toEqual({
            class: "bg-green-100 text-green-700",
            text: "Approved",
        });
        expect(getStatusConfig("rejected")).toEqual({
            class: "bg-red-100 text-red-700",
            text: "Rejected",
        });
    });

    it("returns present config as default for unknown status", () => {
        expect(getStatusConfig("unknown")).toEqual({
            class: "bg-green-100 text-green-700",
            text: "Present",
        });
    });
});

describe("getAttendanceStatusBadge", () => {
    it("returns the class for known statuses", () => {
        expect(getAttendanceStatusBadge("present")).toBe("bg-green-100 text-green-700");
        expect(getAttendanceStatusBadge("absent")).toBe("bg-red-100 text-red-700");
    });

    it("returns default class for unknown status", () => {
        expect(getAttendanceStatusBadge("unknown")).toBe("bg-green-100 text-green-700");
    });
});

describe("getAttendanceStatusText", () => {
    it("returns the text for known statuses", () => {
        expect(getAttendanceStatusText("present")).toBe("Present");
        expect(getAttendanceStatusText("absent")).toBe("Absent");
        expect(getAttendanceStatusText("sick_leave")).toBe("Sick Leave");
    });

    it("returns default text for unknown status", () => {
        expect(getAttendanceStatusText("unknown")).toBe("Present");
    });
});

describe("formatLeaveType", () => {
    it("returns empty string for falsy values", () => {
        expect(formatLeaveType(null)).toBe("");
        expect(formatLeaveType(undefined)).toBe("");
        expect(formatLeaveType("")).toBe("");
    });

    it("formats snake_case to Title Case", () => {
        expect(formatLeaveType("sick_leave")).toBe("Sick Leave");
        expect(formatLeaveType("annual_leave")).toBe("Annual Leave");
        expect(formatLeaveType("personal_leave")).toBe("Personal Leave");
    });

    it("handles single word", () => {
        expect(formatLeaveType("sick")).toBe("Sick");
    });
});

describe("formatStatus", () => {
    it("returns empty string for falsy values", () => {
        expect(formatStatus(null)).toBe("");
        expect(formatStatus(undefined)).toBe("");
        expect(formatStatus("")).toBe("");
    });

    it("formats snake_case to Title Case", () => {
        expect(formatStatus("in_progress")).toBe("In Progress");
        expect(formatStatus("on_leave")).toBe("On Leave");
        expect(formatStatus("half_day")).toBe("Half Day");
    });

    it("handles single word", () => {
        expect(formatStatus("pending")).toBe("Pending");
    });
});
