import { describe, it, expect } from "vitest";
import {
    getSkillLevelBadgeClass,
    getLevelColor,
    getStatusColor,
    getStatusBadgeClass,
    getPriorityColor,
    getProjectStatusColor,
    getProgressColor,
    getLeaveTypeBadgeClass,
    getLeaveRequestStatusBadgeClass,
    getTaskStatusBadgeClass,
    getPayrollStatusColor,
    TASK_STATUS_ORDER,
    TASK_STATUS_LABELS,
} from "@/utils/badgeUtils";

describe("getSkillLevelBadgeClass", () => {
    it("returns expert class", () => {
        expect(getSkillLevelBadgeClass("expert")).toBe("bg-purple-100 text-purple-700");
    });

    it("returns intermediate class", () => {
        expect(getSkillLevelBadgeClass("intermediate")).toBe("bg-blue-100 text-blue-700");
    });

    it("returns beginner class", () => {
        expect(getSkillLevelBadgeClass("beginner")).toBe("bg-green-100 text-green-700");
    });

    it("is case-insensitive", () => {
        expect(getSkillLevelBadgeClass("Expert")).toBe("bg-purple-100 text-purple-700");
        expect(getSkillLevelBadgeClass("BEGINNER")).toBe("bg-green-100 text-green-700");
    });

    it("returns default class for unknown skill level", () => {
        expect(getSkillLevelBadgeClass("unknown")).toBe("bg-gray-100 text-gray-700");
    });

    it("returns default class for null/undefined", () => {
        expect(getSkillLevelBadgeClass(null)).toBe("bg-gray-100 text-gray-700");
        expect(getSkillLevelBadgeClass(undefined)).toBe("bg-gray-100 text-gray-700");
    });
});

describe("getLevelColor (alias)", () => {
    it("is the same function as getSkillLevelBadgeClass", () => {
        expect(getLevelColor).toBe(getSkillLevelBadgeClass);
    });
});

describe("getStatusColor", () => {
    it("returns correct class for known statuses", () => {
        expect(getStatusColor("active")).toBe("bg-green-100 text-green-700");
        expect(getStatusColor("forming")).toBe("bg-blue-100 text-blue-700");
        expect(getStatusColor("planning")).toBe("bg-purple-100 text-purple-700");
        expect(getStatusColor("dormant")).toBe("bg-gray-100 text-gray-700");
    });

    it("is case-insensitive", () => {
        expect(getStatusColor("Active")).toBe("bg-green-100 text-green-700");
    });

    it("returns default for unknown status", () => {
        expect(getStatusColor("unknown")).toBe("bg-gray-100 text-gray-700");
    });

    it("returns default for null/undefined", () => {
        expect(getStatusColor(null)).toBe("bg-gray-100 text-gray-700");
    });
});

describe("getStatusBadgeClass", () => {
    it("returns correct class for known statuses", () => {
        expect(getStatusBadgeClass("active")).toBe("bg-green-100 text-green-700");
        expect(getStatusBadgeClass("inactive")).toBe("bg-gray-100 text-gray-700");
        expect(getStatusBadgeClass("growing")).toBe("bg-blue-100 text-blue-700");
        expect(getStatusBadgeClass("forming")).toBe("bg-blue-100 text-blue-700");
        expect(getStatusBadgeClass("planning")).toBe("bg-purple-100 text-purple-700");
        expect(getStatusBadgeClass("dormant")).toBe("bg-gray-100 text-gray-700");
    });

    it("returns purple default for unknown status", () => {
        expect(getStatusBadgeClass("unknown")).toBe("bg-purple-100 text-purple-700");
    });

    it("is case-insensitive", () => {
        expect(getStatusBadgeClass("ACTIVE")).toBe("bg-green-100 text-green-700");
    });
});

describe("getPriorityColor", () => {
    it("returns correct class for known priorities", () => {
        expect(getPriorityColor("low")).toBe("bg-green-100 text-green-700");
        expect(getPriorityColor("medium")).toBe("bg-yellow-100 text-yellow-700");
        expect(getPriorityColor("high")).toBe("bg-orange-100 text-orange-700");
        expect(getPriorityColor("urgent")).toBe("bg-red-100 text-red-700");
    });

    it("returns default for unknown priority", () => {
        expect(getPriorityColor("critical")).toBe("bg-gray-100 text-gray-700");
    });

    it("is case-insensitive", () => {
        expect(getPriorityColor("HIGH")).toBe("bg-orange-100 text-orange-700");
    });
});

describe("getProjectStatusColor", () => {
    it("returns correct class for known statuses", () => {
        expect(getProjectStatusColor("draft")).toBe("bg-gray-100 text-gray-700");
        expect(getProjectStatusColor("planning")).toBe("bg-purple-100 text-purple-700");
        expect(getProjectStatusColor("active")).toBe("bg-[#EBF8FF] text-[#1E40AF]");
        expect(getProjectStatusColor("on_hold")).toBe("bg-[#FEF3C7] text-[#92400E]");
        expect(getProjectStatusColor("completed")).toBe("bg-[#F0FDF4] text-[#166534]");
        expect(getProjectStatusColor("cancelled")).toBe("bg-red-100 text-red-700");
        expect(getProjectStatusColor("overdue")).toBe("bg-[#FEE2E2] text-[#991B1B]");
    });

    it("returns default for unknown status", () => {
        expect(getProjectStatusColor("unknown")).toBe("bg-gray-100 text-gray-500");
    });

    it("is case-insensitive", () => {
        expect(getProjectStatusColor("ACTIVE")).toBe("bg-[#EBF8FF] text-[#1E40AF]");
    });
});

describe("getProgressColor", () => {
    it("returns green for >= 80", () => {
        expect(getProgressColor(80)).toBe("bg-green-500");
        expect(getProgressColor(100)).toBe("bg-green-500");
    });

    it("returns blue for >= 60 and < 80", () => {
        expect(getProgressColor(60)).toBe("bg-blue-500");
        expect(getProgressColor(79)).toBe("bg-blue-500");
    });

    it("returns yellow for >= 40 and < 60", () => {
        expect(getProgressColor(40)).toBe("bg-yellow-500");
        expect(getProgressColor(59)).toBe("bg-yellow-500");
    });

    it("returns red for < 40", () => {
        expect(getProgressColor(0)).toBe("bg-red-500");
        expect(getProgressColor(39)).toBe("bg-red-500");
    });
});

describe("getLeaveTypeBadgeClass", () => {
    it("returns correct class for known types", () => {
        expect(getLeaveTypeBadgeClass("annual")).toBe("bg-blue-100 text-blue-700");
        expect(getLeaveTypeBadgeClass("sick")).toBe("bg-red-100 text-red-700");
        expect(getLeaveTypeBadgeClass("personal")).toBe("bg-purple-100 text-purple-700");
        expect(getLeaveTypeBadgeClass("emergency")).toBe("bg-orange-100 text-orange-700");
        expect(getLeaveTypeBadgeClass("maternity")).toBe("bg-pink-100 text-pink-700");
    });

    it("returns default for unknown type", () => {
        expect(getLeaveTypeBadgeClass("unknown")).toBe("bg-gray-100 text-gray-700");
    });

    it("is case-insensitive", () => {
        expect(getLeaveTypeBadgeClass("ANNUAL")).toBe("bg-blue-100 text-blue-700");
    });
});

describe("getLeaveRequestStatusBadgeClass", () => {
    it("returns correct class for known statuses", () => {
        expect(getLeaveRequestStatusBadgeClass("pending")).toBe("bg-yellow-100 text-yellow-700");
        expect(getLeaveRequestStatusBadgeClass("approved")).toBe("bg-green-100 text-green-700");
        expect(getLeaveRequestStatusBadgeClass("rejected")).toBe("bg-red-100 text-red-700");
    });

    it("returns default for unknown status", () => {
        expect(getLeaveRequestStatusBadgeClass("cancelled")).toBe("bg-gray-100 text-gray-700");
    });
});

describe("getTaskStatusBadgeClass", () => {
    it("returns correct class for known statuses", () => {
        expect(getTaskStatusBadgeClass("todo")).toBe("bg-gray-100 text-gray-700");
        expect(getTaskStatusBadgeClass("pending")).toBe("bg-gray-100 text-gray-700");
        expect(getTaskStatusBadgeClass("in_progress")).toBe("bg-blue-100 text-blue-700");
        expect(getTaskStatusBadgeClass("review")).toBe("bg-amber-100 text-amber-700");
        expect(getTaskStatusBadgeClass("done")).toBe("bg-green-100 text-green-700");
        expect(getTaskStatusBadgeClass("rejected")).toBe("bg-red-100 text-red-700");
        expect(getTaskStatusBadgeClass("cancelled")).toBe("bg-slate-100 text-slate-700");
    });

    it("returns default for unknown status", () => {
        expect(getTaskStatusBadgeClass("unknown")).toBe("bg-gray-100 text-gray-700");
    });
});

describe("getPayrollStatusColor", () => {
    it("returns correct class for known statuses", () => {
        expect(getPayrollStatusColor("draft")).toBe("bg-gray-100 text-gray-800");
        expect(getPayrollStatusColor("pending")).toBe("bg-yellow-100 text-yellow-800");
        expect(getPayrollStatusColor("approved")).toBe("bg-blue-100 text-blue-800");
        expect(getPayrollStatusColor("finalized")).toBe("bg-green-100 text-green-800");
        expect(getPayrollStatusColor("rejected")).toBe("bg-red-100 text-red-800");
    });

    it("returns default for unknown status", () => {
        expect(getPayrollStatusColor("unknown")).toBe("bg-gray-100 text-gray-700");
    });
});

describe("TASK_STATUS_ORDER", () => {
    it("contains correct statuses in order", () => {
        expect(TASK_STATUS_ORDER).toEqual(["todo", "in_progress", "review", "done", "rejected", "cancelled"]);
    });
});

describe("TASK_STATUS_LABELS", () => {
    it("maps statuses to human-readable labels", () => {
        expect(TASK_STATUS_LABELS.todo).toBe("To Do");
        expect(TASK_STATUS_LABELS.in_progress).toBe("In Progress");
        expect(TASK_STATUS_LABELS.review).toBe("Review");
        expect(TASK_STATUS_LABELS.done).toBe("Done");
        expect(TASK_STATUS_LABELS.rejected).toBe("Rejected");
        expect(TASK_STATUS_LABELS.cancelled).toBe("Cancelled");
    });
});
