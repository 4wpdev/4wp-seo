=== 4WP SEO Helper ===
Contributors: 4wpdev, anatolikkk
Tags: seo, inventory, yoast, meta description, audit
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Audit SEO titles and meta site-wide, set P1-P3 priorities, export CSV, and sync via REST. Works with Yoast SEO and All in One SEO.

== Description ==

**4WP SEO Helper** gives you a **site-wide SEO Inventory** for WordPress: see missing titles and meta descriptions, score completeness, and mark business priority with drag-and-drop **P1 / P2 / P3** lanes.

Built for teams that use **Yoast SEO** or **All in One SEO** and need one place to audit SEO fields across posts, pages, and public custom post types.

A plugin by [4wp.dev](https://4wp.dev/).

= Perfect for =

* **SEO audits** — filter by missing title, meta description, or focus keyword
* **Content ops** — drag rows into P1–P3 lanes (business priority, not SEO score)
* **Bulk workflows** — quick edit SEO fields from the inventory table
* **External sync** — REST API for Google Sheets or custom dashboards
* **Multilingual sites** — Polylang, WPML, or single-language

= How it works =

1. Activate the plugin (Yoast or All in One SEO recommended for read/write).
2. Open **4WP SEO → SEO Inventory**.
3. Review completeness scores and missing SEO fields site-wide.
4. Drag rows into **P1 / P2 / P3** to mark business priority.
5. Optional: enable the REST API under **Settings** and connect Sheets or your dashboard.

Priority reflects **business importance** (e.g. main service pages), not SEO score—a page can stay in P1 at 100% completeness.

= Modules =

**SEO Inventory**, **Inventory REST API**, and **Google Search Console** (OAuth, property picker, URL inspection, search analytics) are available in wp-admin. TechArticle schema, LLMS.txt, and cross posting appear as **Coming soon** until a future release.

== Installation ==

1. Upload the plugin folder to `wp-content/plugins/` or install through **Plugins → Add New**.
2. Activate **4WP SEO Helper** through the Plugins screen.
3. Install and activate **Yoast SEO** or **All in One SEO** for full inventory read/write.
4. Open **4WP SEO → SEO Inventory** to start auditing.

On activation, the inventory API token is created and the module is enabled by default.

== Frequently Asked Questions ==

= Do I need Yoast SEO or All in One SEO? =

**Recommended.** The inventory reads and writes SEO meta through adapter plugins. Without Yoast or AIOSEO, the table still loads but bulk updates and meta writes are limited.

= What is P1 / P2 / P3 priority? =

Three configurable business tiers in the inventory table. **Priority is not SEO score**—you might keep a fully complete page in P1 because it is a key landing page.

= Can I export the inventory? =

Yes. Use **Export CSV** on the SEO Inventory screen to download titles, meta fields, completeness, priority, and missing-field flags.

= Is there a REST API? =

Yes. Base URL: `/wp-json/forwp-seo-helper/v1/seo-inventory`

Routes include `GET /meta`, `GET /items`, and `PUT /priority-queue`. Authenticate with a Bearer token from **4WP SEO → Inventory API** (Settings tab), or as a logged-in administrator with `manage_options`.

= Does it work with Polylang or WPML? =

Yes. Language filters and per-language rows are supported when a multilingual plugin is active.

= Which post types are included? =

Public post types with `show_ui` are discovered automatically. Exclude types with the `forwp_seo_inventory_exclude_post_types` filter.

= What modules are coming soon? =

TechArticle schema and blocks, LLMS.txt, and cross posting are in the codebase but hidden behind **Coming soon** badges until a future release.

= How do I connect Google Search Console? =

Open **4WP SEO → Search Console**. Add OAuth Client ID and Secret from Google Cloud Console, use the redirect URI shown on that screen, then connect and select your property.

= Does it depend on other 4WP plugins? =

No. **4WP SEO Helper** runs on its own. The REST API can integrate with external tools such as Google Sheets or the 4WP Analytics Dashboard.

= Is this plugin affiliated with Yoast or WordPress? =

No. Yoast SEO and All in One SEO are separate products. **4WP** is our project brand name—**WP** appears only as part of that name and is not a reference to WordPress. This plugin is not affiliated with, endorsed, or sponsored by WordPress.

== External services ==

This plugin optionally connects to **Google APIs** when a site administrator enables **Google Search Console** integration under **4WP SEO → Settings**.

**Google OAuth 2.0** (`accounts.google.com`, `oauth2.googleapis.com`)

Used so an administrator can connect their Google account to WordPress. When the admin clicks **Connect Google**, the plugin redirects to Google sign-in and exchanges an authorization code for access and refresh tokens. Tokens are stored in the WordPress database and used only for Search Console features initiated in wp-admin.

**Google Search Console API** (`searchconsole.googleapis.com`)

Used to list verified properties, inspect URLs, and fetch Search Analytics for the connected property. The plugin sends the selected property URL, requested page URLs, and date ranges when an administrator runs sync, URL inspection, or live analytics tools in wp-admin. Synced metrics are stored locally in WordPress for reporting screens.

No Google API calls are made until an administrator configures OAuth credentials and connects an account. The plugin does not send site visitor or front-end user data to Google.

* [Google Terms of Service](https://policies.google.com/terms)
* [Google Privacy Policy](https://policies.google.com/privacy)
* [Google API Services User Data Policy](https://developers.google.com/terms/api-services-user-data-policy)

== Screenshots ==

1. SEO Inventory table with completeness scores and P1–P3 priority lanes
2. Missing-field filters and quick edit for SEO title and meta description
3. Settings — inventory API toggle and configurable priority tier names
4. Inventory API connection details and sync token

== Changelog ==

= 1.0.1 =
* Plugin Review fixes: enqueue inventory admin CSS, safe JSON-LD output, block render escaping, Google API disclosure in readme.

= 1.0.0 =
* Initial WordPress.org release: **SEO Inventory** admin table with filters, CSV export, and quick edit.
* **P1 / P2 / P3** drag-and-drop priority queue with configurable tier names.
* SEO adapters for **Yoast SEO** and **All in One SEO**.
* Multilingual support: Polylang, WPML, single-language.
* **REST API** (`forwp-seo-helper/v1/seo-inventory`) for external sync.
* **Google Search Console** — OAuth, property picker, URL inspection, 28-day search metrics.
* Future modules (TechArticle, LLMS.txt, cross posting) visible as Coming soon in admin.

== Upgrade Notice ==

= 1.0.1 =
Plugin Review compliance fixes for CSS enqueue, JSON-LD escaping, and external services documentation.

= 1.0.0 =
Initial release — SEO Inventory and REST API for Yoast and All in One SEO sites.
