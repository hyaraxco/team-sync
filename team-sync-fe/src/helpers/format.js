import { DateTime } from 'luxon';

export const DEFAULT_AVATAR = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect fill='%23e2e8f0' width='100' height='100' rx='50'/%3E%3Cpath d='M50 25a15 15 0 110 30 15 15 0 010-30zm0 37.5c16.57 0 30 6.72 30 15V85H20v-7.5c0-8.28 13.43-15 30-15z' fill='%2394a3b8'/%3E%3C/svg%3E"

export function formatToClientTimezone(date, format = 'dd MMM yyyy HH:mm') {
  const originalDate = DateTime.fromISO(date, { zone: 'utc' });

  const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

  return originalDate.setZone(timezone).setLocale('id').toFormat(format);
}