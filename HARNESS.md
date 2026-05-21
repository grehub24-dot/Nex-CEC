# HARNESS.md — Nex CEC Development Harness

> **Template Version:** 1.0.0
> **Purpose:** Define constraints, enforcement loops, and governance for AI-assisted development in this project.

---

## Three Enforcement Loops

### Advisory Loop (edit time — warn, do not block)

| Mechanism | Trigger | What it checks |
|---|---|---|
| AGENTS.md routing rules | Task dispatch | Routes to correct specialist agent |
| Pre-edit convention check | Before file write | Ensures PHP/SQL style matches project conventions |
| Session prompt | Every task start | Reminds agent of current constraint file |

### Strict Loop (merge/commit time — block until green)

| Gate | Enforcer | What passes |
|---|---|---|
| PHP syntax check | `php -l` | No syntax errors |
| SQL lint | Manual review | No anti-patterns (SELECT *, missing WHERE) |
| Security review | security-auditor | OWASP Top 10 checks pass |
| Code review | code-reviewer | CUPID principles satisfied |
| markdownlint | CI | All .md files conform |

### Investigative Loop (scheduled — sweep for entropy)

| Cadence | Check | Tooling |
|---|---|---|
| Weekly | Harness health snapshot | `/harness-health` |
| Weekly | Reflection curation | Promote from REFLECTION_LOG.md to AGENTS.md |
| Per-session (rotating) | One GC rule | See GC rules below |
| Per-session | Secrets scan | Gitleaks check |

---

## Constraints

### Deterministic (backed by CI/linter tools)

- [ ] **D-001: PHP syntax** — All `.php` files pass `php -l` before commit
- [ ] **D-002: SQL safety** — All SQL migrations avoid `SELECT *`, have explicit `WHERE` clauses on UPDATE/DELETE
- [ ] **D-003: markdownlint** — All `.md` files pass markdownlint rules
- [ ] **D-004: No secrets in git** — `.env` files, API keys, passwords never committed
- [ ] **D-005: Supabase RLS** — All tables have Row Level Security policies defined

### Agent-backed (enforced by specialist agents)

- [ ] **A-001: CUPID code review** — Every PR reviewed against Composable, Unix, Predictable, Idiomatic, Domain-based properties
- [ ] **A-002: Security audit** — Every security-sensitive change reviewed by security-auditor
- [ ] **A-003: Spec-first** — Non-trivial features start with a spec.md before implementation
- [ ] **A-004: Adversarial review** — Specs passed through advocatus-diaboli before plan approval

### Unverified (declared intent, not yet automated)

- [ ] **U-001: Literate programming** — Code reads as literature; comments explain WHY not WHAT
- [ ] **U-002: Migration hygiene** — All DB changes have both `up` and `down` migration scripts
- [ ] **U-003: Test presence** — Business logic has at least one test case

---

## Garbage Collection Rules

| Rule | Cadence | Auto-fix safe? | What it checks |
|---|---|---|---|
| GC-001: Snapshot staleness | Weekly | No | Last `/harness-health` snapshot ≤ 30 days old |
| GC-002: Reflection backlog | Weekly | No | Unpromoted reflections in REFLECTION_LOG.md |
| GC-003: Secret scan | Per-session | No | Gitleaks on staged files |
| GC-004: Dependency drift | Weekly | No | composer.json vs lockfile freshness |

---

## Composite Learning

Learning artifacts flow through this pipeline:

```
REFLECTION_LOG.md (append-only, agent-written)
    ↓  (weekly curation)
AGENTS.md (human-curated compound learning)
    ↓  (session start)
Agent context (read by all agents)
```

**Current learning velocity target:** ≥ 2 reflections per week, ≥ 1 promotion per month.

---

## Harness Health

Run `/harness-health` to generate a snapshot at `observability/snapshots/<date>-snapshot.md`.

The snapshot captures:
- Enforcement ratios (deterministic / agent-backed / unverified)
- GC rule status and findings
- Learning velocity (reflections logged, promotions made)
- Constraint regression count
- Meta-observability status

---

## Version History

| Date | Version | Changes |
|---|---|---|
| 2026-05-20 | 1.0.0 | Initial harness — three enforcement loops, constraints, GC, learning |
