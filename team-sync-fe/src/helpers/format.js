import { DateTime } from "luxon";
import { useAuthStore } from "@/stores/auth";

export const DEFAULT_AVATAR = "/images/avatar-default.svg";

export function formatToClientTimezone(date, format = "dd MMM yyyy HH:mm") {
    const authStore = useAuthStore();
    const timezone = authStore.user?.company_timezone || "Asia/Jakarta";

    return DateTime.fromISO(date, { zone: "utc" }).setZone(timezone).setLocale("id").toFormat(format);
}
