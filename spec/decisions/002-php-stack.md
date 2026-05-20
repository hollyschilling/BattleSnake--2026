# ADR 002 — PHP Stack: PHP 8.3+, Slim 4, Composer, PHPUnit

**Status:** Accepted
**Date:** 2026-05-19

## Context

The server is a stateless HTTP responder that must answer `POST /move`
within 500 ms. The algorithm is multi-source BFS over an 11×11 grid: CPU
load is negligible. Constraints:

- Low cold-start and per-request overhead.
- Straightforward PSR-7 request/response handling.
- Mature testing tools — the move algorithm benefits from a large test
  corpus of synthetic and recorded GameStates.
- PHP 8.4 is available to the author locally; 8.3 is a likely portability
  floor.

## Decision

- **Language:** PHP, minimum 8.3, target 8.4.
- **Framework:** Slim 4 (PSR-7 / PSR-15).
- **Dependency management:** Composer.
- **Testing:** PHPUnit.
- **Code style:** PSR-12.

## Rationale

- Slim is minimal — a thin router and middleware pipeline over PSR-7. It
  imposes no architecture; the move algorithm lives in plain domain
  classes that are independently testable without an HTTP stack.
- PHP 8.3 provides readonly classes, typed enums, first-class callable
  syntax, and typed class constants — sufficient to model the Domain
  cleanly. 8.4 property hooks are nice but not required.
- PHPUnit has the largest body of related examples and tooling. Pest is
  fine but offers no decisive advantage here.

## Consequences

**Enables:**

- Clean separation between transport (`App\Http`) and algorithm
  (`App\Domain`, `App\Strategy`).
- Fast unit tests over the algorithm without booting an HTTP stack.

**Constrains:**

- Slim-specific abstractions must not leak into the Domain layer (per
  [CLAUDE.md](../../CLAUDE.md) SOLID requirements). HTTP handlers parse
  the request JSON into Domain types and pass those into the algorithm;
  the algorithm returns Domain types; handlers serialize the response.
- Async / parallel BFS is not on the table — PHP-FPM is
  one-process-per-request. The 11×11 board makes this a non-issue.

## Supersedes / Superseded by

None.
