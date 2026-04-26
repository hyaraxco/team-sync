# OpenCode Skill Authoring Best Practices

> Adapted from official skill authoring guidance for the OpenCode platform.

## Core Principles

### Concise is Key

The context window is a shared resource. Your Skill shares it with everything else the agent needs.

**Default assumption**: The agent is already very smart. Only add context it doesn't already have.

### Set Appropriate Degrees of Freedom

Match specificity to task fragility:

- **High freedom** (text instructions): Multiple valid approaches, context-dependent
- **Medium freedom** (pseudocode/scripts): Preferred pattern exists, some variation OK
- **Low freedom** (exact scripts): Fragile operations, consistency critical

## Skill Structure

### YAML Frontmatter

Required fields:
- `name` - 1-64 characters, lowercase alphanumeric with hyphens, must match folder name
- `description` - 1-1024 characters, what the skill does and when to use it

### Writing Effective Descriptions

- **Always write in third person** (injected into system prompt)
- Be specific and include key terms
- Include both what the Skill does and when to use it

### Progressive Disclosure

Keep SKILL.md as an overview. Split heavy content into separate files:

```
skill-name/
├── SKILL.md              # Main instructions (loaded when triggered)
├── reference.md          # API reference (loaded as needed)
├── examples.md           # Usage examples (loaded as needed)
```

**Keep references one level deep from SKILL.md.**

## Content Guidelines

### Avoid Time-Sensitive Information

Use "old patterns" sections instead of date-based conditionals.

### Use Consistent Terminology

Choose one term and use it throughout.

### Template Pattern

Provide templates for output format, matching strictness to needs.

### Examples Pattern

Provide input/output pairs for quality-dependent tasks.

## Workflows and Feedback Loops

### Use Workflows for Complex Tasks

Break complex operations into clear, sequential steps with checklists.

### Implement Feedback Loops

Run validator → fix errors → repeat. This pattern greatly improves output quality.

## Evaluation and Iteration

### Build Evaluations First

Create evaluations BEFORE writing extensive documentation:

1. Identify gaps (run without skill, document failures)
2. Create evaluations (3+ scenarios)
3. Establish baseline
4. Write minimal instructions
5. Iterate

## Anti-Patterns to Avoid

- Windows-style paths (use forward slashes)
- Offering too many options (provide a default with escape hatch)
- Deeply nested references (keep one level deep)
- Vague descriptions ("Helps with documents")

## Checklist for Effective Skills

### Core Quality
- [ ] Description is specific and includes key terms
- [ ] SKILL.md body is under 500 lines
- [ ] No time-sensitive information
- [ ] Consistent terminology throughout
- [ ] Examples are concrete, not abstract
- [ ] Progressive disclosure used appropriately
- [ ] Workflows have clear steps

### Testing
- [ ] At least three evaluations created
- [ ] Tested with real usage scenarios
