import { capitalize as lodashCapitalize } from "lodash";

export const formatCurrency = (amount) => {
  if (!amount) return "";
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
  }).format(amount);
};

export const formatRupiah = (amount) => {
  if (!amount) return "Rp 0";
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    minimumFractionDigits: 0,
  }).format(amount);
};

export const formatRupiahCompact = (amount) => {
  const n = Number(amount);
  if (!n) return "Rp 0";
  const abs = Math.abs(n);
  const sign = n < 0 ? "-" : "";
  if (abs >= 1000000000) {
    return `Rp ${sign}${(abs / 1000000000).toFixed(1)}B`;
  } else if (abs >= 1000000) {
    return `Rp ${sign}${(abs / 1000000).toFixed(1)}M`;
  } else if (abs >= 1000) {
    return `Rp ${sign}${(abs / 1000).toFixed(1)}K`;
  }
  return formatRupiah(n);
};

export const capitalize = (str) => {
  if (!str) return "-";
  const cleaned = String(str).replace(/[_-]/g, " ");
  return lodashCapitalize(cleaned);
};

export const getJobStatusText = (status) => {
  if (!status) return "";
  const map = {
    active: "Active",
    on_leave: "On Leave",
    resigned: "Resigned",
  };
  return map[status] || capitalize(status);
};

