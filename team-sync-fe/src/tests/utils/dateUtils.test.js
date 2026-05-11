import { describe, it, expect, beforeEach, vi, afterEach } from "vitest";
import {
    getTimeAgo,
    formatDate,
    calculateDuration,
    formatDateShort,
    formatDateLong,
    formatRequestDate,
    formatRequestDateLong,
    getDayName,
    formatTime,
    calculateWorkingHours,
    calculateWorkingDays,
} from "@/utils/dateUtils";

describe("getTimeAgo", () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it("returns 'Just now' for less than 1 hour ago", () => {
        vi.setSystemTime(new Date("2026-05-10T12:00:00"));
        const date = new Date("2026-05-10T11:30:00");
        expect(getTimeAgo(date.toISOString())).toBe("Just now");
    });

    it("returns 'Just now' for the current time", () => {
        vi.setSystemTime(new Date("2026-05-10T12:00:00"));
        expect(getTimeAgo("2026-05-10T12:00:00")).toBe("Just now");
    });

    it("returns hours ago for less than 24 hours", () => {
        vi.setSystemTime(new Date("2026-05-10T12:00:00"));
        const date = new Date("2026-05-10T09:00:00");
        expect(getTimeAgo(date.toISOString())).toBe("3 hours ago");
    });

    it("returns '1 day ago' for exactly 24 hours", () => {
        vi.setSystemTime(new Date("2026-05-10T12:00:00"));
        const date = new Date("2026-05-09T12:00:00");
        expect(getTimeAgo(date.toISOString())).toBe("1 day ago");
    });

    it("returns days ago for more than 24 hours", () => {
        vi.setSystemTime(new Date("2026-05-10T12:00:00"));
        const date = new Date("2026-05-07T12:00:00");
        expect(getTimeAgo(date.toISOString())).toBe("3 days ago");
    });
});

describe("formatDate", () => {
    it("returns empty string for falsy values", () => {
        expect(formatDate("")).toBe("");
        expect(formatDate(null)).toBe("");
    });

    it("formats date as 'MMM DD, YYYY'", () => {
        expect(formatDate("2026-05-10")).toBe("May 10, 2026");
    });

    it("formats different months correctly", () => {
        expect(formatDate("2026-01-15")).toBe("Jan 15, 2026");
        expect(formatDate("2026-12-25")).toBe("Dec 25, 2026");
    });
});

describe("calculateDuration", () => {
    it("returns 'N/A' for missing dates", () => {
        expect(calculateDuration(null, "2026-05-10")).toBe("N/A");
        expect(calculateDuration("2026-05-01", null)).toBe("N/A");
        expect(calculateDuration(null, null)).toBe("N/A");
    });

    it("returns '1 day' for same day", () => {
        expect(calculateDuration("2026-05-01", "2026-05-01")).toBe("1 day");
    });

    it("returns '1 day' for 1 day difference", () => {
        expect(calculateDuration("2026-05-01", "2026-05-02")).toBe("1 day");
    });

    it("returns days for less than 30 days", () => {
        expect(calculateDuration("2026-05-01", "2026-05-10")).toBe("9 days");
    });

    it("returns '1 month' for exactly 30 days", () => {
        expect(calculateDuration("2026-05-01", "2026-05-31")).toBe("1 month");
    });

    it("returns months and days for more than 30 days", () => {
        expect(calculateDuration("2026-05-01", "2026-06-15")).toBe("1 month 15 days");
    });

    it("returns months for exact multiples of 30 days", () => {
        // May 1 to Jul 31 = 91 days -> 3 months, 1 day
        // Use dates that are exactly 60 days apart
        expect(calculateDuration("2026-05-01", "2026-06-30")).toBe("2 months");
    });

    it("returns months and days for non-multiples of 30", () => {
        // Jun 1 to Jul 16 = 45 days -> 1 month 15 days
        expect(calculateDuration("2026-06-01", "2026-07-16")).toBe("1 month 15 days");
    });

    it("returns 'Invalid dates' when start is after end", () => {
        expect(calculateDuration("2026-05-10", "2026-05-01")).toBe("Invalid dates");
    });
});

describe("formatDateShort", () => {
    it("formats date as 'MMM DD'", () => {
        expect(formatDateShort("2026-05-10")).toBe("May 10");
    });

    it("formats different months", () => {
        expect(formatDateShort("2026-01-01")).toBe("Jan 1");
        expect(formatDateShort("2026-12-31")).toBe("Dec 31");
    });
});

describe("formatDateLong", () => {
    it("formats as 'Weekday, Month DD, YYYY'", () => {
        expect(formatDateLong("2026-05-10")).toBe("Sunday, May 10, 2026");
    });

    it("formats a different day", () => {
        expect(formatDateLong("2026-05-01")).toBe("Friday, May 1, 2026");
    });
});

describe("formatRequestDate", () => {
    it("formats as 'MMM DD, YYYY'", () => {
        expect(formatRequestDate("2026-05-10")).toBe("May 10, 2026");
    });
});

describe("formatRequestDateLong", () => {
    it("formats with weekday, date, year, and time", () => {
        const result = formatRequestDateLong("2026-05-10T14:30:00");
        // Uses en-US locale which defaults to 12-hour format
        expect(result).toContain("May 10, 2026");
        expect(result).toContain("2:30");
        expect(result).toContain("PM");
    });

    it("formats morning time", () => {
        const result = formatRequestDateLong("2026-05-10T09:05:00");
        expect(result).toContain("9:05");
        expect(result).toContain("AM");
    });
});

describe("getDayName", () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it("returns 'Today' for today's date", () => {
        vi.setSystemTime(new Date("2026-05-10T12:00:00"));
        expect(getDayName("2026-05-10")).toBe("Today");
    });

    it("returns weekday name for non-today dates", () => {
        vi.setSystemTime(new Date("2026-05-10T12:00:00"));
        expect(getDayName("2026-05-11")).toBe("Monday");
        expect(getDayName("2026-05-09")).toBe("Saturday");
    });
});

describe("formatTime", () => {
    it("returns '--:--' for falsy values", () => {
        expect(formatTime(null)).toBe("--:--");
        expect(formatTime("")).toBe("--:--");
    });

    it("formats time as 'HH:MM' in 24h format", () => {
        expect(formatTime("2026-05-10T09:05:00")).toBe("09:05");
        expect(formatTime("2026-05-10T14:30:00")).toBe("14:30");
    });
});

describe("calculateWorkingHours", () => {
    it("returns null when checkIn or checkOut is missing", () => {
        expect(calculateWorkingHours(null, "2026-05-10T17:00:00")).toBeNull();
        expect(calculateWorkingHours("2026-05-10T09:00:00", null)).toBeNull();
        expect(calculateWorkingHours(null, null)).toBeNull();
    });

    it("returns 'Xh Ym' format", () => {
        expect(calculateWorkingHours("2026-05-10T09:00:00", "2026-05-10T17:00:00")).toBe("8h 0m");
    });

    it("handles partial hours", () => {
        expect(calculateWorkingHours("2026-05-10T09:00:00", "2026-05-10T17:30:00")).toBe("8h 30m");
    });

    it("handles less than an hour", () => {
        expect(calculateWorkingHours("2026-05-10T09:00:00", "2026-05-10T09:45:00")).toBe("0h 45m");
    });
});

describe("calculateWorkingDays", () => {
    it("returns 0 for missing dates", () => {
        expect(calculateWorkingDays(null, "2026-05-10")).toBe(0);
        expect(calculateWorkingDays("2026-05-01", null)).toBe(0);
    });

    it("returns 0 when start is after end", () => {
        expect(calculateWorkingDays("2026-05-10", "2026-05-01")).toBe(0);
    });

    it("counts only weekdays", () => {
        // Mon May 4 to Fri May 8 = 5 weekdays
        expect(calculateWorkingDays("2026-05-04", "2026-05-08")).toBe(5);
    });

    it("includes start and end if they are weekdays", () => {
        // Mon May 4 to Mon May 4 = 1 day
        expect(calculateWorkingDays("2026-05-04", "2026-05-04")).toBe(1);
    });

    it("excludes weekends", () => {
        // Sat May 9 to Sun May 10 = 0 working days
        expect(calculateWorkingDays("2026-05-09", "2026-05-10")).toBe(0);
    });

    it("handles week spanning weekends", () => {
        // Fri May 8 to Mon May 11 = 2 working days (Fri, Mon)
        expect(calculateWorkingDays("2026-05-08", "2026-05-11")).toBe(2);
    });
});
