---
name: prevent-search-indexing
description: Guidelines, checklist, and procedures for strictly preventing search engine robots (Google, Yandex, Bing) from crawling, indexing, caching, or snippet-rendering any CRM pages, views, documents, and static assets.
---

# Search Engine Anti-Indexing (Strict Noindex)

This project is a private CRM and Telegram Mini App system. Under no circumstances should any page, Blade view, API response, or static asset be indexed, cached, or snippeted by search engine crawlers.

## When to Use This Skill

Activate this skill when:
- Creating or editing any HTML/Blade view, layout, or standalone page.
- Adding or modifying HTTP routes or API endpoints.
- Configuring web servers (Nginx) or static file delivery.
- Verifying search robot blocking compliance.

## Three-Layer Anti-Indexing Architecture

Every page or asset must be protected across all 3 layers:

### 1. HTML Meta Tag Layer (`<meta name="robots">`)
Every HTML document or Blade layout containing a `<head>` tag must include:
```html
<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
```
- Views extending `tma.layout` or `errors.layout` inherit this automatically.
- Standalone views (auth, error pages, redirect views, external link pages, documentation) must explicitly declare this tag in `<head>`.

### 2. HTTP Header Layer (`X-Robots-Tag`)
All HTTP responses from the server must include:
```http
X-Robots-Tag: noindex, nofollow, noarchive, nosnippet
```
- **Laravel Middleware**: Managed globally by `App\Http\Middleware\PreventSearchIndexing` (registered via `$middleware->append(...)` in `bootstrap/app.php`). Applies to all web routes, API endpoints, JSON responses, redirects, and error responses.
- **Nginx Web Server**:
  - Top-level `server` block must have:
    ```nginx
    add_header X-Robots-Tag "noindex, nofollow, noarchive, nosnippet" always;
    ```
  - Custom location blocks (such as `location ^~ /build/assets/` and `location ~* \.(js|css|...)$`) must explicitly re-declare `add_header X-Robots-Tag ... always;` because Nginx drops parent `add_header` directives when a block defines its own.
  - The FastCGI block (`location ~ \.php$`) must include `fastcgi_hide_header X-Robots-Tag;` to prevent duplicate headers when Laravel also sends it.

### 3. Protocol Layer (`robots.txt`)
- The file `public/robots.txt` must always contain:
  ```txt
  User-agent: *
  Disallow: /
  ```
- The route `/robots.txt` in `routes/web.php` must act as an application-level fallback returning the same content with `Content-Type: text/plain` and `X-Robots-Tag`.

## Verification Checklist

Before finishing any task that introduces new pages or endpoints:
1. Verify the layout or view has `<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">`.
2. Run automated SEO indexing tests:
   ```bash
   docker exec crm_app php artisan test --filter=PreventSearchIndexingTest
   ```
3. Test response headers via curl (ensure exactly 1 `X-Robots-Tag` header):
   ```bash
   curl -s -D - http://127.0.0.1:8080/<url-path> -o /dev/null | grep -i "X-Robots-Tag"
   ```
