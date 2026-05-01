# B2B Search Intelligence Magento Module

This module captures logged-in customer search intent in Magento / Adobe Commerce.

It focuses on:

- logged-in customer search events
- search started and completed lifecycle
- server-side search response time
- result count where available
- customer account linkage
- later cart or order follow-through
- unresolved logged-in searches

## Admin location

Reports -> Business Intelligence -> B2B Search Intelligence

## API endpoint

/rest/V1/search-loss/dashboard

Expected payload sections:

- summary
- loggedInSearchIntelligence

## Current technical module path

app/code/Scandiweb/SearchLoss

## Current technical module name

Scandiweb_SearchLoss

## Product wording

Use the visible product name:

B2B Search Intelligence

Avoid leading with:

- Search Loss Audit
- failed-search dashboard
- weak-search analytics
- GA4 search analytics
- catalogue audit

## Current scope

This focused module intentionally does not lead with failed-search audits, GA4 weak-search reports, or broad catalogue diagnosis.

Those can remain part of the separate Search Loss Audit product later.

## Current proof

The focused API should return only:

- summary
- loggedInSearchIntelligence

If failedSearchTerms or lowEngagementSearchTerms return in the payload, the wrong codebase is being tested.
