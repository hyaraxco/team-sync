import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

const css = readFileSync(resolve(process.cwd(), "src/assets/css/input.css"), "utf8");

function token(block, name) {
    return block.match(new RegExp(`${name}:\\s*(#[\\da-fA-F]{6})`))?.[1];
}

function contrastRatio(foreground, background) {
    const luminance = (color) => {
        const channels = color.match(/[\da-fA-F]{2}/g).map((channel) => parseInt(channel, 16) / 255);
        const [red, green, blue] = channels.map((channel) =>
            channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4,
        );
        return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
    };

    const values = [luminance(foreground), luminance(background)].sort((a, b) => b - a);
    return (values[0] + 0.05) / (values[1] + 0.05);
}

function themeBlock(selector) {
    const start = css.indexOf(selector);
    const end = css.indexOf("\n}", start);
    return css.slice(start, end);
}

function surfaceTokens(block) {
    return [...block.matchAll(/--color-surface(?:-[\w-]+)?:\s*(#[\da-fA-F]{6})/g)].map((match) => match[1]);
}

describe("muted text contrast", () => {
    it("meets WCAG AA on every theme surface token", () => {
        const light = themeBlock(":root {");
        const dark = themeBlock(".dark {");

        for (const [theme, block] of [["light", light], ["dark", dark]]) {
            const foreground = token(block, "--color-text-muted");
            for (const background of surfaceTokens(block)) {
                expect(
                    contrastRatio(foreground, background),
                    `${theme} muted text ${foreground} on ${background}`,
                ).toBeGreaterThanOrEqual(4.5);
            }
        }
    });
});
