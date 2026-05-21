# enowxai Review UX Design

**Goal:** Upgrade the existing `ai-review.yml` workflow so enowxai becomes a practical AI reviewer that posts one durable PR summary comment plus inline PR comments for every mapped finding, using a repo-owned review rules file instead of a hardcoded prompt-only setup.

## Current State

Current workflow: `.github/workflows/ai-review.yml`

What it does today:
- triggers on `pull_request` events: `opened`, `synchronize`, `ready_for_review`
- fetches PR file patches through `gh api repos/$REPO/pulls/$PR_NUMBER/files`
- truncates diff to 12k chars
- builds a single free-form prompt inside `/tmp/enowx_review.py`
- calls `${ENOWX_API_URL}/chat/completions`
- writes plain-text review output to `/tmp/review_output.txt`
- posts the result as one PR conversation comment via `POST /repos/{repo}/issues/{pr}/comments`

What it does **not** do yet:
- no repo-owned rules file for reviewer policy
- no structured output contract
- no inline review comments on changed lines
- no dedupe/update behavior for existing enowxai comments
- no fallback structure for unmapped findings beyond plain text

This means enowxai already reviews the PR, but the result behaves like a generic bot comment rather than a reviewer-like code review surface.

## Product Outcome

The target reviewer experience is:

1. Every PR still gets **one summary comment** from enowxai.
2. Every finding that can be mapped to a changed file/line gets an **inline PR comment**.
3. Findings that cannot be mapped inline still appear in the summary comment under an explicit fallback section.
4. Reviewer policy lives in the repo, so changing review standards does not require editing workflow logic.
5. The workflow remains **non-blocking** during the trial period. CI/CD status checks stay the merge gate; enowxai remains advisory.

## Design Decision

Chosen direction: **summary comment + inline comments + repo-owned rules file + structured JSON output**.

This was chosen over summary-only because the explicit goal is to make enowxai behave more like a reviewer and less like a passive PR commenter.

## Architecture

### 1. Inputs to enowxai

The review request sent by the workflow should include:
- PR title
- PR body / description
- base branch name
- head branch name
- changed-file diff (truncated if needed)
- repo review rules file contents
- strict output contract instructions

#### Diff truncation strategy

Because provider context is finite, truncation behavior must be explicit.

Recommended v1 rules:
- prioritize changed **source files** first, test files second
- prefer keeping more files with smaller patch excerpts over sending one huge file only
- cap prompt diff payload to a fixed upper bound, e.g. **30k chars including separators**
- if truncation occurs, append a visible note such as `... (diff truncated)`
- if a later finding references a file/line not present in the retained diff window, treat it as **unmapped** and send it to summary fallback

This keeps the workflow predictable and avoids silent reviewer drift caused by arbitrary truncation.

### 2. Ruleset placement

Ruleset level #2 should live in the repo as a markdown file:

`/.github/review-rules/enowxai-review.md`

This file becomes the source of truth for:
- architecture rules to enforce
- issue classes to flag
- issue classes to ignore
- severity rubric
- inline comment policy
- output format expectations

This keeps reviewer behavior versioned with the codebase and editable through normal PR flow.

### 3. Output contract

The workflow should stop expecting free-form prose and instead require **strict structured JSON**.

Recommended JSON shape:

```json
{
  "schema_version": 1,
  "overall": "commented",
  "summary": "2 important findings, 3 minor findings",
  "findings": [
    {
      "severity": "important",
      "file": "team-sync-be/app/Jobs/GeneratePayrollJob.php",
      "line": 42,
      "title": "Queue retry logic can re-run non-idempotent path",
      "body": "Explain the issue and recommend a fix."
    }
  ],
  "fallback_notes": []
}
```

Contract rules:
- `schema_version`: integer contract version used by workflow parser
- `overall`: short overall state such as `approved`, `commented`, or `changes_requested`
- `summary`: one-line aggregate summary
- `findings`: normalized list of review findings
- `severity`: one of `critical`, `important`, `minor`
- `file`: repo-relative path
- `line`: changed line number target for inline comment
- `title`: short finding headline
- `body`: concise technical explanation plus recommended fix
- `fallback_notes`: array of strings for unmapped, meta, or parser-safe notes that should appear in summary only

Additional output constraints:
- `overall` must be one of: `approved`, `commented`, `changes_requested`
- `findings` should be capped for v1 at **15 inline-eligible findings**; overflow items go to summary fallback notes
- if no issues are found, `findings` must be an empty array and `summary` must explicitly say no issues were found

### 4. Publish surfaces

The workflow publishes to two places.

#### A. Summary PR comment

One durable summary comment in PR conversation with:
- overall verdict
- count by severity
- short summary bullets
- unmapped findings
- parser or posting failure notes if relevant

The comment should contain a stable marker such as:

```md
<!-- enowxai-review-summary -->
```

This marker is used so the workflow can update the existing summary instead of posting a new one every run.

#### B. Inline PR comments

For each finding whose `file` and `line` map to the changed PR diff, the workflow should create an inline PR comment.

Desired scope for inline comments:
- **all findings**, including `minor`

If line mapping fails:
- do not drop the finding
- move it into the summary comment's fallback section

### 5. Dedupe and refresh behavior

The workflow runs repeatedly on PR updates, so duplicate control is mandatory.

#### Summary comment dedupe

- search existing PR comments for `<!-- enowxai-review-summary -->`
- if found: update the comment body
- if not found: create it

#### Inline comment dedupe

Use a fingerprint marker embedded in each inline comment body.

Recommended identity inputs:
- `file`
- `line`
- `severity`

Recommended marker shape:

```md
<!-- enowxai-finding:{sha256(file|line|severity)} -->
```

`title` must **not** be part of the fingerprint because the model may rephrase titles across reruns.

Expected behavior:
- list existing inline PR comments from the PR
- filter to comments previously created by this workflow/bot identity
- parse fingerprint markers from existing comment bodies
- if a matching fingerprint already exists for the current review context, do not re-post it
- if a finding changes materially but keeps the same fingerprint target, posting policy may either update or leave the old comment depending on GitHub API implementation convenience in that phase
- if a finding disappears, stale inline comments are **not deleted in v1**; this is an accepted limitation for the first rollout

For first rollout, dedupe is required; aggressive cleanup is optional.

### 6. Failure behavior

The workflow must degrade gracefully.

#### If enowxai request fails

- still post or update summary comment with a short failure message
- include workflow run link
- do not hard-fail the PR review process unless infra policy later changes

#### If JSON parse fails

- summary comment should say parsing failed
- include truncated raw output or a short diagnostic note
- skip inline posting

#### If JSON is partial / truncated

- treat it as parse failure
- summary comment should explicitly say partial JSON was returned
- include the first debug-safe slice of raw output (for example first 500 chars)
- skip inline posting

#### If file/line mapping fails

- preserve the finding in the summary under `Unmapped Findings`
- skip inline posting only for that specific finding

#### If PR diff changed between runs

- line mapping may drift on `synchronize`
- old inline comments from earlier runs are allowed to remain in v1
- new findings that cannot be re-mapped are moved to summary fallback
- stale-comment cleanup is deferred to a later refinement phase

### 7. Merge policy during trial

During the trial period:
- enowxai review remains **advisory**
- branch rules / CI/CD remain the actual merge gate
- do not make enowxai workflow required for merge until output quality and spam behavior are proven acceptable

## Workflow Permissions and Runtime Safety

Recommended GitHub Actions permissions:

```yaml
permissions:
  pull-requests: write
  contents: read
```

Why:
- `contents: read` is needed to read repo files and workflow context
- `pull-requests: write` is needed to create/update summary comments and create inline PR comments

Recommended workflow concurrency:

```yaml
concurrency:
  group: enowxai-review-${{ github.event.pull_request.number }}
  cancel-in-progress: true
```

Why:
- prevents two near-simultaneous `synchronize` runs from racing and posting duplicate comments
- keeps only the newest review run alive for a PR

Rate-limit and volume guidance:
- inline posting all findings is acceptable in v1, but findings should be capped to avoid noisy PRs and unnecessary GitHub API churn
- if findings exceed the inline cap, overflow items go to summary only
- the workflow should remain non-blocking even when API posting partially fails

## Repo Rules File Design

Recommended initial file:

`/.github/review-rules/enowxai-review.md`

Suggested sections:

```md
# enowxai Review Rules

## Scope
Review only changed code in this PR.

## Must Catch
- architecture violations
- state machine guard bypass
- missing validation / missing resource / permission issues
- risky queue, transaction, or concurrency bugs

## Ignore
- pure formatter/style nits already handled by tooling
- subjective renaming unless misleading
- speculative refactors outside PR scope

## Severity Rubric
- Critical
- Important
- Minor

## Output Contract
Return strict JSON with overall, summary, findings, fallback_notes.

## Inline Comment Policy
Post inline comments for all mapped findings. If mapping fails, place the finding in fallback summary.
```

This should be enough for v1. A dedicated JSON schema file can be added later if the parser needs stricter validation.

## Implementation Phases

### Phase 1 — Structured review foundation

Deliverables:
- add repo rules file
- modify prompt builder to inject repo rules contents
- change enowxai request instructions to require JSON output
- parse JSON response safely
- keep posting one summary comment
- add stable summary comment marker and update-in-place behavior

Why first:
- reduces ambiguity in the model output
- creates stable contract before inline comment logic

### Phase 2 — Inline comment publishing

Deliverables:
- map findings to PR diff lines
- post inline comments for mapped findings
- add inline dedupe logic
- fallback unmapped findings to summary

Why second:
- isolates the harder GitHub review-surface work
- keeps rollback simpler if mapping behavior is noisy

## Constraints and Trade-offs

### Gains
- enowxai becomes visibly useful inside PR review flow
- repo controls review policy through versioned rules
- summary comment prevents review signal from being lost
- inline comments make findings actionable at code location

### Costs
- more workflow complexity
- requires reliable structured output from model/provider
- line mapping and dedupe logic can be noisy if not implemented carefully

### Chosen trade-off

The design intentionally prefers slightly more workflow complexity in exchange for a reviewer UX that approximates Copilot review while staying under repo control.

## Testing Strategy

Implementation should prove:
- JSON parse success path
- JSON parse failure fallback
- partial / truncated JSON fallback
- summary comment create path
- summary comment update path
- inline comment creation path
- unmapped finding fallback path
- duplicate inline finding not reposted path
- no-findings path (empty findings array still posts correct summary)
- `line: null` or missing line path falls back to summary

Manual acceptance should include:
- open PR with 1 clear issue → expect summary + inline comment
- update PR without changing issue → expect no duplicate spam
- update PR removing issue → summary updated, stale inline behavior follows documented v1 limitation
- open PR with no issues → expect clean summary comment and no inline comments

## Out of Scope

Not in this design:
- making enowxai a required merge gate
- turning enowxai into a native GitHub reviewer identity / GitHub App reviewer panel actor
- replacing existing CI/CD checks
- solving organization-wide reviewer policy beyond this repo
- multi-persona review orchestration inside the workflow itself

## Final Recommendation

Implement this as:
- one repo rules file
- structured JSON output contract
- one durable summary comment
- inline comments for all mapped findings
- non-blocking workflow during trial

This is the smallest design that materially improves enowxai review UX without over-engineering the first rollout.
