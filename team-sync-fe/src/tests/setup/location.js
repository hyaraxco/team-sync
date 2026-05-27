import { beforeEach } from "vitest";

beforeEach(() => {
    window.history.replaceState(null, "", "/auth/login");
});
