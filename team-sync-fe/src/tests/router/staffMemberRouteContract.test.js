import { describe, expect, it } from "vitest";
import { appRoutes } from "@/router/index";

const allNames = [];

const walk = (routes) => {
    for (const route of routes) {
        if (route?.name) {
            allNames.push(route.name);
        }

        if (Array.isArray(route?.children)) {
            walk(route.children);
        }
    }
};

describe("staff member route contract", () => {
    it("contains admin.staffMembers route namespace", () => {
        walk(appRoutes);

        expect(allNames).toContain("admin.staffMembers");
    });
});
