# Gondal Data Model Decisions

This document locks the Version 1 data-model rules that were previously implied but not enforced consistently. It is the source of truth for schema behavior, lifecycle expectations, and program membership resolution.

Use these decisions when changing migrations, Eloquent relations, query scopes, and workflow services.

## Finalized Decisions

### Program membership cardinality

- A farmer may have only one active Gondal program enrollment at a time in Version 1.
- An agent may have only one active Gondal program assignment at a time in Version 1.
- Historical records are preserved by closing the previous enrollment or assignment with `status = inactive` and `ends_on = <effective date>`.
- Reassignment reactivates an existing `(project_id, farmer_id)` or `(project_id, agent_profile_id)` row when it already exists, instead of creating duplicates.

Why this is locked:

- Current workflow services resolve one effective `project_id` for milk collection, orders, inventory credit, and settlements.
- Allowing multiple simultaneous active memberships would make `resolveFarmerProjectId()` and `resolveAgentProjectId()` ambiguous and would create inconsistent `project_id` tagging on financial transactions.

### Program resolution source of truth

- Active assignment and enrollment history tables are the primary source of truth for program membership.
- `gondal_agent_profiles.project_id` is a compatibility field only. It may be synchronized for legacy reads, but it does not outrank active assignment history.
- Farmer program membership is resolved from `gondal_program_farmer_enrollments`, not from territory alone.
- If no active farmer enrollment exists, workflows may fall back to the active agent assignment only where the current service contract already allows it. That fallback is operational convenience, not the canonical membership record.

### Transaction-level `project_id`

- Every new Gondal operational or financial transaction that belongs to a program must store `project_id` at creation time.
- `project_id` remains nullable only for:
  - legacy records created before program tagging was introduced
  - non-program transactions that are explicitly outside sponsor scope
  - backfill or reconciliation records that still require manual classification
- Once posted, `project_id` is immutable historical context. Corrections happen through reversal, reposting, or documented reconciliation flows.

### Lifecycle and history rules

- Enrollment and assignment rows are append-and-close history, not disposable mapping rows.
- A reassignment must end the previous active row instead of deleting it.
- Projects with Gondal transactional history must be archived or marked inactive, not hard-deleted from operational workflows.
- Any future UI delete action for sponsor programs must be replaced with archive semantics before it is exposed for Gondal program records.

### Farmer and agent ownership semantics

- Farmer-to-program membership is explicit through `gondal_program_farmer_enrollments`.
- Agent-to-program membership is explicit through `gondal_program_agent_assignments`.
- Territory, community, one-stop-shop, and cooperative relationships are visibility and operating-scope rules. They do not replace explicit program membership records.
- Version 1 does not introduce a separate farmer-to-agent ownership table. Farmer visibility remains derived from enrollment plus territory or community scope already present in the agent profile.

### Key and identifier strategy

- Keep existing integer primary keys for Version 1. Do not retrofit UUIDs into the Gondal tables at this stage.
- Human-readable references such as batch IDs, obligation references, settlement references, and order numbers remain the public-facing identifiers.
- Cross-table joins and financial relations should continue using numeric foreign keys for consistency with the existing Laravel schema.

## Runtime Enforcement Notes

- `ProgramScopeService` must resolve active program membership using rows whose status is active and whose effective date window includes today.
- Reassignment and re-enrollment workflows must automatically deactivate any other active rows for the same agent or farmer.
- Tests must cover reassignment, fallback behavior, and stale `agent_profiles.project_id` values so runtime logic cannot drift from the data-model decision.

## Schema Implications

- The existing unique constraints on `(project_id, agent_profile_id)` and `(project_id, farmer_id)` remain valid because they prevent duplicate history rows per project.
- Version 1 does not add a database-level partial unique index for "one active row per farmer or agent" because the current schema and database support in this repo do not guarantee a portable partial-index implementation.
- Single-active membership is therefore enforced in the service layer and covered by tests until a database-backed strategy is introduced deliberately.

## Open Questions

- Whether archived projects need an explicit `is_archived` column or can rely on the existing project status model.
- Whether historical reassignment should later capture a reason code in assignment and enrollment metadata.
- Whether legacy `project_id = null` transactions should be backfilled automatically or only through a supervised reconciliation tool.

## Recommended Next Implementation Step

Add an archival rule for Gondal sponsor programs so transactional projects cannot go through the current hard-delete path in `Project::deleteProject()`.
