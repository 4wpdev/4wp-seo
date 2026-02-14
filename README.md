# 4WP SEO

Internal SEO plugin with Schema.org, Google Search Console, and LLMS.txt modules.

**Version:** 0.1.0

## Overview

4WP SEO is an internal (non-public) WordPress plugin designed to enhance technical content SEO through structured data, search console integration, and LLM-friendly content generation. The plugin name is 4wp, while the code/JS uses the `forwp` namespace.

## Features

### ✨ Schema.org (TechArticle) ✅
- Admin settings page (minimal UI/UX)
- Editor sidebar: status indicator for Code blocks + Steps
- Post type: TechArticle (toggle on post)
- TechArticle requirements:
  - Minimum one core Code block
  - TechArticle Steps (custom blocks)
- JSON-LD output as TechArticle in content
- Integration with `4wp-advanced-code`:
  - Uses collected `softwareCode`
  - Intercepts standard JSON-LD from `4wp-advanced-code` and forms unified TechArticle
- Automatically adds `about` (context)

### 🍳 Schema.org (Recipe) 🚧
- Practical use case for culinary content
- Automatic Recipe detection from blocks:
  - Ingredients (ingredients)
  - Instructions (instructions)
  - Cooking time (prepTime, cookTime, totalTime)
  - Serving size (recipeYield)
  - Calories and nutrition (nutrition)
- Rich snippets for search engines
- Editor integration via custom blocks

### 📋 Schema.org (Other Popular Types)
- **Article / BlogPosting** — for blog posts and articles
- **FAQPage** — for FAQ pages
- **HowTo** — for step-by-step instructions
- **Organization** — for organization information
- **Person** — for author profiles
- **Product** — for products and services
- **Review** — for reviews and ratings
- **VideoObject** — for video content
- **BreadcrumbList** — for navigation breadcrumbs

### 🔍 Google Search Console
- OAuth 2.0 connection
- Properties list → select one → site binding (no multisite support)
- URL inspection (single URL):
  - Index status, Coverage state, Last crawl, Canonical (user/google), Robots state
- Search Analytics (page filter, last 28 days):
  - Clicks, impressions, CTR, average position

### 📄 LLMS.txt
- Auto-generation of `llms.txt` only if TechArticle is valid

### 🔗 Cross Posting (Module)
- Module in 4wp-seo with enable/disable option
- Platform list in editor sidebar (not dropdown)
- On-the-fly text generation:
  - dev.to, Medium: Markdown
  - LinkedIn: title + text (400 character limit)
  - X, Bluesky: short text with platform limit

## Modules

- **Schema.org** - TechArticle, Recipe, and other structured data types
- **LLMS.txt** - LLM-friendly content generation
- **Meta Tags** - (Future)
- **Sitemap** - (Future)

## Requirements

- WordPress 5.0+
- PHP 8.0+
- Optional: `4wp-advanced-code` plugin for enhanced code block integration

## Installation

1. Place the `4wp-seo` folder in your `wp-content/plugins/` directory
2. Activate the plugin through the WordPress admin panel
3. Configure settings in WordPress Admin → 4WP SEO

## Roadmap

### v0.1 ✅ (Current)
- Schema.org (TechArticle)
- Basic admin interface
- Editor sidebar integration

### v0.1.1 (Planned)
- Schema.org (Recipe)
- Culinary content support
- Rich snippets for recipes

### v0.1.2 (Planned)
- Schema.org (Other popular types)
- Article, FAQPage, HowTo, Organization, Person, Product, Review, VideoObject, BreadcrumbList

### v0.2 (Planned)
- Google Search Console integration
- OAuth 2.0 authentication
- URL inspection and Search Analytics

### v0.3 (Planned)
- LLMS.txt auto-generation

### v0.4 (Planned)
- Cross posting module
- Platform-specific formatting

## Documentation

Full documentation and usage guide: [https://4wp.dev/plugin/4wp-seo/](https://4wp.dev/plugin/4wp-seo/)

## Developer

- Plugin page: [https://4wp.dev/plugin/4wp-seo/](https://4wp.dev/plugin/4wp-seo/)
- Author: [4wp.dev](https://4wp.dev)

## License

GPL-2.0-or-later
