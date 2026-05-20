# CLAUDE.md — Project Intelligence File

> This file is the entry point for AI-assisted development on this project.
> All code generation, review, and refactoring must be grounded in the specs
> referenced here. Code is a build artifact of the specification — not the
> source of truth.

---

## Project Overview

**Project:** BattleSnake
**Type:** Backend Service (HTTP game server / AI agent)
**Primary Language(s):** PHP
**Target Platform(s):** Web (BattleSnake game platform — https://play.battlesnake.com)

A PHP-based BattleSnake AI agent. The service exposes the four HTTP endpoints
required by the BattleSnake engine (`GET /`, `POST /start`, `POST /move`,
`POST /end`) and returns a move decision (`up` / `down` / `left` / `right`) on
each turn. The goal is a snake that survives long, outmaneuvers opponents, and
adapts its strategy to board state.

---

## Spec Hierarchy

All specifications live in `/spec`. Code must not diverge from these documents.
If the code and spec conflict, the spec wins — update the code, not the spec,
unless a spec change is explicitly intended and documented.

```
/spec
  /domain          # Core domain model, entities, and relationships
  /features        # Per-feature specifications
  /decisions       # Architecture Decision Records (ADRs)
  /interfaces      # API contracts, SDK interfaces, data models
  GLOSSARY.md      # Canonical terminology — always use these terms
```

**Start here for context:**
- Domain model: `/spec/domain/domain-model.md`
- Glossary: `/spec/GLOSSARY.md`
- Current active features: `/spec/features/`

---

## Engineering Principles

This project follows **SOLID principles** strictly. When generating or reviewing
code, apply these in order of priority:

1. **Single Responsibility** — Every class, module, and function has one reason
   to change. If you find yourself writing "and" to describe what something does,
   it needs to be split.

2. **Open/Closed** — Extend behavior through abstraction, not modification.
   Prefer protocols/interfaces over concrete inheritance.

3. **Liskov Substitution** — Subtypes must be substitutable for their base types
   without altering correctness. Flag any violation explicitly.

4. **Interface Segregation** — Prefer narrow, focused interfaces over broad ones.
   No client should depend on methods it does not use.

5. **Dependency Inversion** — Depend on abstractions, not concretions. All
   dependencies should be injected, not instantiated internally.

**Additional non-negotiable principles:**
- No magic numbers or strings — all constants are named and documented
- All public interfaces are documented before implementation
- Error states are explicit — no silent failures
- Side effects are isolated and clearly identified

---

## What a Bug Means Here

> A bug is a gap between the specification and the implementation — not bad code.

Before writing a fix:
1. Identify which spec document governs the affected behavior
2. Determine whether the spec is ambiguous or the implementation diverged
3. If the spec is ambiguous — update the spec first, then implement
4. If the implementation diverged — correct the implementation to match the spec
5. Never patch code to pass a test if the spec does not support the behavior

---

## Code Generation Rules

When generating code from a spec:

- **Read the relevant spec file in full before writing any code**
- Use terminology from `GLOSSARY.md` exactly — do not invent synonyms
- Implement what the spec states, nothing more
- If the spec has a gap that requires a judgment call, pause and state the
  assumption explicitly before proceeding — do not silently fill gaps
- Generate interfaces and contracts before implementations
- If an implementation decision conflicts with a SOLID principle, flag it
  rather than silently compromising

---

## Pull Request Expectations

Every PR that modifies source code must also:

- [ ] Update the relevant `/spec/features/` document if behavior changed
- [ ] Add or update an ADR in `/spec/decisions/` if an architectural decision
      was made
- [ ] Update `/spec/GLOSSARY.md` if new terms were introduced
- [ ] Pass spec-alignment review (automated Claude review in CI)

PRs that modify spec files without corresponding code changes are valid —
spec refinement is legitimate work.

---

## Architectural Decisions

Significant decisions are recorded as ADRs in `/spec/decisions/`.

Format: `NNN-short-title.md`

Each ADR captures:
- **Status:** Proposed / Accepted / Superseded
- **Context:** Why this decision was needed
- **Decision:** What was decided
- **Consequences:** What this enables and what it constrains
- **Supersedes / Superseded by:** Links to related ADRs

ADRs are append-only. Never edit an accepted ADR — write a new one that
supersedes it.

---

## Glossary Discipline

`/spec/GLOSSARY.md` is the canonical source for all domain terminology.

- If a term appears in code that is not in the glossary, add it before the PR merges
- If a term in code differs from the glossary, the code is wrong
- Claude should use glossary terms verbatim in generated code, comments, and
  variable names

---

## Context for AI Sessions

When starting a new coding session, provide Claude with:

1. The relevant feature spec from `/spec/features/`
2. Any interfaces from `/spec/interfaces/` the feature touches
3. The domain model if new entities are involved
4. Any relevant ADRs that constrain the implementation

Do not paste existing implementation code as context unless debugging a
specific divergence — the spec is the source of truth, not the existing code.

---

## Out of Scope

Do not generate the following without explicit instruction and a corresponding
spec update:

- New public interfaces or API surface
- New dependencies or third-party integrations
- Database schema changes
- Changes to error handling contracts
- Performance optimizations that change observable behavior

---

_Last updated: 2026-05-19_
_Maintained by: Holly Schilling_
