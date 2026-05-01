# Search & Discovery Audit

## Positioning

Search & Discovery Audit is a paid diagnostic engagement for Magento / Adobe Commerce stores.

It uses Search Loss Audit as the internal analysis engine, but the first client-facing deliverable is not a production module install.

The first deliverable is an audit report and walkthrough.

## Core idea

Instead of asking the client to install a new module before the value is proven, we run the analysis against a sanitized database dump or approved data export.

The client gets the insight first.

The module comes later only if they want ongoing visibility and the code has been hardened, reviewed, and approved for their environment.

## One-line pitch

Search & Discovery Audit shows where Magento search and catalogue structure may be blocking product discovery, then turns the evidence into a prioritised fix plan before any search-platform or production-code decision.

## Why audit first

Audit first gives the client:

- insight without production install risk
- a billable engagement instead of a free module demo
- evidence from real client data before any module proposal
- a natural bridge into catalogue hygiene, synonym setup, layered navigation, and Adobe Live Search readiness
- lower governance, IP, and support risk while the module is still maturing

## Recommended first move

Use a sanitized DB dump or approved export.

Run the Search Loss analysis internally.

Generate a one-off report.

Walk through the findings with the client.

Use the evidence to scope follow-on work.

## What we need from the client

Preferred input:

- sanitized Magento database dump
- or approved extracts of relevant Magento tables
- optional GA4 export or access if Phase 2 enrichment is in scope
- current search/platform context
- known search complaints or priority product areas
- catalogue/search goals

Useful Magento data includes:

- search_query
- sales_order summary data
- product catalogue data
- product attributes
- category data
- website/category assignment
- product status and visibility
- stock or salability data where available
- searchable attribute configuration

## What we deliver

The audit deliverable should include:

- executive summary
- top failed-search opportunities
- estimated demand value
- catalogue evidence
- likely root causes
- recommended Magento review/fix steps
- search configuration opportunities
- synonym/search-term opportunities
- product data and attribute issues
- visibility, stock, website, and category assignment findings
- optional GA4 low-engagement findings where data is available
- Adobe Live Search / search-platform readiness notes
- prioritised follow-on roadmap

## Suggested report sections

### 1. Executive summary

Plain-English summary of the biggest search and discovery issues found.

### 2. Search demand overview

High-level view of failed searches, repeated terms, estimated demand value, and major themes.

### 3. Top failed-search findings

Prioritised list of searches where Magento returned zero results.

Each finding should include:

- search term
- search count
- estimated demand value
- diagnosis
- supporting catalogue evidence
- recommended review/fix

### 4. Catalogue evidence

Show whether Magento already has related:

- products
- SKUs
- categories
- product names
- identity attributes
- searchable attributes
- assigned products
- visible products
- in-stock products

### 5. Search configuration and synonym opportunities

Highlight issues that may be improved through:

- Magento search term setup
- synonyms
- searchable attribute configuration
- SKU / MPN / part-number fields
- product naming
- category naming

### 6. Low-engagement search findings

Optional.

Only include this section if GA4 or another search analytics source provides usable data.

Use careful wording:

- low-engagement signal
- diagnostic only
- review before action
- based on available tracking
- not guaranteed lost revenue

### 7. Adobe Live Search / layered navigation readiness

Use the audit to show what should be cleaned up before or alongside Adobe Live Search work.

This can include:

- layered navigation structure
- searchable/filterable attributes
- product data quality
- synonym strategy
- category structure
- catalogue hygiene
- fitment / compatibility data
- product identity fields

### 8. Recommended roadmap

Split actions into sensible groups:

- quick admin/config fixes
- catalogue data cleanup
- synonym/search-term setup
- attribute/search configuration
- layered navigation / discovery refactor
- Adobe Live Search readiness
- optional ongoing dashboard/module install

## Future Phase 3: Abandoned Search Opportunities

True search abandonment is a strong future extension, especially where search speed is already a known pain.

Do not build this first.

Keep it as a follow-on if the audit gets traction.

Possible future definition:

Customer searched or started a search, then did not continue to product view, cart, quote, order, or other meaningful next step.

A stronger true-abandonment version would need extra evidence such as:

- search start timestamp
- search response completed timestamp
- page unload or navigation-away event
- search latency
- timeout or error data
- session identity
- logged-in customer/account identity

Without that instrumentation, use careful wording such as:

- possible search abandonment
- search session ended without engagement
- searched but did not continue to product, cart, quote, or order
- abandonment data opportunity

Do not claim the customer left before search completed unless the instrumentation proves it.

## Future abandonment data output

For B2B stores, unresolved or abandoned searches can become a useful data set for the client to review.

The audit should stay data-led and avoid prescribing account outreach.

Possible future output:

- search term
- search timestamp or date range
- result status
- engagement status
- search count
- affected account count where available and approved
- likely product/category area
- suggested review area

This should be positioned as data the client can interpret and act on themselves.

Safer wording:

Search abandonment data can highlight where users appear to stop after searching, but the client decides what action, if any, to take.

## How this leads to follow-on work

The audit can naturally lead into:

- catalogue hygiene fixes
- product data cleanup
- synonym/search-term setup
- attribute/search configuration
- layered navigation refactor
- Adobe Live Search readiness work
- Adobe Live Search implementation or optimisation
- GA4/search tracking improvement
- search latency and abandonment instrumentation
- abandonment and unresolved-search reporting
- ongoing Search Loss dashboard installation

## When to propose the module

Do not lead with installing the module.

Propose the module later if:

- the audit lands well
- the client wants ongoing visibility
- stakeholders trust the findings
- code has been hardened and reviewed
- ownership/support model is clear
- the module has been moved into the right organisation/repo if required
- the client accepts the governance path

## What is out of scope for the first audit

The first audit should not promise:

- production module install
- automatic catalogue changes
- exact lost revenue
- guaranteed revenue recovery
- search-platform replacement
- Adobe Live Search implementation
- full layered navigation rebuild
- long-term monitoring dashboard
- AI-generated automatic fixes
- true search-abandonment proof without instrumentation
- account-specific action recommendations unless explicitly requested

Those can become follow-on work after the audit.

## Safer client wording

Use:

- Search & Discovery Audit
- search diagnosis
- discovery friction
- missed search demand
- estimated demand value
- prioritised review list
- evidence-led recommendations
- possible search abandonment
- abandonment data opportunity

Avoid:

- guaranteed lost revenue
- exact revenue recovery
- plug-and-play search fix
- production-ready extension
- install this module now
- customer definitely abandoned because search was slow
- customer definitely failed to buy because of search

## Best close

The next step is a fixed Search & Discovery Audit.

We run the analysis against a sanitized data set, produce a prioritised findings report, and walk the team through what should be reviewed first.

If the value is clear, we can then scope the next piece of work: catalogue hygiene, synonym setup, layered navigation, Adobe Live Search readiness, search speed/abandonment instrumentation, or an ongoing Search Loss dashboard.
