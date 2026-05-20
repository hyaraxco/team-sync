# enowxai Review Rules

## Scope
Review only changed code in this pull request. Prefer concrete issues over speculative refactors.

## Must Catch
- architecture violations against repo rules
- controller/service/repository layering breaks
- missing validation / missing API resource / permission issues
- queue / transaction / concurrency bugs
- state machine guard bypasses
- response contract regressions

## Ignore
- formatter-only issues already handled by tooling
- speculative refactors outside PR scope
- style-only naming nits unless misleading
- suggestions that require rewriting unrelated code

## Severity Rubric
- `critical`: merge-blocking, correctness/security/data-loss/state-machine issue
- `important`: should be fixed before merge, likely behavior or maintainability risk
- `minor`: useful improvement, clarity, or low-risk cleanup

## Output Contract
Return strict JSON only.

Top-level fields:
- `schema_version` (integer, must be `1`)
- `overall` (`approved` | `commented` | `changes_requested`)
- `summary` (string)
- `findings` (array)
- `fallback_notes` (array of strings)

Each `findings[]` item must contain:
- `severity`
- `file`
- `line`
- `title`
- `body`

If no issues are found:
- return `findings: []`
- say so clearly in `summary`

## Inline Comment Policy
All mapped findings may be posted inline, including `minor` findings.
If a finding cannot be mapped to a changed file/line, place it in fallback summary notes instead of dropping it.
