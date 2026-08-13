/**
 * Google Sheets ↔ 4wp-seo-helper SEO inventory sync.
 *
 * Setup:
 * 1. Extensions → Apps Script in your spreadsheet.
 * 2. Paste this file, set SITE_URL and API_TOKEN from WP admin → 4wp SEO.
 * 3. Run pullInventoryToSheet once (authorize), then use pushBulkFromSheet.
 */

const SITE_URL = 'https://your-site.example';
const API_TOKEN = 'paste-token-from-wp-admin';

const API_BASE = SITE_URL.replace(/\/$/, '') + '/wp-json/forwp-seo-helper/v1/seo-inventory';

function apiHeaders_() {
  return {
    Authorization: 'Bearer ' + API_TOKEN,
    'Content-Type': 'application/json',
  };
}

function pullInventoryMeta() {
  const response = UrlFetchApp.fetch(API_BASE + '/meta', {
    headers: apiHeaders_(),
    muteHttpExceptions: true,
  });
  Logger.log(response.getContentText());
}

function pullInventoryToSheet() {
  const params = {
    lang: 'uk',
    missing: 'any',
    per_page: 200,
  };
  const query = Object.keys(params)
    .map(function (key) {
      return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
    })
    .join('&');

  const response = UrlFetchApp.fetch(API_BASE + '/export?' + query, {
    headers: apiHeaders_(),
    muteHttpExceptions: true,
  });

  if (response.getResponseCode() !== 200) {
    throw new Error('Export failed: ' + response.getContentText());
  }

  const payload = JSON.parse(response.getContentText());
  const sheet = SpreadsheetApp.getActiveSheet();
  sheet.clearContents();

  const headers = [
    'post_id',
    'lang',
    'post_type',
    'wp_title',
    'url',
    'seo_title',
    'meta_description',
    'focus_keyword',
    'priority',
    'queue_position',
    'completeness',
    'missing',
  ];

  sheet.getRange(1, 1, 1, headers.length).setValues([headers]);

  const rows = (payload.items || []).map(function (item) {
    return [
      item.post_id,
      item.lang,
      item.post_type,
      item.wp_title,
      item.url,
      item.seo_title,
      item.meta_description,
      item.focus_keyword,
      item.priority || '',
      item.queue_position === 0 || item.queue_position ? item.queue_position : '',
      item.completeness,
      (item.missing || []).join('|'),
    ];
  });

  if (rows.length) {
    sheet.getRange(2, 1, rows.length, headers.length).setValues(rows);
  }
}

function pushBulkFromSheet() {
  const sheet = SpreadsheetApp.getActiveSheet();
  const data = sheet.getDataRange().getValues();
  const headers = data.shift();
  const index = function (name) {
    return headers.indexOf(name);
  };

  const items = [];
  data.forEach(function (row) {
    const postId = row[index('post_id')];
    if (!postId) {
      return;
    }

    items.push({
      post_id: Number(postId),
      fields: {
        seo_title: String(row[index('seo_title')] || ''),
        meta_description: String(row[index('meta_description')] || ''),
        focus_keyword: String(row[index('focus_keyword')] || ''),
      },
    });
  });

  const response = UrlFetchApp.fetch(API_BASE + '/bulk', {
    method: 'post',
    headers: apiHeaders_(),
    payload: JSON.stringify({ items: items }),
    muteHttpExceptions: true,
  });

  Logger.log(response.getContentText());
}
