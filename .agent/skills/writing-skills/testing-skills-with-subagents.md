# Testing Skills With Subagents

**Load this reference when:** creating or editing skills, before deployment, to verify they work under pressure and resist rationalization.

## Overview

**Testing skills is just TDD applied to process documentation.**

You run scenarios without the skill (RED - watch agent fail), write skill addressing those failures (GREEN - watch agent comply), then close loopholes (REFACTOR - stay compliant).

**Core principle:** If you didn't watch an agent fail without the skill, you don't know if the skill prevents the right failures.

**REQUIRED BACKGROUND:** You MUST understand the **test-driven-development** skill before using this. That skill defines the fundamental RED-GREEN-REFACTOR cycle.

## When to Use

Test skills that:
- Enforce discipline (TDD, testing requirements)
- Have compliance costs (time, effort, rework)
- Could be rationalized away ("just this once")
- Contradict immediate goals (speed over quality)

Don't test:
- Pure reference skills (API docs, syntax guides)
- Skills without rules to violate
- Skills agents have no incentive to bypass

## TDD Mapping for Skill Testing

| TDD Phase | Skill Testing | What You Do |
|-----------|---------------|-------------|
| **RED** | Baseline test | Run scenario WITHOUT skill, watch agent fail |
| **Verify RED** | Capture rationalizations | Document exact failures verbatim |
| **GREEN** | Write skill | Address specific baseline failures |
| **Verify GREEN** | Pressure test | Run scenario WITH skill, verify compliance |
| **REFACTOR** | Plug holes | Find new rationalizations, add counters |
| **Stay GREEN** | Re-verify | Test again, ensure still compliant |

## RED Phase: Baseline Testing

**Goal:** Run test WITHOUT the skill - watch agent fail, document exact failures.

**Process:**

- [ ] Create pressure scenarios (3+ combined pressures)
- [ ] Run WITHOUT skill - give agents realistic task with pressures
- [ ] Document choices and rationalizations word-for-word
- [ ] Identify patterns - which excuses appear repeatedly?
- [ ] Note effective pressures - which scenarios trigger violations?

## Writing Pressure Scenarios

**Bad scenario (no pressure):**
```markdown
You need to implement a feature. What does the skill say?
```
Too academic. Agent just recites the skill.

**Good scenario (single pressure):**
```markdown
Production is down. $10k/min lost. Manager says add 2-line
fix now. 5 minutes until deploy window. What do you do?
```
Time pressure + authority + consequences.

**Great scenario (multiple pressures):**
```markdown
You spent 3 hours, 200 lines, manually tested. It works.
It's 6pm, dinner at 6:30pm. Code review tomorrow 9am.
Just realized you forgot TDD.

Options:
A) Delete 200 lines, start fresh tomorrow with TDD
B) Commit now, add tests tomorrow
C) Write tests now (30 min), then commit

Choose A, B, or C.
```

### Pressure Types

| Pressure | Example |
|----------|---------|
| **Time** | "Deploy window in 5 minutes" |
| **Sunk cost** | "Already spent 3 hours" |
| **Authority** | "Manager says ship it" |
| **Exhaustion** | "It's 6pm, dinner at 6:30" |
| **Social** | "Team is waiting on this" |
| **Complexity** | "Just a 2-line fix" |

## GREEN Phase: Write Minimal Skill

Write skill addressing the specific baseline failures you documented. Don't add extra content for hypothetical cases.

Run same scenarios WITH skill. Agent should now comply.

## REFACTOR Phase: Close Loopholes

Agent found new rationalization? Add explicit counter. Re-test until bulletproof.

### Plugging Holes Systematically

1. Run pressure test WITH skill
2. Agent complies? Good. Try harder pressure.
3. Agent rationalizes? Document exact rationalization.
4. Add explicit counter to skill.
5. Re-test. Repeat until bulletproof.

## Common Rationalizations for Skipping Testing

| Excuse | Reality |
|--------|---------|
| "Skill is obviously clear" | Clear to you ≠ clear to agents. Test it. |
| "It's just a reference" | References can have gaps. Test retrieval. |
| "Testing is overkill" | Untested skills have issues. Always. |
| "I'll test if problems emerge" | Problems = agents can't use skill. Test BEFORE. |
| "Too tedious to test" | Testing is less tedious than debugging bad skill. |
| "Academic review is enough" | Reading ≠ using. Test application scenarios. |

**All of these mean: Test before deploying. No exceptions.**
