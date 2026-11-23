// @vitest-environment node
import { beforeEach, expect, it, vi } from "vitest";

const { execSync } = vi.hoisted(() => ({ execSync: vi.fn() }));
vi.mock("node:child_process", () => ({ execSync }));
import { drainQueue } from "../../../e2e/helpers/backend";

beforeEach(() => execSync.mockReset());

it("drains through the running Compose container", () => {
    execSync.mockReturnValueOnce("container-id\n").mockReturnValue("");
    drainQueue(2);
    const workers = execSync.mock.calls.slice(1).map(([command]) => command);
    expect(workers).toHaveLength(2);
    for (const command of workers) {
        expect(command).toContain("exec -T web php artisan queue:work");
    }
});

it.each(["", new Error("Compose unavailable")])("uses the host when no container is available: %s", (probe) => {
    execSync.mockImplementationOnce(() => {
        if (probe instanceof Error) throw probe;
        return probe;
    }).mockReturnValue("");
    drainQueue(1);
    const command = execSync.mock.calls[1][0];
    expect(command).toContain('&& php artisan queue:work');
    expect(command).not.toContain("exec -T web");
});

it.each(["container-id\n", ""])("propagates worker failure without executing another worker: %s", (probe) => {
    const failure = new Error("Worker failed");
    execSync.mockReturnValueOnce(probe).mockImplementationOnce(() => { throw failure; });
    expect(() => drainQueue(2)).toThrow(failure);
    expect(execSync).toHaveBeenCalledTimes(2);
});
