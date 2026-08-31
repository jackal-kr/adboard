# Ad Board — Functional & Technical Specification

**Component** `com_adboard` · **Package** `pkg_adboard` · **Version** 1.5.26
**Platform** Joomla 6.1 / PHP 8.2+ · **Database** MySQL 8.0+ / utf8mb4 · **Date** 2026-05-30

> Authoritative behaviour reference. When code and this document disagree, treat it
> as a bug in one of them and reconcile — update this file as part of the change
> (see CLAUDE.md → Definition of done).

---

## 1. Purpose & Scope
A moderated classifieds component for Joomla 6.1, built for the S.O.D. Dojazdów
garden-plot association. Any site visitor can post an advertisement (plot for sale,
plant exchange, tools, services) **without a Joomla account**. All submissions enter
a moderation queue and are published only after an administrator or moderator approves.

**Design goals:** zero-account submission · moderation-first (nothing public until
approved) · multi-layer security (honeypot, IP rate limit, CAPTCHA, GD re-encode, CSRF)
· Joomla-native (ACL, action logs, Smart Search, Web Asset Manager) · minimal footprint
(one component + one optional finder plugin, no external dependencies).

**Out of scope / backlog:** self-service ad management via secret token · submitter
approval/rejection email · GDPR consent checkbox · "sold/resolved" state · plot-number field.

## 2. Package Contents
`pkg_adboard_v1_5_26.zip` installs, in one operation:

| File | Type | Description |
|---|---|---|
| `com_adboard.zip` | Component | Admin panel, site views, media assets |
| `plg_finder_adboard.zip` | Plugin (finder) | Smart Search adapter — indexes published ads |

## 3. File Structure
See `CLAUDE.md → Repository layout`. Key roles: `script.php` (idempotent installer —
table, category/expiry seed, ACL defaults, action-log config), `sql/install.mysql.sql`
(fresh DDL), `admin/sql/updates/mysql/` (per-version migrations), `site/src/Service/Router.php`
(SEF router), `media/adboard/` (CSS/JS + upload dir), `joomla.asset.json` (Web Asset manifest).

## 4. Database Schema
One table: `#__adboard`.

| Column | Type | Default | Description |
|---|---|---|---|
| `id` | INT(11) PK AI | — | Primary key |
| `title` | VARCHAR(255) | `''` | Ad headline (required) |
| `category` | VARCHAR(50) | `''` | Category slug (from Options → Categories) |
| `description` | MEDIUMTEXT | NULL | Free-text body |
| `contact` | VARCHAR(255) | NULL | Phone / email / other |
| `images` | VARCHAR(2000) | NULL | JSON array of filenames in `media/com_adboard/ads/` |
| `state` | TINYINT(3) | 0 | 0=Pending, 1=Published, 2=Expired, −1=Rejected, −2=Trashed |
| `created` | DATETIME | — | UTC submission timestamp |
| `publish_up` | DATETIME | NULL | Set to NOW() on first approval |
| `publish_down` | DATETIME | NULL | Expiry — from selected term at submission |
| `hits` | INT UNSIGNED | 0 | View counter |
| `ip_address` | VARCHAR(45) | NULL | Spam tracking only — never displayed |

**Indexes:** PRIMARY(`id`), `idx_state`, `idx_category`, `idx_created` (default newest-first sort).

**Lazy expiry flip:** no cron. `AdsModel::getListQuery()` runs one `UPDATE` flipping
state `1 → 2` where `publish_down < NOW()`.

## 5. Functional Specification

**5.1 Public submission form** (`/component/adboard?view=form` or a menu item).
Fields: Title (req), Category (req, drop-down → slug), Description (opt, HTML stripped),
Contact (req), Images (opt, up to `max_images`=7, JPG/PNG/WebP/GIF, drag-to-reorder),
Active-for (req, sets `publish_down`), CAPTCHA (only if a CAPTCHA plugin is configured),
Honeypot (hidden; non-empty → silently discarded).

**5.2 Public listing** — state=1 and `publish_down > NOW()`; category filter + free-text
search; sort Newest/Oldest/Title; per-page configurable; cards show cover image, title,
category badge, expiry, hits; all filter/sort changes are Itemid-aware in the URL.

**5.3 Public detail** — full view with scrollable gallery; lightbox (arrow keys, Esc/×,
swipe); hit counter incremented per load.

**5.4 Admin list** — paginated table with state badge, title, category, IP, dates, hits;
pending-count badge; "View on site" on published; bulk Approve/Unpublish/Reject/Delete;
bulk Approve skips ads whose `publish_down` is already past (with a warning).

**5.5 Admin edit** — Title, Category, Description, Contact, Images, Expiry, State; sidebar
has Status, Publish Up/Down pickers, Hits (RO), IP (RO). Saving Pending/Rejected → Published
sets `publish_up = NOW()` if unset.

**5.6 Action logging** — every admin action writes to `#__action_logs` (see CLAUDE.md).
Messages use `{username}`/`{accountlink}` placeholders; logging is non-fatal.

**5.6.1 Change detection** — Save/update logs only when data actually changed: md5 of a
7-field snapshot before/after (`title, category, description, contact, state, publish_up,
publish_down`); images excluded.

**5.7 Email notification** — on submission when `email_notify=1` (default On); sent to
Global Config → Server → From Email; content sanitised.

**5.8 Smart Search** — via `plg_finder_adboard`; indexes title/category/description/contact
of published, non-expired ads; GC removes expired/unpublished; result links use SEF route
(with Itemid); `url` field has no Itemid (GC dedup). Re-index after upgrades.

**5.9 SEF URLs** (with Joomla SEF on):

| View | Internal | SEF |
|---|---|---|
| Listing | `?view=ads` | `/ogloszenia` |
| Single ad | `?view=ad&id=12` | `/ogloszenia/12` |
| Submit form | `?view=form` | `/ogloszenia/dodaj` |

No slug column — the auto-increment `id` is sufficient.

## 6. Configuration Reference
**Components → Ad Board → Options** (requires `core.admin`).
- **General → Submission:** `redirect_itemid` (menu item to redirect to after submit).
- **General → Notifications:** `email_notify` (default Yes).
- **Categories:** rows of Slug (never change after ads exist) + Title EN + Title pl-PL.
- **Expiry Terms:** rows of Days + Label EN + Label pl-PL. Seed: 7/14/21/30.
- **Security → Images:** `max_image_size` (MB, 1–50, def 5), `max_images` (1–20, def 7).
- **Security → Submission Limits:** `rate_limit_max` (1–100, def 3), `rate_limit_window`
  (minutes, 1–1440, def 60).

## 7. Access Control
`core.manage` · `core.create` · `core.edit` · `core.edit.state` · `core.delete` ·
`core.admin`. Suggested: Moderator = all except `core.admin`; Site Admin adds `core.admin`;
Super User bypasses. Manager group is granted the moderator set at install; `core.admin`
left unset (Super Users only) by default.

## 8. Security Model
- **8.1 Input validation:** Joomla query builder + `quote()/escape()` (SQLi); double
  `strip_tags` input (`TextHelper::sanitize`); `ENT_QUOTES|ENT_HTML5` output (ViewEscapeTrait);
  `Session::checkToken()` on all POST (CSRF).
- **8.2 Image pipeline** (strict order, reject on first failure): `is_uploaded_file` → size
  vs param → `finfo` real MIME → MIME allow-list → extension matches MIME → `getimagesize`
  ≤ 12 MP → GD full decode (strips EXIF/payloads) → resize ≤ 1920×1920 → random hex filename.
  PHP `upload_max_filesize`/`post_max_size` are the outer barrier.
- **8.3 Anti-spam:** honeypot · IP rate limit (counts state-0 rows by IP in window) · CAPTCHA
  when configured · moderation backstop (everything enters state 0).
- **8.4 Path traversal:** `keep_images[]` filtered by `basename()` + `/^[a-f0-9]{24}\.[a-z]{3,4}$/`.

## 9. Architecture
- **9.1 MVC class map** — Admin: `AdsController` (bulk), `AdController` (single CRUD + log),
  `AdsModel` (list + lazy expiry), `AdModel` (load/save), `ImageHelper`, `TextHelper`,
  `CategoryHelper`. Site: `Router`, `FormController` (submit + anti-spam), `FormModel`,
  `AdsModel` (listing), `AdModel`.
- **9.2 SEF router** — `Joomla\Component\Adboard\Site\Service\Router` extends `RouterBase`;
  registered via `RouterFactory` in `site/services/provider.php`. `build()` internal→SEF,
  `parse()` SEF→internal.
- **9.3 JS assets** — `image-picker.js` (site form), `admin-image-picker.js` (admin form),
  `gallery.js` (lightbox), `listing.js` (filter UX, Itemid-aware URLs).
- **9.4 Joomla integration** — `com_actionlogs` (bootComponent), `com_finder`,
  Joomla ACL (`authorise`), Web Asset Manager (`joomla.asset.json`), Session (CSRF + rate
  limit), Mailer, `#__action_log_config` (registered in `script.php`).

## 10. Smart Search Finder Plugin
`plg_finder_adboard` (group `finder`, element `adboard`, content type "Advertisements").
Extends the Finder `Adapter`. Index condition `state = 1 AND (publish_down IS NULL OR
publish_down >= NOW())`. Overrides `getListQuery()`, `index()` (`url` without Itemid;
`route` with Itemid), `getAdboardItemid()` (statically cached `#__menu` lookup),
`onFinderGarbageCollection()`. Uses `$this->db ?? Factory::getDbo()`.
**Setup:** install package → enable plugin (System → Plugins, type finder) →
Components → Smart Search → Index.

## 11. Installation & Upgrade
**Requirements:** Joomla ≥ 6.1.0 · PHP ≥ 8.2 (GD) · MySQL 8 / MariaDB 10.4, utf8mb4_unicode_ci.
**Fresh install:** upload `pkg_adboard_v<version>.zip`; Joomla creates the table, seeds
categories/expiry, sets Manager ACL, registers action-log config; enable Finder plugin; run
a full index; create a menu item.
**Upgrade:** install the new zip on top (`method="upgrade"`); pending migrations in
`admin/sql/updates/mysql/` run by stored schema version; no data loss (columns only).

| Migration | Change |
|---|---|
| `1.5.15.sql` | `DROP COLUMN IF EXISTS checked_out, checked_out_time` |

## 12. Localisation
Complete en-GB and pl-PL for admin (`com_adboard.ini`, `.sys.ini`), site (`com_adboard.ini`),
and plugin (`plg_finder_adboard.ini`, `.sys.ini`). New language: copy en-GB `.ini`, translate,
add `<language tag="xx-XX">` to the manifest. Joomla's built-in CAPTCHA has no pl-PL file —
the site form template swaps the label to "Nie jestem robotem" via an inline DOM script.

## 13. Known Limitations & Backlog
- **BL-01 SEF router — DONE (v1.5.26).**
- BL-02 submitter notification on approve/reject.
- BL-03 self-service edit/withdraw via secret token.
- BL-04 GDPR consent checkbox.
- BL-05 "sold/resolved" state (`state = 3`).
- BL-06 plot-number field (allotment metadata).
- **BL-07 `mod_adboard_status` admin module — abandoned** ("Module XML data not available"
  in Joomla 6.1, root cause unresolved). Don't resurrect without solving that.

## 14. Version History (recent)
| Version | Date | Changes |
|---|---|---|
| 1.5.26 | 2026-05-30 | SEF router (`site/src/Service/Router.php`); `/ogloszenia/12`, `/ogloszenia/dodaj` |
| 1.5.22 | 2026-05-30 | Full language-file descriptions for component + finder plugin |
| 1.5.20 | 2026-05-30 | Security Options split into Images + Submission Limits |
| 1.5.19 | 2026-05-30 | Tech-debt cleanup; asset manifest + 12-section help updated |
| 1.5.16 | 2026-05-30 | Action logging via `bootComponent`; status module abandoned; CAPTCHA pl label |
| 1.5.15 | 2026-05-30 | Dropped `checked_out(_time)`; schema-update system introduced |
| 1.5.7 | 2026-05-27 | Lightbox on detail page |
| 1.5.1 | 2026-05-25 | Manager ACL defaults; Permissions tab |
| 1.5.0 | 2026-05-25 | Finder plugin split into its own package entry |
| 1.0–1.2 | 2026-05-22 | Initial build: form, moderation, help, pagination, Expired state |
