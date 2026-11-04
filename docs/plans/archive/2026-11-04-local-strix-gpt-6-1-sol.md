# Local Strix with GPT 6.1 SOL

Status: COMPLETED for the approved configuration-only scope. Sandbox execution remains deferred and tracked by Beads `team-sync-5y4` (P2, blocked).

## Goal

Install the official Strix CLI and configure Team Sync to use the existing authenticated OpenAI-compatible proxy at `http://127.0.0.1:20128/v1`, model `cx/gpt-6.1-sol`. Keep credentials outside the repository. Do not change OMP settings or configure GitHub CI.

## Plan

1. Inspect existing launcher, proxy model discovery, authentication, and container prerequisites.
2. Install `strix-agent` through the existing uv tool manager. Store project-specific Strix configuration in the user's `.strix` directory; update the ignored local launcher to use the actual executable and configuration.
3. Exercise the installed CLI and its model connection check without scanning project code. Verify failure reporting for the unavailable container runtime.
4. Record commands, observed results and the deferred runtime prerequisite in this plan and a Beads ticket; update existing documentation after smoke verification.

## Confirmed prerequisites

- Existing proxy advertises `cx/gpt-6.1-sol`.
- An authenticated harmless chat-completions request returned `STRIX_SETUP_OK` from `gpt-6.1-sol`.
- Strix 1.7.0 installed with `uv tool install strix-agent`.
- Docker/Podman binaries are absent and WSL reports not installed. User selected configuration only, not runtime installation.

## Safety boundary

No security scan, target execution, live-target probe or full audit is authorized by this setup task. A future scan needs a sandbox policy and an isolated disposable target; current Strix documentation warns that local-directory targets are writable mounts. Model connectivity is not evidence of completed scanning, findings or production security.

## Verification results

- `strix --help` and `strix --version`: exited 0; version `1.7.0`.
- Installed Strix `preflight_model_connection`, using `%USERPROFILE%\.strix\team-sync.json`: exited 0 with `openai/cx/gpt-6.1-sol`, `http://127.0.0.1:20128/v1`, and `chat_completions`. This was a connection check, not a scan.
- Private profile stores the existing proxy credential outside Git, disables telemetry, and has a non-inherited ACL granting the current Windows user access. OMP configuration was unchanged.
- `run-strix.bat backend quick`: exited 1 and printed `Docker is unavailable. No scan was started.` The launcher discovers the uv executable and uses the private profile.
- README and changelog record usage, writable-target risk, deferred Docker installation, and limitations of budget accounting for a custom proxy alias.
- Beads `team-sync-5y4` was created and read back with matching description, acceptance criteria, P2 priority and blocked status. No full scan, findings, SARIF verification or CI activation is claimed.

## Deferred work

Only after a new user decision: install/start a compatible container runtime, confirm an approved sandbox policy and isolated disposable target, then collect real model/tool and completed-scan evidence. The existing PHP/development security gate `team-sync-l5p` and release-readiness ticket `team-sync-lzo` remain separate. Do not rely on `--max-budget 15` until custom-model pricing is verified.
