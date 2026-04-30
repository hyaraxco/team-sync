import { DateTime } from 'luxon';

export const DEFAULT_AVATAR = "/images/avatar-default.svg"

export function formatToClientTimezone(date, format = 'dd MMM yyyy HH:mm') {
  const originalDate = DateTime.fromISO(date, { zone: 'utc' });

  const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

  return originalDate.setZone(timezone).setLocale('id').toFormat(format);
}