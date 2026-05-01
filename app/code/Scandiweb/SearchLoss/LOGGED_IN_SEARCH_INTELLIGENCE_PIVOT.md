# Logged-in Search Intelligence — Product Pivot Note

## Strategic note

The strongest product angle is a focused logged-in customer search intelligence offer.

Core question:

Have my logged-in customers been searching for products but not finding, reaching, adding, or buying them?

This is especially strong for B2B Magento / Adobe Commerce stores because customers often search with clear buying intent:

- SKU
- part number
- product name
- category
- brand
- replacement part
- technical phrase
- fitment/use-case wording

## Why this is the headliner

Failed searches and weak searches are useful diagnostic reports, but they are store-level.

Logged-in Search Intelligence is customer-intent-level.

It can show:

- which logged-in customer searched
- what they searched for
- when they searched
- whether Magento completed the search response
- how long the search response took
- how many results were returned
- whether they later added or ordered something matching

This gives the client a reason to check the dashboard frequently.

## Important distinction

Native Magento `search_query` cannot tell which customer searched what.

Magento's native `search_query` table is aggregate search-term data only.

It does not store:

- customer_id
- account/company ID
- email
- session ID
- visitor ID
- quote ID

Therefore, historical customer-level search intent cannot be reconstructed from native Magento `search_query` alone.

However, because B2B customers are logged in, Search Loss can add lightweight event tracking going forward.

## Current technical breakthrough

Predispatch capture can record a logged-in customer's intended search before Magento finishes building the search result page.

This gives us:

- customer_id
- search term
- searched_at
- store_id

Predispatch means:

Magento received the intended search request.

Postdispatch means:

Magento completed the server-side search response.

This enables lifecycle tracking:

- search started
- search completed
- response time
- result count
- completion not recorded

## Safe wording

Use:

- Logged-in Search Intelligence
- unresolved logged-in searches
- customer search intent
- search started
- search completion not recorded
- possible search-friction signal
- possible search-speed issue
- no matching cart/order found

Avoid:

- lost customer
- guaranteed lost revenue
- customer definitely abandoned
- customer definitely left because search was slow
- exact revenue loss

## Product direction

Finish the current Search Loss Audit build normally.

Then create an offshoot focused only on Logged-in Search Intelligence.

The offshoot should be a lightweight, targeted offering.

It does not need to lead with:

- failed search audit
- GA4 weak-search enrichment
- broad dashboard positioning

Those can be added later.

## Target lightweight offer

Suggested name:

Logged-in Search Intelligence

Alternative names:

- Customer Search Intelligence
- B2B Search Intent Monitor
- Unresolved Logged-in Searches
- Search Intent Audit

Suggested pitch:

Show what logged-in customers searched for, whether Magento completed the search response, and whether they later added or ordered anything matching.

## Initial feature scope

### 1. Search event capture

Capture logged-in search events:

- customer_id
- search_term
- searched_at
- store_id
- source
- completion_status
- completed_at
- response_time_ms
- results_count

### 2. Search lifecycle status

Statuses:

- started
- server_completed
- completion_not_recorded

Possible meaning:

A logged-in customer searched, but Magento did not record a completed search response.

This may indicate search speed, timeout, cancellation, search-page failure, or another search-friction issue.

### 3. Customer profile join

Join captured search events to:

- customer_entity
- customer_grid_flat

If Adobe Commerce B2B company tables exist later, optionally join to:

- company
- company_customer

### 4. Later order/cart matching

Cross-check search events against later cart/order activity.

Useful statuses:

- matching cart found
- matching order found
- possible related order found
- no matching order found
- zero results
- completion not recorded

### 5. Report

Initial report should answer:

Have logged-in customers searched for products but not reached a useful result?

Columns:

- customer
- email
- search term
- searched at
- completion status
- response time
- result count
- later cart/order match
- status

## Offshoot plan

After the current build is stable:

1. Commit the current Search Loss Audit work.
2. Create a new branch/offshoot.
3. Strip the offer down to Logged-in Search Intelligence.
4. Keep failed-search and weak-search reports as later optional modules.
5. Build a clean demo around logged-in customer search intent.

Suggested branch name:

logged-in-search-intelligence

## Commercial framing

This should be sold as a lightweight targeted offer first.

The client pain is search speed and search friction.

The strongest question is:

Have my customers been searching for products but not finding or buying them?

This is more direct than a broad failed-search audit.

## Future expansion

Once the logged-in search intelligence offer lands, add:

- failed-search analysis
- catalogue evidence
- GA4 low-engagement enrichment
- weak-search analysis
- Adobe Live Search readiness
- catalogue hygiene recommendations

But those should not lead the first targeted offer.
