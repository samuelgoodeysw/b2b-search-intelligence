# B2B Search Intelligence

B2B Search Intelligence is a focused Magento / Adobe Commerce module for logged-in customer search intent.

It helps answer:

- Which logged-in customers searched?
- What did they search for?
- Did Magento complete the search response?
- How long did the search response take?
- How many results were returned?
- Did the customer later add or order something matching?
- Which searches look unresolved?

## Core idea

B2B customers often search with clear buying intent:

- SKU
- part number
- product name
- category
- brand
- replacement part
- technical wording
- fitment or use-case wording

This module focuses on customer-level search intent, not broad store-level keyword analytics.

## Current feature scope

- Captures logged-in customer searches.
- Records when the search started.
- Records when Magento completed the search response.
- Stores server-side response time.
- Stores result count where available.
- Joins search events to Magento customer accounts.
- Checks later cart/order follow-through.
- Flags unresolved logged-in searches.

## Current Magento module path

Current path:

app/code/Scandiweb/SearchLoss

The code was split from the broader Search Loss Audit project.

The next cleanup step is to strip out failed-search, weak-search, GA4, and catalogue-audit features so this repo becomes a focused B2B Search Intelligence module.

## Intended product positioning

B2B Search Intelligence shows what logged-in customers searched for, whether Magento completed the search response, and whether they later added or ordered anything matching.

It is designed as a lightweight, focused revenue-intent module for B2B merchants.

## What this is not

This focused module is not intended to be:

- a broad failed-search audit dashboard
- a GA4 weak-search analytics tool
- a catalogue evidence audit
- a replacement for Adobe Live Search, Algolia, Klevu, or Searchspring
- a guaranteed lost-revenue calculator

## Hero report

The main report should show:

- Customer
- Email
- Search term
- Search started
- Search completed
- Response time
- Result count
- Lifecycle status
- Commercial follow-through
- Matched cart/order item
- Unresolved status

## Example interpretation

Example 1:

Customer searched for leaf spring.
Magento completed the response.
Results were returned.
The customer later added a matching item to cart.
Status: matching cart item found.

Example 2:

Customer searched for leaf spring 123.
Magento received the search request.
No completed search response was recorded.
No later matching cart or order was found.
Status: unresolved logged-in search.

## Near-term cleanup plan

1. Keep logged-in search lifecycle tracking.
2. Keep customer account joining.
3. Keep cart/order follow-through matching.
4. Remove failed-search dashboard sections.
5. Remove weak-search and GA4 sections.
6. Remove broad catalogue-audit evidence.
7. Rename visible product wording to B2B Search Intelligence.
8. Later, rename the technical Magento module once the focused product is stable.
