# MODEL_ROUTING.md — Agent Model Tier Assignment

> **Purpose:** Map each agent role to a model tier for cost-efficient dispatch.
> **Updated:** 2026-05-20

---

## Model Tiers

| Tier | Models | Use case |
|---|---|---|
| **T1 — Most capable** | `anthropic/claude-sonnet-4-6`, `openai/gpt-5.5` | Spec writing, code review, security audit, adversarial review |
| **T2 — Standard** | `anthropic/claude-haiku-4-5`, `openai/gpt-5.4-mini` | Implementation, debugging, database work, DevOps |
| **T3 — Fast/cheap** | `anthropic/claude-haiku-4-5`, `openai/gpt-5.4-mini` | Simple queries, file reads, status checks |

---

## Agent → Tier Mapping

| Agent | Tier | Rationale |
|---|---|---|
| **Orchestrator** | T2 | Coordination logic, not deep reasoning |
| **Backend Engineer** | T2 | Implementation work, well-defined patterns |
| **Frontend Engineer** | T3 | UI generation, well-defined patterns |
| **Database Architect** | T2 | Schema design needs precision |
| **DevOps Engineer** | T2 | CI/CD config needs correctness |
| **Security Auditor** | T1 | Vulnerability detection needs depth |
| **Code Reviewer** | T1 | Quality judgment needs best model |
| **MCP Builder** | T2 | Tool building needs moderate reasoning |
| **Debugger** | T2 | Root cause analysis |
| **Spec Writer** *(new)* | T1 | Design decisions shape entire feature |
| **Advocatus Diaboli** *(new)* | T1 | Adversarial reasoning needs depth |

---

## Token Budget Guidance

| Agent | Max input tokens | Max output tokens | Cost cap per call |
|---|---|---|---|
| T1 agents | 128K | 16K | $0.50 |
| T2 agents | 64K | 8K | $0.15 |
| T3 agents | 32K | 4K | $0.05 |

---

## Cost Tracking

Log AI spend quarterly via `/cost-capture`. Track:
- Provider API costs
- Model-tier distribution (% T1 vs T2 vs T3)
- Cost per PR / per feature
- Monthly trend vs previous quarter

---

## Review Cadence

Revisit this file quarterly or when:
- A new model tier becomes available at better price/performance
- Actual spend deviates significantly from budget
- Agent pipeline changes (new roles added/removed)
