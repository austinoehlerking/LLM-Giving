# Google Analytics and Search Console

## Configuration

The existing GA4 property uses a public measurement ID. GOOGLE_ANALYTICS_ID can override it; an empty override disables collection. GOOGLE_ANALYTICS_ENABLED defaults to 1 in production and 0 elsewhere. A value of 0 disables Analytics globally. No Google API secret is stored.

Keep enhanced measurement disabled for automatic form, site-search, outbound-link and history-change events. Google signals and ad personalization are disabled in the application.

Search Console uses the static home-page ownership tag. GOOGLE_SITE_VERIFICATION can override the public token; an empty override disables the tag. Keep the verification tag when deploying subsequent changes. The sitemap at /sitemap.xml includes fixed public pages and published editorial articles, without private records. Sitemap discovery does not establish indexing.

## Runtime behavior

On successful allowlisted public GET pages, src/google.php emits a local loader that starts Analytics automatically. There are no visitor opt-in controls or analytics-choice panels. The asset URL includes a version so browsers replace the former consent-panel loader after deployment.

Do Not Track and Global Privacy Control suppress collection and clear this site's Analytics cookies. A previously stored llmgiving.analytics.v1=denied preference also remains honored; the new loader does not write a new choice to localStorage. Browser storage failures do not prevent ordinary public-page startup.

Private/admin/management pages, submission/recovery forms, receipts, API/context/file routes, POST responses and errors omit Analytics. Public pages viewed from an admin or requester management session also omit it. URLs exclude query strings and fragments; referrers are blank. CSP permits only the specific Google script and collection origins on eligible requests. Local development defaults to no collection.

The privacy page appends content/analytics-privacy.txt after its existing policy body, including saved page_privacy overrides. Historical accepted policy records remain preserved.

## Verification

Run node tools/dev.mjs setup, node tools/check.mjs, node tools/qa.mjs viewport, node tools/qa.mjs, and node tools/qa.mjs google.

The Google browser mode supplies synthetic identifiers and intercepts Google requests before the first navigation. It verifies automatic startup without an opt-in UI, removed illustrative text, sanitized URLs/referrers, advertising restrictions, private-route exclusions, browser privacy signals, previous opt-outs, cookie cleanup, and the privacy disclosure. It also checks initial viewport actions at 1366x768, 390x844 and 375x667, plus enlarged-text reflow. No real property events are recorded by these local tests.

After separately authorized deployment, verify the static ownership tag, local loader version, CSP, automatic Analytics startup, private-route exclusions, browser privacy signals, and the privacy disclosure. Check Google account integration state directly without republishing credentials or private setup history. Keep deployment evidence and runtime data under ignored storage/.

Google references: [GA4 configuration](https://developers.google.com/analytics/devguides/collection/ga4/reference/config), [Search Console ownership verification](https://support.google.com/webmasters/answer/9008080?hl=en).