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
