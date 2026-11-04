# README audit rewrite

Status: COMPLETED for the approved documentation rewrite. Clean-machine installation, application runtime and rendered Markdown layout were not verified.

## Scope

- Rewrite the root README in plain Indonesian, preserving useful feature, architecture, domain, permission and operations details.
- Fix working-directory instructions, backend Bun installation, destructive E2E warnings, cache configuration and machine-local Strix presentation.
- Document the complete locked PHP constraint: PHP 8.4.x, at least 8.4.1. Pest alone permits 8.3, but locked Symfony packages require 8.4.1 and PhpSpreadsheet excludes 8.5.
- Remove promotional wording, decorative heading emoji and the stock closing line. Do not add compliance or production-readiness claims.
- Keep application code, dependencies, private model configuration and Beads state unchanged. Record the documentation change in the existing changelog.

## Verification

Use read-only checks of Markdown links, code-block working directories, package-script names, cache configuration, dependency constraints, safety warnings and copy. Do not execute migrations, seeders, E2E resets, dependency installation or a Strix scan. Do not present historical tests as current passes.

## Plan

1. Confirm source contracts for the reviewed instructions.
2. Rewrite README.md and add one changelog entry.
3. Run read-only documentation checks and archive this plan with observed results.

## Observed results

- Rewrote README.md and added one entry under Changed in CHANGELOG.md.
- Read-only documentation validation passed 36 checks, covering 15 code fences, 11 Bash blocks, 18 directory transitions, package-script references, and 12 local link references including anchors.
- Confirmed documented PHP 8.4.x bounds against all locked package constraints: minimum 8.4.1, maximum below 8.5.0.
- Confirmed CACHE_STORE matches the Laravel config reader, backend setup installs its own Bun dependencies, and the E2E reset warning precedes the command.
- Confirmed shared Strix guidance retains writable-target and budget-accounting warnings without depending on a private profile or ignored launcher.
- Copy checks passed for dash-aside punctuation, decorative heading emoji, promotional blanket wording and API-key patterns.
- No application code, dependencies, private model settings or Beads tickets changed. No installs, migrations, seeders, application tests or scans ran. Verification code existed only in the Eval runtime; no script was added to the repository.
