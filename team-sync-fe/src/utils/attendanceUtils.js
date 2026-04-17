import { startCase } from 'lodash';

/**
 * Get attendance/leave request status badge configuration
 */
export const getStatusConfig = (status) => {
  const statusConfig = {
    present: { class: "bg-green-100 text-green-700", text: "Present" },
    absent: { class: "bg-red-100 text-red-700", text: "Absent" },
    sick_leave: { class: "bg-yellow-100 text-yellow-700", text: "Sick Leave" },
    weekend: { class: "bg-gray-100 text-gray-700", text: "Weekend" },
    pending: { class: "bg-yellow-100 text-yellow-700", text: "Pending" },
    approved: { class: "bg-green-100 text-green-700", text: "Approved" },
    rejected: { class: "bg-red-100 text-red-700", text: "Rejected" },
  };
  return statusConfig[status] || statusConfig["present"];
};

/**
 * Get attendance status badge class only
 */
export const getAttendanceStatusBadge = (status) => {
  const config = getStatusConfig(status);
  return config.class;
};

/**
 * Get attendance status text only
 */
export const getAttendanceStatusText = (status) => {
  const config = getStatusConfig(status);
  return config.text;
};

/**
 * Format leave type from snake_case to Title Case
 * Example: "sick_leave" -> "Sick Leave"
 */
export const formatLeaveType = (leaveType) => {
  if (!leaveType) return '';
  return startCase(leaveType.replace(/_/g, ' '));
};

/**
 * Format status from snake_case to Title Case
 * Example: "sick_leave" -> "Sick Leave"
 */
export const formatStatus = (status) => {
  if (!status) return '';
  return startCase(status.replace(/_/g, ' '));
};
