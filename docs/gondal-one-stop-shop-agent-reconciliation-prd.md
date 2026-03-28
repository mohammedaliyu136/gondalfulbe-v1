# Gondal One-Stop-Shop Agent Inventory and Reconciliation PRD

## 1. Purpose

Build a database-backed one-stop-shop inventory operating model for Gondal where agents sell company-owned stock, can sell on credit when allowed, remit proceeds, and undergo reconciliation with clear accountability.

This module must integrate with the existing ERP `users`, `roles`, permissions, and current Gondal inventory records rather than introducing a separate identity system.

## 2. Problem Statement

The current Gondal inventory flow records:

- inventory items
- direct sales
- credit sales

It does not model:

- which agent received stock
- who is responsible for unsold stock
- expected versus actual remittance
- physical stock count
- reconciliation variance
- different settlement treatment for employees, vendors, and independent resellers

This creates operational and financial gaps, especially when inventory is distributed to agents for resale.

## 3. Goals

- Reuse existing `users` and role management for internal and external system actors.
- Introduce an `agent profile` layer for business behavior and settlement rules.
- Support three agent types:
  - employee
  - vendor
  - independent reseller
- Support cash, transfer, and credit sales.
- Allow admin-controlled credit toggles globally and per agent.
- Make reconciliation database-backed and auditable.
- Default operational reconciliation to daily, while also supporting weekly and batch-based review.
- Provide shortage handling through supervisor review, not automatic deduction.

## 4. Non-Goals

- Full payroll deduction automation in this phase
- Full accounting journal posting in this phase
- Offline/mobile-first field app in this phase
- Customer credit collections workflow beyond basic ledger linkage in this phase

## 5. Users and Roles

### 5.1 Existing Identity Layer

Use the current ERP user management as the master identity system:

- `users`
- `roles`
- permissions

### 5.2 Agent Business Layer

An agent is not defined only by role. An agent also needs business classification.

Recommended split:

- `role`: what the user can do in the system
- `agent_type`: what commercial relationship the user has with the company

Examples:

- role: `Field Sales Agent`, agent_type: `employee`
- role: `Field Sales Agent`, agent_type: `vendor`
- role: `Field Sales Agent`, agent_type: `independent_reseller`

### 5.3 Operational Actors

- Agent
  - records sales
  - submits remittance
  - submits physical count
- Supervisor
  - verifies counts
  - reviews shortages
- Reconciliation Officer / Finance Reviewer
  - validates remittance and credit exposure
- Admin
  - configures credit policy, tolerances, and escalation rules

## 6. Core Business Rules

### 6.1 Stock Ownership

Stock remains company-owned until sold or otherwise approved for transfer/adjustment.

### 6.2 Credit Sales

Credit sales are allowed only when enabled:

- globally by admin
- and/or per agent profile

Credit sales create receivables, not immediate cash shortages.

### 6.3 Reconciliation Cadence

Recommended default:

- daily operational reconciliation

Also supported:

- weekly management review
- per batch reconciliation where stock is issued as a route/event/batch

### 6.4 Physical Stock Count

Recommended model:

- agent submits first count
- supervisor verifies exception cases
- high variance requires supervisor review before closure

### 6.5 Shortage Handling

Shortages must not auto-deduct.

Workflow:

1. system calculates shortage/overage
2. agent submits explanation
3. supervisor reviews
4. finance/admin finalizes treatment

Treatment by agent type:

- employee: may become payroll recovery candidate after approval
- vendor: becomes debt/payable/settlement offset after approval
- independent reseller: becomes receivable/settlement offset after approval

## 7. Functional Requirements

### 7.1 Agent Profiles

System must support an `agent profile` linked to:

- a `user_id` when the agent has login access
- optionally a `vender_id` when the business party already exists as a vendor/farmer-style entity

Each agent profile must store:

- agent code
- agent type
- status
- assigned warehouse / outlet reference
- supervisor user
- reconciliation frequency
- settlement mode
- credit enabled flag
- credit limit
- variance thresholds

### 7.2 Stock Issue

System must support issuing inventory from company stock to an agent.

Each issue must store:

- issue reference
- agent profile
- inventory item
- quantity issued
- unit cost
- issue date
- batch reference
- notes
- issued by user

### 7.3 Sales

Inventory sales must support:

- cash
- transfer
- credit

Each sale should capture:

- agent profile
- inventory item
- quantity
- unit price
- total amount
- payment method
- sold date
- customer/member name
- optional vendor/farmer linkage
- credit ledger linkage when payment method is credit

### 7.4 Credit Ledger

Credit records must support:

- open
- partial
- settled
- overdue
- written off

Each credit should store:

- customer name
- linked sale
- agent profile
- amount
- outstanding amount
- due date
- status

### 7.5 Remittances

System must store agent remittances independently from sales.

Each remittance should store:

- agent profile
- reconciliation period or batch
- amount remitted
- method
- reference
- remitted at
- received by user
- notes

### 7.6 Physical Counts

System must record physical count submissions.

Each count should store:

- agent profile
- inventory item
- counted quantity
- count date
- submitted by
- verified by
- verification date
- notes

### 7.7 Reconciliation

System must support daily/weekly/batch reconciliation records with status flow:

- draft
- submitted
- under_review
- approved
- approved_with_variance
- escalated
- closed

Each reconciliation should store:

- agent profile
- mode: daily / weekly / batch
- period start
- period end
- issue quantity
- sold quantity
- credit quantity/value
- cash expected
- cash remitted
- physical stock counted
- expected stock
- stock variance
- cash variance
- credit exposure
- status
- reviewer
- approved by
- resolution notes

## 8. Reconciliation Logic

### 8.1 Expected Stock

Expected stock balance per agent/item:

`opening + stock_issued - stock_sold - approved_damage - approved_returns_out + returns_in`

### 8.2 Expected Cash

Expected remittance:

`cash_sales + transfer_sales + credit_collections - refunds`

### 8.3 Credit Exposure

Outstanding credit:

`credit_sales - credit_collections - approved_writeoffs`

### 8.4 Variance

- stock variance = expected stock - counted stock
- cash variance = expected cash - remitted cash

## 9. Data Model

### 9.1 New Tables

- `gondal_agent_profiles`
- `gondal_stock_issues`
- `gondal_agent_remittances`
- `gondal_inventory_reconciliations`

### 9.2 Existing Tables to Extend

- `gondal_inventory_sales`
  - add `agent_profile_id`
  - add `credit_allowed_snapshot`
  - add `credit_entry_id` if needed later

- `gondal_inventory_credits`
  - add `agent_profile_id`
  - add `inventory_sale_id`
  - add `outstanding_amount`
  - add `due_date`

## 10. Permissions

Suggested new permissions:

- manage agent profile
- create agent profile
- edit agent profile
- issue agent stock
- manage agent remittance
- create agent remittance
- submit reconciliation
- review reconciliation
- approve reconciliation
- manage inventory credit policy

## 11. UX Scope

### Phase 1 UI

- Agent profile management
- Stock issue entry
- Enhanced sales form with agent selection
- Remittance entry
- Reconciliation summary list
- Variance review panel

### Dashboard KPIs

- stock issued today
- expected remittance today
- remittance received today
- open credit exposure
- agents with unresolved variance
- overdue credit accounts

## 12. Reporting

- daily reconciliation by agent
- weekly agent performance summary
- stock issued vs sold vs counted
- remittance vs expected cash
- overdue credit by agent
- shortage/overage exception report

## 13. Phase Plan

### Phase 1

- schema foundation
- agent profiles
- stock issues
- remittances
- reconciliation records
- inventory sales and credits linked to agent profile

### Phase 2

- supervisor review workflow
- count verification flow
- tolerance rules
- weekly/batch dashboards

### Phase 3

- accounting integration
- payroll/debt settlement routing
- collections and write-off workflow

## 14. Implementation Decision for This Build

This implementation slice should deliver:

- PRD in repo
- foundational database schema
- Eloquent models/relationships
- integration hooks into existing Gondal inventory tables

UI and full workflow screens can be layered on top once the data model is stable.
