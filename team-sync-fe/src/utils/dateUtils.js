export const getTimeAgo = (dateString) => {
  const date = new Date(dateString);
  const now = new Date();
  const diffInMs = now.getTime() - date.getTime();
  const diffInHours = Math.floor(diffInMs / (1000 * 60 * 60));
  const diffInDays = Math.floor(diffInHours / 24);

  if (diffInHours < 1) return "Just now";
  if (diffInHours < 24) return `${diffInHours} hours ago`;
  if (diffInDays === 1) return "1 day ago";
  return `${diffInDays} days ago`;
};

export const formatDate = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

export const calculateDuration = (startDate, endDate) => {
  if (!startDate || !endDate) return "N/A";

  const start = new Date(startDate);
  const end = new Date(endDate);

  const diffInMs = end.getTime() - start.getTime();
  const diffInDays = Math.floor(diffInMs / (1000 * 60 * 60 * 24));

  if (diffInDays < 0) return "Invalid dates";
  if (diffInDays === 0) return "1 day";
  if (diffInDays === 1) return "1 day";
  if (diffInDays < 30) return `${diffInDays} days`;

  const months = Math.floor(diffInDays / 30);
  const days = diffInDays % 30;

  if (months === 1 && days === 0) return "1 month";
  if (months === 1) return `1 month ${days} days`;
  if (days === 0) return `${months} months`;
  return `${months} months ${days} days`;
};

export const formatDateShort = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
  });
};

export const formatDateLong = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    weekday: "long",
    month: "long",
    day: "numeric",
    year: "numeric",
  });
};

export const formatRequestDate = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
};

export const formatRequestDateLong = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    weekday: "short",
    month: "long",
    day: "numeric",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

export const getDayName = (dateString) => {
  const date = new Date(dateString);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  date.setHours(0, 0, 0, 0);

  if (date.getTime() === today.getTime()) {
    return "Today";
  }

  return date.toLocaleDateString("en-US", { weekday: "long" });
};

export const formatTime = (dateString) => {
  if (!dateString) return "--:--";
  const date = new Date(dateString);
  return date.toLocaleTimeString("en-US", {
    hour12: false,
    hour: "2-digit",
    minute: "2-digit",
  });
};

export const calculateWorkingHours = (checkIn, checkOut) => {
  if (!checkIn || !checkOut) return null;
  const start = new Date(checkIn);
  const end = new Date(checkOut);
  const diffMs = end - start;
  const diffMins = Math.floor(diffMs / (1000 * 60));
  const hours = Math.floor(diffMins / 60);
  const minutes = diffMins % 60;
  return `${hours}h ${minutes}m`;
};

export const calculateWorkingDays = (startDate, endDate) => {
  if (!startDate || !endDate || new Date(startDate) > new Date(endDate)) {
    return 0;
  }

  const start = new Date(startDate);
  const end = new Date(endDate);
  let workingDays = 0;

  for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
    if (d.getDay() !== 0 && d.getDay() !== 6) {
      workingDays++;
    }
  }

  return workingDays;
};
