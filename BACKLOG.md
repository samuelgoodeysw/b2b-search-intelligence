# B2B Search Intelligence Backlog

## Current product focus

B2B Search Intelligence should stay focused on logged-in customer search intent.

Core question:

Have logged-in B2B customers searched for products, parts, SKUs, or technical terms but not successfully reached, added, or ordered anything matching?

## Near-term backlog

### 1. Rename Top Search Intelligence Actions

Current label:

Top Search Intelligence Actions

Potential clearer labels:

- Top Actions
- Priority Actions
- Search Intelligence Actions
- Customer Search Actions

Preferred direction:

Use a shorter label in the dashboard, likely:

Top Actions

Reason:

The current label is accurate but too long. The dashboard should feel simple and commercially useful.

### 2. Recoverable revenue model

Add a recoverable revenue or potential value estimate.

Status:

TBD.

Possible approaches to investigate:

- unresolved searches x customer average order value
- unresolved searches x account average order value
- unresolved searches x product/category average value where matched
- unresolved searches x configured estimated order value
- account-specific order history where available

Important wording:

Use directional language only.

Recommended labels:

- Potential recoverable value
- Estimated unresolved intent value
- Directional opportunity value

Avoid:

- guaranteed lost revenue
- exact lost sales
- proven recovery

### 3. Sort and filter controls

Add table sorting and filtering for the logged-in customer search table.

Needed filters:

- customer
- lifecycle status
- follow-through status
- matched item
- unresolved only

Needed sorting:

- newest/oldest search
- response time high/low
- result count high/low
- customer A-Z
- lifecycle status
- follow-through status

### 4. Search box above the table

Add a separate search area above the table.

Purpose:

Quickly search customer-level records without using browser find.

Search fields:

- customer name
- customer email
- search term
- matched product name
- matched SKU

Suggested label:

Search customers, search terms, or matched products

### 5. Export current view

Add export for the currently filtered table view.

Export fields:

- customer name
- customer email
- search term
- searched at
- completed at
- response time
- result count
- lifecycle status
- lifecycle explanation
- follow-through status
- follow-through explanation
- matched item name
- matched item SKU
- unresolved status

Suggested button label:

Export current view

### 6. Export unresolved searches

Add a second export focused only on unresolved search intent.

Suggested button label:

Export unresolved searches

Purpose:

Give merchants a practical review list.

### 7. Configurable slow-search threshold

Current behaviour:

Slow search threshold is hardcoded in the provider.

Backlog:

Make this configurable.

Potential default:

3000ms

Possible options:

- 1000ms
- 2000ms
- 3000ms
- 5000ms

### 8. Demo data helper

Add a safe local/demo helper to create predictable demo rows.

Desired examples:

- completed search with matching cart item
- completed search with zero results and no follow-through
- search started but completion not recorded
- completed slow search
- completed search with matching order

Purpose:

Make demos repeatable and reduce manual setup.

## Keep out of this focused module for now

Do not reintroduce these until the focused product is stable:

- broad failed-search audit
- GA4 weak-search analytics
- catalogue evidence diagnosis
- revenue-at-risk from generic failed searches
- Adobe Live Search comparison dashboards
- LLM clustering
- workflow/assignment tooling

Those belong to the broader Search Loss Audit product or a later phase.

## Later backlog: Customer search aggregation

Add an aggregated customer-level view.

Current table shows individual logged-in search events.

A later customer summary view should group events by customer so merchants can quickly see customer-level search patterns.

Possible customer-level fields:

- customer name
- customer email
- total searches
- unresolved searches
- completed searches
- zero-result searches
- searches with no matching cart or order
- searches that led to cart
- searches that led to order
- most searched terms
- recently searched terms
- matched products
- latest search date
- latest unresolved search date

Possible labels:

- Customer Search Summary
- Customer Intent Summary
- Customer Search Trends
- Account Search Intelligence

Why this matters:

B2B merchants often care about accounts, not just individual search terms.

A grouped customer view could help answer:

Which customers are repeatedly searching but not finding or buying?

Which accounts are showing unresolved product intent?

Which customers are searching for the same product families repeatedly?

Which customers searched and later added or ordered matching items?

Suggested timing:

Do not build this before the focused event-level dashboard is stable.

Build later after:

- filters and exports are validated
- demo flow is clean
- recoverable value model is decided
- customer-level grouping has a clear client/demo story

Keep this read-only and diagnostic.

Do not add customer follow-up workflows yet.
