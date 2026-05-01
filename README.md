# B2B Search Intelligence

B2B Search Intelligence is a focused Magento / Adobe Commerce module for logged-in customer search intent.

It shows what logged-in B2B customers searched for, whether Magento completed the search response, and whether the customer later added or ordered anything matching.

## The core question

Have our logged-in customers been searching for products but not reaching, adding, or buying them?

## Why this matters

B2B customers often search with clear buying intent.

They may search by:

- SKU
- part number
- product name
- category
- brand
- replacement part
- technical wording
- fitment or use-case wording

When a logged-in customer searches and does not continue to cart or order, that is a strong commercial signal for the merchant to review.

## What the module shows

The main report shows:

- customer
- email
- search term
- search started time
- search completed time
- server-side response time
- result count
- lifecycle status
- commercial follow-through
- matched cart or order item
- unresolved status

## Current dashboard summary

The dashboard currently includes:

- total logged-in searches
- unresolved searches
- completed searches
- completion not recorded
- matched cart items
- matched orders

## Lifecycle statuses

The module can identify:

- Completed
- Completed, zero results
- Completed slowly
- Search started, completion not recorded
- Needs review

## Follow-through statuses

The module can identify:

- Matching cart item found
- Matching order found
- No later matching cart/order found
- Needs review

## Example interpretation

Example 1:

A logged-in customer searched for leaf spring.

Magento completed the response.

Results were returned.

The customer later added a matching item to cart.

Status: matching cart item found.

Example 2:

A logged-in customer searched for leaf spring 123.

Magento received the search request.

No completed search response was recorded.

No later matching cart or order was found.

Status: unresolved logged-in search.

## What this is not

This focused module is not intended to be:

- a broad failed-search audit dashboard
- a GA4 weak-search analytics tool
- a catalogue evidence audit
- a replacement for Adobe Live Search, Algolia, Klevu, Searchspring, or similar search platforms
- a guaranteed lost-revenue calculator

## Current technical module path

Current path:

app/code/Scandiweb/SearchLoss

Visible product wording should use:

B2B Search Intelligence

The technical namespace can be renamed later once the focused product is stable.

## Current API endpoint

Current endpoint:

/rest/V1/search-loss/dashboard

Expected payload sections:

- summary
- loggedInSearchIntelligence

## Near-term roadmap

1. Keep the module focused on logged-in customer search intelligence.
2. Polish admin dashboard wording.
3. Add a simple export for unresolved searches.
4. Add Top Search Intelligence Actions based only on logged-in search events.
5. Add configuration for slow-search threshold.
6. Later, rename the technical module namespace.
