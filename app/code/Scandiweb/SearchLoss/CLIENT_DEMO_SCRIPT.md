# Search Loss Audit — Client Demo Script

## Goal of the demo

Show Search Loss Audit as a low-risk Magento search diagnosis.

The aim is not to claim search is fixed, or to promise exact lost revenue.

The aim is to show:

    These are the search issues worth reviewing first.
    Here is the Magento evidence.
    Here is the likely fix path.

## One-line opening

Search Loss Audit shows where Magento site search may be missing customer demand, what that demand may be worth directionally, and what to review first.

## 30-second positioning

Search has come up as an area of friction, and larger search changes are not always a simple plug-in decision.

Rather than jumping straight into a rebuild or a search platform decision, Search Loss Audit gives us a low-risk diagnostic step.

It uses Magento data the store already has, checks failed searches against catalogue evidence, and gives us a prioritised list of issues to review.

Where GA4 data is available, it can also enrich the audit with searches that returned results but did not lead to meaningful engagement.

## What it is

- Magento search audit
- missed-demand diagnosis
- read-only admin tool
- prioritised fix list
- evidence-led review layer

## What it is not

- not a search engine
- not an Adobe Live Search replacement
- not an Algolia/Klevu/Searchspring replacement
- not a guaranteed lost-revenue calculator
- not an automatic catalogue changer
- not an AI tool making unsupervised edits

## Demo flow

### 1. Start with the hero

Say:

This is a read-only Magento diagnostic. It does not change products, categories, orders, checkout, storefront search behaviour, or customer data.

Point out:

- Search Loss Audit title
- Read-only Magento diagnostic pill
- How to use this audit
- Configure audit

### 2. Open Audit snapshot

Explain the KPI cards:

- Failed Terms
- Failed Searches
- Est. Demand Value
- AOV
- Search-to-order rate

Say:

Est. Demand Value is directional. It helps prioritise review work; it is not a claim of guaranteed lost revenue.

### 3. Open Top money actions

Say:

Instead of giving a flat report, the audit groups findings by the type of work likely needed.

Examples:

- catalogue data fix
- attribute/search configuration fix
- synonym/search term fix
- catalogue coverage review
- manual review

Click one action card and show that it filters the findings table.

### 4. Open Prioritized failed-search findings

Explain:

This table is Phase 1. These are searches where Magento returned zero results.

Click a row.

Walk through:

1. Diagnosis
2. Recommended fix
3. Evidence

Say:

The important part is the evidence. The module checks whether Magento already has related products, SKUs, categories, visibility, assignment, stock, and searchable attribute signals.

### 5. Open Searches With Low Engagement

Explain:

This is Phase 2. These are searches where there was search activity or results, but customers did not meaningfully move forward.

Explain the columns:

- Searches
- Product engagement
- Add-to-cart rate
- Purchase rate
- Weakness signal
- Recommended review

Say:

This section depends on GA4 data. We only enable it where the GA4 probe proves the store exposes usable data.

### 6. Show healthy revenue signal carefully

If a row exists with Healthy revenue signal, say:

This is important because we do not want to mark every search as a problem. If a term is producing revenue, the audit treats it as a healthy signal rather than a loss item.

### 7. Show Configure audit

Open Configure audit.

Explain:

These settings let us adapt the audit to the client's catalogue and traffic level without changing code.

Mention:

- Product identifier attributes
- Ignored search terms
- Minimum failed searches
- GA4 low-engagement thresholds

Say:

A B2B store with a long buying cycle may need different thresholds from a high-traffic B2C store.

### 8. Show export

Show:

- Download current view
- Download all findings

Say:

This turns the audit into a working review list for search, catalogue, and merchandising tasks.

## Safe close

Use this close:

The next step would not be a big rebuild. The next step would be a fixed Search Loss Audit: run this against the store, review the top failed and weak search terms, validate the evidence, and agree the first batch of fixes worth doing.

## Fixed audit offer framing

Suggested offer:

Fixed Search Loss Audit

Includes:

- Search Loss setup or dashboard review
- failed-search analysis
- optional GA4 enrichment if available
- top missed-demand opportunities
- catalogue/search diagnosis
- prioritised fix list
- client review session or written summary

## Handling objections

### Is this replacing Adobe Live Search?

No.

Adobe Live Search improves search results. Search Loss Audit helps diagnose where search demand is being missed and what should be reviewed first.

It can be useful before, during, or after a search platform project.

### Is the revenue number exact?

No.

It is directional. It helps prioritise search issues by possible commercial importance.

### Will this change the storefront?

No.

The audit is read-only. It does not change storefront search, checkout, products, categories, orders, or customer data.

### What if GA4 data is not good enough?

Then Phase 2 stays disabled or treated as unavailable.

The core audit still works from Magento failed-search data.

### Can this show us what to fix?

Yes, as a prioritised review list.

It does not automatically fix anything, but it points the team toward likely Magento review areas such as catalogue data, synonyms, searchable attributes, SKU matching, product visibility, website/category assignment, stock, and search configuration.

## Best short pitch

Search Loss Audit helps move from:

    Search feels broken.

to:

    These are the specific failed searches, weak searches, catalogue gaps, and Magento configuration issues we should review first.

## Demo checklist

Before showing:

- Git state is clean
- page loads in Magento admin
- Configure audit opens
- Audit snapshot opens
- Top money actions filters work
- failed-search table opens
- failed-search rows expand
- Searches With Low Engagement opens
- export buttons work
- REST endpoint returns valid JSON
- no temporary SLTEST rows are present
- GA4 low-engagement section has either real rows or a clear explanation
