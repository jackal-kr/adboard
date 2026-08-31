# CLAUDE.md — Ad Board (`com_adboard`)

Guardrails for working on this repo. Read before editing. Keep this file current.

## What this is
A **moderated classifieds component for Joomla 6.1** built for the S.O.D. Dojazdów
garden-plot association. Public visitors submit ads with no account; everything
enters a moderation queue and is only public after a moderator approves it.
Shipped as a package (`pkg_adboard`) that bundles the component plus a Smart
Search (Finder) plugin.

- **Stack:** Joomla 6.1 / PHP 8.2+ (GD required) / MySQL 8 or MariaDB 10.4, utf8mb4.
- **Namespace:** `Joomla\Component\Adboard` — with `Administrator\` and `Site\` sub-namespaces.
- **Single table:** `#__adboard`. **No other tables.**
- **Author/manifest identity:** JOD, `dojazdowdzialki.pl`.

## Repository layout — source is the source of truth
```
src/com_adboard/         Unpacked component (admin/, site/, media/, sql/, adboard.xml, script.php)
src/plg_finder_adboard/  Unpacked finder plugin (adboard.php, adboard.xml, language/)
src/pkg_adboard.xml      Package manifest (references versionless inner zips)
build/build.sh           Assembles src/ → dist/pkg_adboard_v<ver>.zip
docker/dev-deploy.sh     Syncs src/ into a running Joomla container for fast iteration
docker/                  compose override + deploy helpers
docs/SPEC.md             Functional & technical spec (authoritative behaviour reference)
dist/                    Build output — git-ignored, never edit a zip
```
**Never edit a `.zip`.** Zips are build artifacts. Edit files under `src/`, then
`./build/build.sh`. The old `pkg_*.zip / com_*.zip` nesting is a build shape, not a repo shape.

## Build, deploy, test
- **Build package:** `./build/build.sh` → `dist/pkg_adboard_v<ver>.zip`.
- **First install (required):** upload that zip via *System → Install → Upload Package File*.
  Only a real install runs `script.php` (creates the table, seeds categories &
  expiry terms, sets Manager ACL, registers action-log config, sets the Help URL).
- **Fast iteration after first install:** `./docker/dev-deploy.sh` copies source into
  the container's installed paths. PHP / `tmpl` / CSS / JS are live on reload.
- **Reinstall the zip** whenever you change: `sql/*`, a migration, `adboard.xml`,
  `config.xml`, or `script.php`. File-sync alone won't apply those.

## Architecture facts to respect
- **MVC (namespaced, Joomla 6 service-provider style).** DI is wired in
  `admin/services/provider.php` and `site/services/provider.php` via
  `MVCFactory` / `ComponentDispatcherFactory` / `RouterFactory`. Don't hand-roll
  dispatch or bypass the provider.
- **Storage split:** ad rows live in `#__adboard`; **categories and expiry terms
  live in the component params JSON in `#__extensions`**, edited via custom Options
  fields (`CategoriesTableField`, `ExpiryDaysTableField`). Do not create tables for
  categories/expiry.
- **Ad states:** `0` pending, `1` published, `2` expired, `-1` rejected, `-2` trashed.
- **Lazy expiry, no cron:** `AdsModel::getListQuery()` runs one `UPDATE` to flip
  `1 → 2` where `publish_down < NOW()`. Keep it this way — do **not** add a scheduled task.
- **SEF router, no slug column:** `site/src/Service/Router.php` maps `id` directly
  (`/ogloszenia/12`, form at `/ogloszenia/dodaj`). Do **not** add a slug column —
  the auto-increment `id` is the identifier by design.
- **Images:** stored as a JSON array of filenames in the `images` column; files in
  `media/com_adboard/ads/`. Filenames are `bin2hex(random_bytes(12))` (24 hex chars).
  Media source folder is `media/adboard` but installs to `media/com_adboard`.

## Security invariants — do not weaken
- **Image upload pipeline (`ImageHelper`) runs in strict order, reject on first failure:**
  `is_uploaded_file` → size vs `max_image_size` → `finfo` real MIME → MIME allow-list
  (jpeg/png/gif/webp) → extension-matches-MIME → `getimagesize` ≤ 12 MP → GD full decode
  (strips EXIF/payloads) → resize to ≤ 1920×1920 → random hex filename. **Preserve every
  step and the order.** Never trust the file extension or `$_FILES['type']`.
- **Path traversal:** `keep_images[]` is filtered by `ImageHelper::filterKeepList()` —
  `basename()` + regex `/^[a-f0-9]{24}\.[a-z]{3,4}$/`. Keep this validation.
- **CSRF:** every POST calls `Session::checkToken()`. Never add a POST path without it.
- **Input:** `TextHelper::sanitize()` (strip_tags). **Output:** `ViewEscapeTrait`
  (`htmlspecialchars`, `ENT_QUOTES | ENT_HTML5`). Escape on output in every template.
- **Anti-spam:** honeypot (non-empty → silently discard), IP rate limit (`rate_limit_max`
  per `rate_limit_window` counting state-0 rows by `ip_address`), CAPTCHA when configured.
  Everything still enters state 0 regardless — moderation is the backstop.
- `ip_address` is for spam tracking only — **never render it publicly.**

## Action logging (and its change-detection)
- Writes to `#__action_logs` via the Joomla 6.1 API
  (`bootComponent(...)->getMVCFactory()->createModel('Actionlog')`). Logging is
  **non-fatal** — a logging failure must never interrupt the admin action.
- **Save/update logs only on real change.** `getAdSnapshot(int $id)` = `md5(serialize())`
  of 7 fields (`title, category, description, contact, state, publish_up, publish_down`)
  taken before and after save; equal hashes → no log entry. Image changes are excluded
  from the snapshot (processed separately). Preserve this — no-op saves must stay silent.
- Message strings use `{username}` / `{accountlink}` placeholders (clickable user link).

## Finder plugin (`plg_finder_adboard`, group `finder`)
- Extends `Joomla\Component\Finder\Administrator\Indexer\Adapter`; bails silently if
  Smart Search isn't installed.
- Indexes only `state = 1 AND (publish_down IS NULL OR publish_down >= NOW())`.
- **`$item->url` has NO Itemid** (GC dedup key); **`$item->route` includes Itemid**
  (produces the SEF link). Don't collapse these two.
- Uses `$this->db ?? Factory::getDbo()` (legacy Adapter base has no `getDatabase()`).
- **A re-index is required after any component upgrade or menu restructure.**

## Localisation
- Ships **en-GB and pl-PL**, complete. Any new user-facing string must be added to
  **both** languages, admin and/or site as appropriate, plus `.sys.ini` for
  name/description strings. Don't hard-code UI text in PHP/templates.

## ACL
`core.manage` (entry) · `core.create` · `core.edit` · `core.edit.state`
(approve/unpublish/reject) · `core.delete` · `core.admin` (Options; Super User only by
default). `script.php::setDefaultPermissions()` grants Manager everything except
`core.admin`. Declared in `admin/access.xml`.

## General Joomla conventions (this developer's hard-won rules)
- **View → Model class naming:** only the first letter is capitalised
  (`view=ads` → `AdsModel`, never camelCase like `AdsModel` vs `adsModel` — match existing casing exactly).
- **Schema migrations:** one file per version in `admin/sql/updates/mysql/<version>.sql`,
  using idempotent DDL (`... IF NOT EXISTS` / `DROP COLUMN IF EXISTS`). The installer
  runs pending files by stored schema version on upgrade. `script.php::ensureTable()`
  additionally uses `CREATE TABLE IF NOT EXISTS` so install AND update are both safe.
- **Conditional column reads:** if a query depends on a column that may not exist yet,
  guard with `SHOW COLUMNS FROM` before building the SELECT — Joomla validates column
  names at `setQuery()` time, not at `execute()`.
- **Redirects:** use `$this->setRedirect()` in controllers.

## Known backlog — intentionally NOT built (don't "fix" as bugs)
- BL-02 submitter notification on approve/reject · BL-03 self-service edit/withdraw via
  secret token · BL-04 GDPR consent checkbox · BL-05 "sold/resolved" state (`state = 3`)
  · BL-06 plot-number field. SEF router (BL-01) is **done** (v1.5.26).
- **BL-07 — `mod_adboard_status` admin module was abandoned** due to unresolved
  "Module XML data not available" errors in Joomla 6.1. Do not resurrect it without
  first solving that root cause.

## Definition of done for any change
1. Edit under `src/`; keep the security invariants above intact.
2. Schema change → add `admin/sql/updates/mysql/<newversion>.sql` (idempotent) **and**
   keep `sql/install.mysql.sql` + `script.php::ensureTable()` consistent.
3. New string → add to **both** en-GB and pl-PL.
4. Bump version in **all** the right places: `src/com_adboard/adboard.xml`,
   `src/pkg_adboard.xml`, `media/adboard/joomla.asset.json`, and the plugin's
   `adboard.xml` if it changed.
5. Update `docs/SPEC.md` (behaviour + the Version History table).
6. `./build/build.sh`, install the zip on a clean-ish Joomla, click through the
   affected view; if Finder-related, re-index.
7. **Every new/edited `.php` file carries the GPLv2+ header** (JED rule PH1):
   `@package Adboard` / `@copyright 2026 JOD` / `@license GNU General Public
   License version 2 or later; see LICENSE`, placed right after `<?php`, before
   `namespace`. The `_JEXEC` guard stays after `namespace`.
8. **Keep the `<license>` tag in all three manifests** (`pkg_adboard.xml`,
   `com_adboard/adboard.xml`, `plg_finder_adboard/adboard.xml`).

## JED / release rules (Joomla Extensions Directory)
- **Extension name has no version and matches the JED entry.** Public name is
  **"Ad Board"**; the component resolves `COM_ADBOARD -> "Ad Board"` and the plugin
  resolves `PLG_FINDER_ADBOARD -> "Smart Search - Ad Board"` (required `{Type} -
  {Name}` plugin form). Don't reintroduce suffixes like "- Full Package".
- **Update stream is auto-generated.** `build.sh` rewrites `updates/pkg_adboard.xml`
  from `<version>` on every build - never hand-edit it. To release: bump the version
  (step 4), `./build/build.sh`, create a GitHub release tagged `v<version>` with the
  built zip attached, then commit `updates/pkg_adboard.xml`. The `<downloadurl>` must
  resolve or the update check 404s.
- **Run JED Checker** on the built zip before publishing; all rules green/yellow/blue,
  none red. PH1 (headers) and PH2 (`_JEXEC`) must pass.
