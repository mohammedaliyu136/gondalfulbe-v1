ERP Implementation Checklist
Each section follows the same structure: Define → Build → Test → Common mistakes

Section 1: Business rules (do this first)
Define

Milk pricing logic (by volume, quality, time-based, or combination)
Deduction rules: priority order, caps, minimum payout floor
Credit eligibility rules
Agent permission boundaries
Sponsor program rules (what qualifies a farmer or transaction as "in program")

Build

A single business rules document (source of truth for all developers)
Config tables in the database — no hardcoded logic in backend code

Test

Farmer sells milk → check ledger credit
Farmer has 2+ debts → verify deduction priority is respected
Farmer hits minimum payout floor → check remainder carries forward

Common mistakes

Hardcoding rules directly in application code
Not deciding deduction priority before any other module is built


Section 2: Core data model
Define
Entities: farmers, agents, sponsors, programs, centers, products/services, users/roles
Relationships:

farmer → agent (many-to-one)
agent → program (decide: can one agent span programs?)
farmer → center
transaction → program (critical — every transaction must carry a program_id)

Build

Full database schema (PostgreSQL recommended)
Consistent ID system (UUID or structured IDs — pick one)
Foreign keys and constraints enforced at database level

Test

Can one farmer belong to multiple programs? Define and enforce the rule.
Can an agent span multiple programs? Same — decide and lock it in.
Does removing a program break transaction history?

Common mistakes

Not tagging transactions with program_id from day one — retroactively adding this breaks reporting
Weak foreign key relationships that allow orphaned records


Section 3: Ledger system (core engine — build before anything transactional)
Define
What is a ledger entry? An immutable record of any value change for a farmer.
Entry types: milk income (credit), order debit, loan disbursement, deduction recovery, manual adjustment
Reversal rules: reversals create a new opposing entry — never delete or edit an existing entry.
Build
Table: ledger_entries
FieldNotesidUUIDfarmer_idFK → farmersamountPositive = credit, negative = debittypeEnum: milk_income, order_debit, loan_disbursement, deduction_recovery, adjustmentreference_idFK to source record (milk collection, order, loan)program_idNullable FK → programscreated_atTimestamp, immutablecreated_byUser/agent who posted the entry
Test

Farmer sells milk → credit entry appears
Farmer buys feed → debit entry appears
Repayment → credit reduces outstanding balance
SUM(amount) WHERE farmer_id = X always matches displayed balance
Reversal creates a new entry, original stays intact

Common mistakes

Storing balance as a single column instead of summing ledger entries — this breaks audit trails
Not storing reference_id — makes it impossible to trace which order caused a debit


Section 4: Milk collection
Define

Collection process: center drop-off vs. route collection (agent visits farm)
Pricing logic: flat rate, tiered by volume, quality-adjusted, or time-of-day
Quality test integration: pass/fail, or graded adjustment to price

Build
Tables: milk_collections, quality_tests (if quality grading applies)
Workflow:

Agent selects farmer
Enters volume and quality reading
System applies pricing logic → computes value
Record saved → ledger credit posted immediately

Test

Multiple collections for same farmer on same day
Rejected milk (quality fail) → no ledger credit posted
Price rule changes mid-month → old collections use old price, new ones use new price
Center-level reconciliation: sum of collections = sum of ledger credits for that center

Common mistakes

Not posting to ledger immediately after collection — creates a sync gap
No per-center reconciliation report → errors go undetected


Section 5: Orders and marketplace

Note: Sponsor-funded payment mode depends on the Sponsor/Program module (Section 9). Build the payment mode infrastructure here, but defer sponsor-funded orders to after Section 9 is complete.

Define

Product/service catalog (who owns it: company, agent, or both?)
Payment modes: cash, deduction from milk earnings, sponsor-funded
Order lifecycle: draft → submitted → fulfilled → settled

Build
Tables: orders, order_items
Workflow:

Agent selects farmer
Selects product(s) and quantity
Chooses payment mode
Order submitted (status: pending)
Fulfilled (status: fulfilled)
Ledger debit posted

Test

Mixed payment: partial cash + partial deduction
Order placed before milk income exists → deduction deferred or blocked (define the rule)
Order cancellation → reversal entry posted

Common mistakes

Not separating order creation from fulfillment — they happen at different times with different actors
No status lifecycle → impossible to report on pending vs. fulfilled orders


Section 6: Deduction engine (highest operational risk — get this right before settlements)
Define

Priority order: e.g., loans → feed → services → other (document this explicitly)
Maximum deduction percentage per cycle (e.g., 40% of milk value)
Minimum payout floor (e.g., farmer always receives at least ₦X)
Multi-cycle recovery: if cap is hit, remainder carries forward to next cycle

Build
Engine inputs:

Farmer's milk value for the cycle
All outstanding obligations with their types and amounts

Engine outputs:

Deduction allocation per obligation (itemized)
Total deduction
Farmer net payout
Carry-forward balance per obligation

Each deduction run should produce a stored record — not just update balances in place.
Test
Scenario:

Farmer earns ₦10,000
Loan outstanding: ₦8,000 | Feed outstanding: ₦5,000
Max deduction: 40% = ₦4,000
Expected: ₦4,000 applied to loan first; feed untouched this cycle; loan balance = ₦4,000 carried forward
Farmer payout: ₦6,000

Also test:

Farmer earns less than minimum payout floor → zero deduction
Multiple obligations of the same type → deduction within that type by date (oldest first?)

Common mistakes

Hardcoding priority rules in application code instead of config
Not storing the deduction breakdown — farmers and agents will dispute amounts
Silently skipping carry-forward when the cap is hit


Section 7: Farmer management
Define

Onboarding flow and required KYC documents
Agent assignment rules (who can assign, can assignment be changed, history kept?)
Farmer status lifecycle: active, inactive, suspended

Build

Farmer profile (KYC fields, contact, location)
Agent assignment with history log
Transaction history view
Credit/balance profile

Test

Farmer reassigned to new agent → historical transactions remain linked to original agent
Inactive farmer → blocked from new orders, existing obligations still settle

Common mistakes

Weak identity/KYC → fraud risk and duplicate farmer records
No assignment history → disputes about which agent owns a farmer


Section 8: Agent management
Define

Agent types: company employee, independent, or sponsored (tied to a program)
Territory definition: geographic area, center, or farmer list

Build

Agent onboarding and KYC
Territory/farmer assignment
Permission scope per agent type (what products can they sell? what programs can they access?)
Performance tracking (milk collected, orders placed, repayment rate of their farmers)

Test

Agent switching programs → does their farmer list follow them or stay?
Agent with multiple territories → reporting correctly aggregates both
Agent tries to sell a product outside their permitted catalog → blocked

Common mistakes

No control over which agents can sell which products
Performance data not captured → no basis for agent incentives or accountability


Section 9: Sponsor and program module

Dependency: This section must be complete before sponsor-funded payment modes (introduced in Section 5) are activated.

Define

Program structure: what does a program include? (farmers, agents, products, funding limits)
Sponsor visibility scope: a sponsor should only see their agents, their farmers, and their transactions
Funding limits per program and per farmer

Build
Tables: sponsors, programs, program_agents, program_farmers
Logic: every transaction is optionally tagged with program_id. Sponsor dashboards filter all queries by program_id — not just by role.
Test

Sponsor logs in → cannot see data from other programs
Sponsor sees correct aggregate impact (farmers enrolled, milk collected, orders funded)
Transaction tagged with wrong program_id → caught in reconciliation

Common mistakes

Relying on role-based access alone — a sponsor role can still see other programs' data if queries aren't filtered by program_id
Not linking program_agents and program_farmers → blurry program boundaries

See Section 14 (Access Control) for how program-level filtering integrates with the full RBAC model.

Section 10: Credit and loans
Define

Loan types (input loan, equipment loan, emergency loan — different repayment rules)
Approval workflow (who approves? auto-approve under a threshold?)
Repayment rules: deduction-based, fixed schedule, or both

Build
Tables: loans, repayment_schedules, loan_disbursements
Test

Happy path: loan disbursed → regular deduction over N cycles → loan closed
Partial recovery: cap hit → balance carries forward → eventually cleared
Default: farmer inactive → loan flagged, no further deductions
Overlapping loans: system correctly applies deduction priority across multiple open loans

Common mistakes

Not linking loans to the deduction engine — loan balance and deduction engine operate independently and go out of sync
No loan closure event → loan stays "open" after being fully repaid


Section 11: Inventory and services
Define

Stock ownership: company-owned, agent-held, or both
Service types: vet visit, equipment repair, AI (artificial insemination) — each has different workflow
Technician assignment for services

Build

Stock tracking (inflows, outflows, transfers between agents)
Service request workflow: request → assigned → completed → invoiced
Batch and expiry tracking for perishable inputs

Test

Stock transfer from company to agent → both inventories update correctly
Service completed → ledger debit posted for farmer
Expired batch flagged → blocked from sale

Common mistakes

Ignoring batch/expiry tracking for agricultural inputs (seeds, vaccines, feed)
No technician assignment → no accountability for service completion


Section 12: Reporting and dashboards
Define
KPIs by role:

Operations: daily milk volume, active farmers, unfulfilled orders
Finance: repayment rate, deduction totals, settlement amounts
Agent: their farmers' activity, their own performance
Sponsor: program impact — milk collected, farmers supported, funds deployed

Build

Role-scoped dashboards (each role sees only what they need)
Export capability (CSV at minimum)
Scheduled reports for settlements and reconciliation

Test

All dashboard numbers reconcile with the ledger — no derived totals that diverge
Sponsor dashboard correctly filters to their program only (re-test this explicitly)
Report runs in acceptable time with realistic data volume

Common mistakes

Building reports before the underlying data model is stable — reports built on a moving schema become a maintenance burden
Deriving totals in the report layer instead of the ledger — creates inconsistencies


Section 13: Access control and security

Connects to Section 9: Program-level filtering (sponsor isolation) is a data-layer concern separate from role-based access. Both are required — neither alone is sufficient.

Define
Three layers of access control:

Role — what actions can this user type perform? (admin, agent, sponsor, farmer)
Program scope — which program's data can this user see?
Territory scope — which geographic area or farmer list does this user cover?

Build

RBAC (role-based access control) for action permissions
Row-level filtering for program_id and territory on all data queries
Audit log: who accessed or changed what, and when

Test

Sponsor cannot retrieve data from another program — test at the API level, not just the UI
Agent sees only their assigned farmers — test with a farmer reassigned mid-cycle
Admin can override scope for support purposes — log the access

Common mistakes

Implementing role checks in the UI only — API endpoints also need enforcement
Treating role and scope as the same thing — a sponsor can have the "sponsor" role but still leak data if program filtering isn't in the query


Section 14: Notifications
Define
Key events that trigger notifications:

Payment settled → farmer notified of net payout
Deduction applied → farmer notified of amount and reason
Order confirmed → farmer and agent notified
Loan disbursed → farmer notified
Low stock alert → agent notified

Build
Table: notification_log (event type, recipient, channel, status, timestamp)
Workflow:

Transaction finalised
Event queued in notification system
Message delivered via SMS or WhatsApp
Delivery status recorded

Test

Notification sent only after transaction is final — not on draft or pending state
Failed delivery → retry logic triggers (with a cap on retries)
Duplicate prevention: same event doesn't produce two messages

Common mistakes

Sending notifications before the transaction is confirmed — farmer receives a payment notification that later gets reversed
No delivery log → no way to know if a farmer actually received a critical message

