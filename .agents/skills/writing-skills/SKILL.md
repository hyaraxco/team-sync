---
name: writing-skills
description: Use when creating new skills, editing existing skills, or verifying skills work before deployment
---

# Writing Skills

## Overview

**Writing skills IS Test-Driven Development applied to process documentation.**

**Personal skills live in `~/.agents/skills/` or `~/.config/opencode/skills/`**

You write test cases (pressure scenarios with explicit task execution), watch them fail (baseline behavior), write the skill (documentation), watch tests pass (agents comply), and refactor (close loopholes).

**Core principle:** If you didn't watch an agent fail without the skill, you don't know if the skill teaches the right thing.

**REQUIRED BACKGROUND:** You MUST understand the **test-driven-development** skill before using this skill. That skill defines the fundamental RED-GREEN-REFACTOR cycle. This skill adapts TDD to documentation.

## What is a Skill?

A **skill** is a reference guide for proven techniques, patterns, or tools. Skills help future sessions find and apply effective approaches.

**Skills are:** Reusable techniques, patterns, tools, reference guides

**Skills are NOT:** Narratives about how you solved a problem once

## TDD Mapping for Skills

| TDD Concept             | Skill Creation                   |
| ----------------------- | ------------------------------------------------ |
| **Test case**           | Pressure scenario with explicit task execution   |
| **Production code**     | Skill document (SKILL.md)                        |
| **Test fails (RED)**    | Agent violates rule without skill (baseline)     |
| **Test passes (GREEN)** | Agent complies with skill present                |
| **Refactor**            | Close loopholes while maintaining compliance     |

## When to Create a Skill

**Create when:**

- Technique wasn't intuitively obvious to you
- You'd reference this again across projects
- Pattern applies broadly (not project-specific)
- Others would benefit

**Don't create for:**

- One-off solutions
- Standard practices well-documented elsewhere
- Project-specific conventions (put in project rules/AGENTS.md)
- Mechanical constraints (if enforceable with regex/validation, automate it)

## Skill Types

### Technique
Concrete method with steps to follow (condition-based-waiting, root-cause-tracing)

### Pattern
Way of thinking about problems (flatten-with-flags, test-invariants)

### Reference
API docs, syntax guides, tool documentation

## Directory Structure

```
skills/
  skill-name/
    SKILL.md              # Main reference (required)
    supporting-file.*     # Only if needed
```

**Flat namespace** - all skills in one searchable namespace

## SKILL.md Structure

**Frontmatter (YAML) - Required by OpenCode:**

- `name`: lowercase, hyphens only, 1-64 chars, must match folder name
- `description`: 1-1024 chars, starts with "Use when...", third-person

```markdown
---
name: skill-name-with-hyphens
description: Use when [specific triggering conditions and symptoms]
---

# Skill Name

## Overview

What is this? Core principle in 1-2 sentences.

## When to Use

Bullet list with SYMPTOMS and use cases
When NOT to use

## Core Pattern (for techniques/patterns)

Before/after code comparison

## Quick Reference

Table or bullets for scanning common operations

## Common Mistakes

What goes wrong + fixes
```

## OpenCode Skill Discovery

**Critical for discovery:** OpenCode reads the `description` field to decide which skills to load.

### Description Best Practices

**CRITICAL: Description = When to Use, NOT What the Skill Does**

The description should ONLY describe triggering conditions. Do NOT summarize the skill's process or workflow.

```yaml
# BAD: Summarizes workflow - agent may follow this instead of reading skill
description: Use when executing plans - executes tasks sequentially with code review between tasks

# GOOD: Just triggering conditions, no workflow summary
description: Use when executing implementation plans with independent tasks

# GOOD: Triggering conditions only
description: Use when implementing any feature or bugfix, before writing implementation code
```

### Naming Conventions

**Use active voice, verb-first, gerund form (-ing):**

- `creating-skills` not `skill-creation`
- `condition-based-waiting` not `async-test-helpers`

**Name validation regex:** `^[a-z0-9]+(-[a-z0-9]+)*$`

### Cross-Referencing Other Skills

Reference by skill name with explicit requirement markers:

- Good: `**REQUIRED SKILL:** Use the **test-driven-development** skill`
- Good: `**REQUIRED BACKGROUND:** You MUST understand the **systematic-debugging** skill`
- Bad: `See skills/testing/test-driven-development` (unclear if required)

## Flowchart Usage

Use flowcharts ONLY for:
- Non-obvious decision points
- Process loops where you might stop too early
- "When to use A vs B" decisions

Never use flowcharts for:
- Reference material → Tables, lists
- Code examples → Markdown blocks
- Linear instructions → Numbered lists

See graphviz-conventions.dot in this directory for style rules.

## Code Examples

**One excellent example beats many mediocre ones**

**Good example:**
- Complete and runnable
- Well-commented explaining WHY
- From real scenario
- Shows pattern clearly

**Don't:**
- Implement in 5+ languages
- Create fill-in-the-blank templates
- Write contrived examples

## The Iron Law (Same as TDD)

```
NO SKILL WITHOUT A FAILING TEST FIRST
```

This applies to NEW skills AND EDITS to existing skills.

## Bulletproofing Skills Against Rationalization

Skills that enforce discipline need to resist rationalization.

### Close Every Loophole Explicitly

Don't just state the rule - forbid specific workarounds.

### Address "Spirit vs Letter" Arguments

Add foundational principle early:

```markdown
**Violating the letter of the rules is violating the spirit of the rules.**
```

### Build Rationalization Table

Capture rationalizations from baseline testing. Every excuse agents make goes in the table.

### Create Red Flags List

Make it easy for agents to self-check when rationalizing.

## RED-GREEN-REFACTOR for Skills

### RED: Write Failing Test (Baseline)

Run pressure scenario WITHOUT the skill. Document exact behavior.

### GREEN: Write Minimal Skill

Write skill addressing those specific rationalizations. Run same scenarios WITH skill.

### REFACTOR: Close Loopholes

Agent found new rationalization? Add explicit counter. Re-test until bulletproof.

**Testing methodology:** See testing-skills-with-subagents.md in this directory.

## Skill Creation Checklist

**RED Phase:**
- [ ] Create pressure scenarios (3+ combined pressures for discipline skills)
- [ ] Run scenarios WITHOUT skill - document baseline behavior
- [ ] Identify patterns in rationalizations/failures

**GREEN Phase:**
- [ ] Name uses only lowercase letters, numbers, hyphens
- [ ] YAML frontmatter with `name` and `description`
- [ ] Description starts with "Use when..." and includes specific triggers
- [ ] Description written in third person
- [ ] Clear overview with core principle
- [ ] Address specific baseline failures
- [ ] Run scenarios WITH skill - verify compliance

**REFACTOR Phase:**
- [ ] Identify NEW rationalizations from testing
- [ ] Add explicit counters
- [ ] Build rationalization table
- [ ] Create red flags list
- [ ] Re-test until bulletproof

**Quality Checks:**
- [ ] Small flowchart only if decision non-obvious
- [ ] Quick reference table
- [ ] Common mistakes section
- [ ] No narrative storytelling
- [ ] Supporting files only for tools or heavy reference

**Deployment:**
- [ ] Commit skill to git
