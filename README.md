# 4wp-seo-helper

Внутрішній плагін 4wp.dev. Namespace: `Forwp\SeoHelper`.

## Модулі

### TechArticle (Schema.org)
- Gutenberg wrappers: goal, context, steps, issues, **completion**
- JSON-LD TechArticle на фронті
- Інтеграція з `4wp-advanced-code` (softwareCode у hasPart)
- Markup builder для імпорту контенту (`TechArticleMarkup`)

### SEO Inventory (v0.4+)
Translation-aware SEO ops шар поверх Yoast / AIOSEO.

- **Adapters:** Yoast, All in One SEO, fallback
- **Multilingual:** Polylang, WPML, single-language
- **CPT:** auto-discovery (`public` + `show_ui`), без hardcode
- **REST:** `forwp-seo-helper/v1/seo-inventory/*`
- **Admin:** 4wp SEO → SEO Inventory (table, filters, CSV export)
- **Sheets:** `docs/google-sheets-sync.gs`

Контракт API: `GET /wp-json/forwp-seo-helper/v1/seo-inventory/meta`

Auth: `Authorization: Bearer <token>` (4wp SEO → Settings) або `manage_options`.

### Google Search Console
OAuth, URL inspection, search analytics (28 days).

### LLMS.txt
`/llms.txt` для постів з валідним TechArticle.

### Cross posting
Markdown / social snippets з редактора (module toggle).

## Фільтри

```php
// Виключити CPT з inventory
add_filter( 'forwp_seo_inventory_exclude_post_types', fn( $types ) => array_merge( $types, [ 'shop_order' ] ) );

// CORS для зовнішніх клієнтів (не dashboard — окремий крок)
add_filter( 'forwp_seo_inventory_cors_origins', fn() => [ 'https://script.google.com' ] );
```

## Поза scope v0.5

- `4wp-analytics-dashboard` UI — окремий репозиторій, не чіпаємо
- Rename block names `forwp-seo/*` → `forwp-seo-helper/*` (breaking для post_content)
