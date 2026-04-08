# Gondal Business Rules

This document is the source of truth for Gondal business policy. Runtime values that are intended to vary by project or over time are stored in the `gondal_business_rules` table and resolved by `App\Services\Gondal\BusinessRuleService`.

Use this document to distinguish three things clearly:

- Policy decisions: rules that developers must implement consistently.
- Runtime config values: values that may change by program or over time without code changes.
- Manual business signoff items: values or choices that are still open and must not be guessed in code.

## System Principles

- Every financial or operational transaction that belongs to a sponsor program must resolve and store a `program_id` at creation time.
- Ledger and journal history is immutable. Corrections happen by reversal and reposting, never by editing historical value records in place.
- Project-scoped runtime rules may override global defaults, but they may not weaken security boundaries such as sponsor isolation or territory enforcement.
- Security and visibility boundaries are structural rules enforced in code, policies, and queries. They are not treated as loosely editable runtime config.

## Finalized Policy Decisions

### Milk pricing logic

- Version 1 uses quality-adjusted per-liter pricing only.
- Milk value is calculated as `accepted liters x grade price`.
- Version 1 does not use volume tiers, time-of-day pricing, or route-vs-center pricing differences.
- Grade `C` or a failed adulteration result means the collection is rejected for payment and posts no farmer credit.
- Historical collections keep the price that applied when they were recorded. Later rule changes do not restate old collections.
- The resolved unit price must be stored on the transaction or journal metadata at posting time so future price changes remain auditable.

### Deduction priority order

- Deductions are applied only during a settlement run.
- The settlement engine must apply obligations in this category order:
  1. Loan recovery
  2. Feed and input credit
  3. Service charges
  4. Marketplace and order recovery
  5. Manual adjustments
  6. Other obligations
- When multiple obligations exist in the same category, apply the oldest due obligation first.
- If due dates are the same or missing, fall back to oldest creation date, then lowest record ID for deterministic ordering.
- The engine must store the itemized allocation for every deduction run.

### Maximum deduction percentage

- Every settlement cycle is capped by a maximum deduction percentage of gross milk value.
- The cap is a runtime config value, not a hardcoded service constant.
- The currently seeded global fallback is `50%`. This remains the active fallback until business signoff changes it.
- Project-level overrides are allowed.

### Minimum payout floor

- A payout floor applies before deductions are allocated.
- The engine must never deduct an amount that pushes net payout below the configured floor.
- The payout floor is a runtime config value, not a hardcoded service constant.
- The currently seeded global fallback is `0`, which means the floor is effectively disabled unless a project or global rule is updated.

### Carry-forward behavior

- If the deduction cap or payout floor prevents full recovery, the unpaid portion remains outstanding automatically.
- Carry-forward is mandatory. The engine must not silently drop, forgive, or merge unpaid balances.
- Each obligation keeps its own remaining outstanding amount and continues into later cycles until settled, cancelled, or reversed.

### Credit eligibility rules

- Credit sales are allowed only for active farmers, active agents, and active inventory items.
- The selling agent must have `credit_sales_enabled = true`.
- The selling agent must remain within their configured credit limit.
- The farmer must be inside the agent's assigned territory or community scope.
- If the transaction is program-scoped, the farmer must qualify for that program on the transaction date.
- Existing outstanding balances do not automatically block new credit in Version 1. That remains a possible tightening rule, but it is not active policy yet.
- Data-model rules around active farmer and agent program membership are locked in `docs/gondal-data-model-decisions.md`.

### Agent permission boundaries

- Company employee agents may operate only within their assigned one-stop shop, territory, and approved catalog.
- Farmer agents and independent resellers must be tied to one active operational program at transaction time in Version 1.
- Agents may create milk collections, orders, visits, inventory sales, and remittances only for farmers inside their assigned communities or explicit territory scope.
- Agents may not reverse journal entries, post manual financial adjustments, or process payout batches.
- Admin and finance users may perform settlement and correction workflows; operational agents may not.
- Sponsor users are read-only for their own program data. They do not post operational or financial transactions.

### Sponsor and program qualification rules

- A farmer qualifies as "in program" only when an active farmer enrollment exists for the transaction date.
- An agent qualifies as "in program" only when an active agent assignment exists for the transaction date. The profile-level `project_id` may be used only as a bootstrap fallback while assignment records are being completed.
- A transaction is considered "in program" only if the resolved farmer-side and agent-side program context agree.
- If farmer context and agent context resolve to different programs, the transaction must be blocked and reviewed, not auto-assigned.
- Every new milk collection, order, credit, settlement, payout, and other Gondal transaction must store `program_id` at creation time.
- Once posted, `program_id` is treated as immutable history. Corrections must happen through reversal and reposting or a documented reconciliation workflow.

## Active Runtime Config Values

These rule keys are already seeded in `gondal_business_rules` and currently resolved by `BusinessRuleService`.

### `milk.grade_prices`

Purpose: Defines the price per liter by milk grade.

Current global fallback:

```json
{
  "A": 120,
  "B": 100,
  "C": 0
}
```

Implementation note: This is the active runtime pricing rule used by milk value posting.

### `settlement.defaults`

Purpose: Defines the fallback deduction cap and payout floor when a settlement request does not override them.

Current global fallback:

```json
{
  "max_deduction_percent": 50,
  "payout_floor_amount": 0
}
```

Implementation note: This controls the cap and floor mechanics, but not the ordering of obligation categories.

### `inventory.credit_payment_methods`

Purpose: Lists payment methods that create farmer credit obligations.

Current global fallback:

```json
[
  "Credit",
  "Milk Collection Balance"
]
```

Implementation note: This determines which inventory sales create open farmer obligations.

### `inventory.credit_obligation_defaults`

Purpose: Defines the default recovery policy for inventory credit obligations.

Current global fallback:

```json
{
  "priority": 10,
  "max_deduction_percent": 35,
  "payout_floor_amount": 0,
  "due_days": 14
}
```

Implementation note: This is currently used for obligation creation from inventory credit sales. It is not yet the full cross-category deduction priority policy for all obligation types.

## Proposed Additional Rule Keys

These keys should be added only when the implementation is ready to consume them. They are proposed here so the next phase can extend `BusinessRuleService` deliberately instead of hardcoding logic in `SettlementService` or inventory workflows.

### `settlement.deduction_priority`

Purpose: Moves cross-category deduction order out of application code.

Suggested shape:

```json
{
  "order": [
    "loan",
    "feed_input_credit",
    "service_charge",
    "marketplace_order",
    "manual_adjustment",
    "other"
  ],
  "same_type_order": "oldest_due_date_first"
}
```

Why this is justified: the checklist requires explicit deduction priority and same-type ordering, and this is the next missing rule the settlement engine should consume.

### `inventory.credit_eligibility`

Purpose: Centralizes blocking rules for new credit sales.

Suggested shape:

```json
{
  "require_active_farmer": true,
  "require_active_agent": true,
  "require_program_qualification": true,
  "block_when_credit_limit_exceeded": true,
  "block_when_farmer_outside_agent_scope": true,
  "block_when_farmer_has_overdue_balance": false
}
```

Why this is justified: the current inventory flow already enforces part of this policy in code, and this key would allow that behavior to become explicit and auditable.

### Why agent boundaries and sponsor qualification are not runtime keys yet

- Agent permission boundaries are security rules and should remain enforced by RBAC, policies, and scoped queries.
- Sponsor and program qualification rules are structural data rules and should remain enforced by enrollment and assignment lookups, not soft config alone.
- These policies belong in code and documentation first. Only tunable sub-parts should move to config keys later.

## Values Requiring Manual Business Signoff

The following values or choices are still open and must not be guessed by developers:

- Whether the global settlement deduction cap should remain `50%` or be reduced to `40%`.
- Whether the payout floor should remain disabled globally (`0`) or be set to a fixed naira minimum.
- Whether overdue obligations should block new credit orders in Version 1.
- Whether sponsor-funded orders should reserve sponsor budget at order submission or only at fulfillment.

## Scope Rules

- Global rules use `scope_type = global` and `scope_id = 0`.
- Program-specific overrides use `scope_type = project` and `scope_id = <project_id>`.
- The resolver checks project-scoped rules first and falls back to global rules.
- Security boundaries still apply even when a project-specific runtime rule exists.

## Change Process

- Update the database rule value and this document in the same change.
- Do not reintroduce runtime config values as hardcoded constants in services.
- When a rule change affects posted financial values, apply it only to future transactions unless a formal reversal and repost workflow is executed.
- If a policy decision changes, update tests for settlements, milk collections, sponsor scope, and order recovery in the same change.

## Finalized Decisions

- Version 1 milk pricing is quality-adjusted per-liter pricing only.
- Deduction order is fixed by obligation category and then oldest obligation first within category.
- Deduction cap and payout floor are runtime config values, with current seeded fallbacks of `50%` and `0`.
- Carry-forward is mandatory whenever the cap or payout floor prevents full recovery.
- Credit requires active participants, in-scope territory, and program qualification where applicable.
- Agent permissions are bounded by territory, program, and role; sponsors are read-only and program-scoped.
- Program membership is resolved at transaction time and stored as immutable history on the transaction record.
- Farmer and agent program membership is single-active in Version 1, as locked in `docs/gondal-data-model-decisions.md`.

## Open Questions

- Should the global deduction cap be `50%` or `40%`?
- Should the payout floor remain `0` globally, or should a minimum farmer payout be mandated?
- Should overdue balances block new credit sales?
- At what point should sponsor-funded orders reserve budget?

## Recommended Next Implementation Step

Implement `settlement.deduction_priority` in `BusinessRuleService`, seed it in `gondal_business_rules`, and update `SettlementService` to consume it so deduction ordering is no longer implied by hardcoded obligation priority values alone.
