/**
 * Parse salary number from various formats to integer
 * Handles Indonesian formatting (1.000.000,50), float format (1000.50), and raw digits
 * 
 * @param {any} value - The value to parse
 * @returns {number | null} - Parsed integer or null if invalid
 */
export const parseSalaryNumber = (value) => {
    if (value === null || value === undefined) return null;

    const raw = String(value).trim();
    if (!raw) return null;

    // Pattern: decimal number like "1000.50" (float format)
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
