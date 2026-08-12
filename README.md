# 4wp-seo

Внутрішній (непублічний) плагін. Назва плагіна 4wp, у коді/JS використовується forwp.

## Roadmap (чернетка)

### v0.1 — Schema.org (TechArticle)
- Одна адмін-сторінка налаштувань (без UI/UX надбудов).
- Сайдбар у редакторі посту: статус наявності Code blocks + Steps.
- Тип сторінки: TechArticle (перемикач на пості).
- Умова TechArticle:
  - є мінімум один core Code block;
  - є TechArticle Steps (кастомні блоки).
- JSON-LD виводимо як TechArticle в загальному контенті.
- Якщо активний `4wp-advanced-code`:
  - використовуємо зібрані `softwareCode`;
  - перехоплюємо стандартний JSON-LD від `4wp-advanced-code` і формуємо єдиний TechArticle.
- Автоматично додаємо `about` (контекст).

### v0.2 — Google Search Console (мінімум)
- OAuth 2.0 конект.
- Список properties → вибір 1 → прив'язка до сайту (без мультисайтів).
- URL inspection (один URL):
  - Index status, Coverage state, Last crawl, Canonical (user/google), Robots state.
- Search Analytics (page filter, last 28 days):
  - clicks, impressions, CTR, avg position.

### v0.3 — LLMS.txt
- Автогенерація `llms.txt` лише якщо TechArticle валідний.

### v0.4 — Cross posting (module)
- Модуль у 4wp-seo з можливістю вмикати.
- Список платформ у sidebar редактора (не dropdown).
- Генерація тексту на льоту:
  - dev.to, Medium: Markdown.
  - LinkedIn: заголовок + текст (ліміт 400 символів).
  - X, Bluesky: короткий текст з лімітом платформи.

## Модулі
- Schema.org
- LLMS.txt
- Meta Tags (майбутнє)
- Sitemap (майбутнє)
