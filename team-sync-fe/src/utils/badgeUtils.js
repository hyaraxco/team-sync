/**
 * Unified badge/style utility functions.
 * Merged from badgeUtils.js + styleHelpers.js
 */

// ─── Skill Level ───
export const getSkillLevelBadgeClass = (skillLevel) => {
  const classes = {
    expert: "bg-purple-100 text-purple-700",
    intermediate: "bg-blue-100 text-blue-700",
    beginner: "bg-green-100 text-green-700",
  };
  return classes[skillLevel?.toLowerCase()] || "bg-gray-100 text-gray-700";
};

// Alias for backward compatibility
export const getLevelColor = getSkillLevelBadgeClass;

// ─── Team Status ───
export const getStatusColor = (status) => {
  const colors = {
    active: "bg-green-100 text-green-700",
    forming: "bg-blue-100 text-blue-700",
    planning: "bg-purple-100 text-purple-700",
    dormant: "bg-gray-100 text-gray-700",
  };
  return colors[status?.toLowerCase()] || "bg-gray-100 text-gray-700";
};

// Generic status badge (for dashboard latest components)
export const getStatusBadgeClass = (status) => {
  const classes = {
    active: "bg-green-100 text-green-700",
    inactive: "bg-gray-100 text-gray-700",
    growing: "bg-blue-100 text-blue-700",
    forming: "bg-blue-100 text-blue-700",
    planning: "bg-purple-100 text-purple-700",
    dormant: "bg-gray-100 text-gray-700",
  };
  return classes[status?.toLowerCase()] || "bg-purple-100 text-purple-700";
};

// ─── Priority ───
export const getPriorityColor = (priority) => {
  const colors = {
    low: "bg-green-100 text-green-700",
    medium: "bg-yellow-100 text-yellow-700",
    high: "bg-orange-100 text-orange-700",
    urgent: "bg-red-100 text-red-700",
  };
  return colors[priority?.toLowerCase()] || "bg-gray-100 text-gray-700";
};

// ─── Project Status ───
export const getProjectStatusColor = (status) => {
  const statusConfig = {
    draft: "bg-gray-100 text-gray-700",
    planning: "bg-purple-100 text-purple-700",
    active: "bg-[#EBF8FF] text-[#1E40AF]",
    on_hold: "bg-[#FEF3C7] text-[#92400E]",
    completed: "bg-[#F0FDF4] text-[#166534]",
    cancelled: "bg-red-100 text-red-700",
    overdue: "bg-[#FEE2E2] text-[#991B1B]",
  };
  return statusConfig[status?.toLowerCase()] || "bg-gray-100 text-gray-500";
};

// ─── Progress Bar ───
export const getProgressColor = (progress) => {
  if (progress >= 80) return "bg-green-500";
  if (progress >= 60) return "bg-blue-500";
  if (progress >= 40) return "bg-yellow-500";
  return "bg-red-500";
};

// ─── Leave Type ───
export const getLeaveTypeBadgeClass = (type) => {
  const map = {
    annual: "bg-blue-100 text-blue-700",
    sick: "bg-red-100 text-red-700",
    personal: "bg-purple-100 text-purple-700",
    emergency: "bg-orange-100 text-orange-700",
    maternity: "bg-pink-100 text-pink-700",
  };
  return map[type?.toLowerCase()] || "bg-gray-100 text-gray-700";
};

// ─── Leave Request Status ───
export const getLeaveRequestStatusBadgeClass = (status) => {
  const map = {
    pending: "bg-yellow-100 text-yellow-700",
    approved: "bg-green-100 text-green-700",
    rejected: "bg-red-100 text-red-700",
  };
  return map[status?.toLowerCase()] || "bg-gray-100 text-gray-700";
};

// ─── Task Status ───
export const getTaskStatusBadgeClass = (status) => {
  const map = {
    pending: "bg-gray-100 text-gray-700",
    todo: "bg-gray-100 text-gray-700",
    in_progress: "bg-blue-100 text-blue-700",
    review: "bg-amber-100 text-amber-700",
    done: "bg-green-100 text-green-700",
    rejected: "bg-red-100 text-red-700",
    cancelled: "bg-slate-100 text-slate-700",
  };
  return map[status?.toLowerCase()] || "bg-gray-100 text-gray-700";
};

// ─── Payroll Status ───
export const getPayrollStatusColor = (status) => {
  const colors = {
    draft: "bg-gray-100 text-gray-800",
    pending: "bg-yellow-100 text-yellow-800",
    approved: "bg-blue-100 text-blue-800",
    finalized: "bg-green-100 text-green-800",
    rejected: "bg-red-100 text-red-800",
  };
  return colors[status?.toLowerCase()] || "bg-gray-100 text-gray-700";
};
