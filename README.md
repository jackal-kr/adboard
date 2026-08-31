# Ad Board — `com_adboard`

Moderated classifieds component for **Joomla 6.1**, packaged with a Smart Search (Finder) plugin. See
[`docs/SPEC.md`](docs/SPEC.md) for behaviour and [`CLAUDE.md`](CLAUDE.md) for
development guardrails.

## Layout
```
src/com_adboard/         Component source (edit here)
src/plg_finder_adboard/  Finder plugin source
src/pkg_adboard.xml      Package manifest
build/build.sh           src/ → dist/pkg_adboard_v<ver>.zip
docker/dev-deploy.sh     Sync src/ into a running Joomla container
docs/SPEC.md             Functional & technical spec
```
`dist/` and all `*.zip` are build artifacts (git-ignored). **Edit source, never a zip.**

## Build
```bash
./build/build.sh                 # → dist/pkg_adboard_v1.5.26.zip
```
Install via Joomla admin: **System → Install → Extensions → Upload Package File**.

## Local Joomla dev loop (Docker)
The dev stack ships **in this repo** under `docker/`: Joomla 6.1 + Xdebug
(`Dockerfile.joomla`), MariaDB, phpMyAdmin, and the php/apache/mysql tuning files.
Named volumes `joomla_data` / `db_data` hold the running site — they're Docker-managed,
never committed. Compose project name is `adboard-dev`.

Drive it from the repo root (or the VS Code **Docker: …** tasks):
```bash
docker compose -f docker/compose.yml up -d --build   # first run (builds Xdebug image)
docker compose -f docker/compose.yml start           # daily: resume
docker compose -f docker/compose.yml stop            # daily: pause
docker compose -f docker/compose.yml down            # remove containers (keeps volumes)
```
Joomla → http://localhost:8080 · phpMyAdmin → http://localhost:8081

Then:

1. **Complete the Joomla web installer once** at http://localhost:8080 (site + admin account).
2. **First extension install (required):** build the zip and install it through the Joomla
   installer. This runs `script.php` (creates `#__adboard`, seeds categories/expiry, sets
   Manager ACL, registers the action-log config). File-sync cannot do this.
3. **Fast iteration:** after the first install, run **AdBoard: Build + Deploy** (Ctrl+Shift+B)
   or:
   ```bash
   ./build/build.sh && ./docker/dev-deploy.sh
   ```
   Deploy stages the exact install layout and copies it into the running `joomla_app`
   container; PHP / templates / CSS / JS are live on the next page load. Override targets
   if needed: `JOOMLA_CONTAINER=… JOOMLA_ROOT=… ./docker/dev-deploy.sh`.
4. **Reinstall the zip** when you change SQL, a migration, `adboard.xml`, `config.xml`,
   or `script.php`.
5. **Zero-copy alternative:** `docker/docker-compose.override.yml` bind-mounts the source
   instead of copying — merge it with `-f docker/compose.yml -f docker/docker-compose.override.yml`.

After anything Finder-related: **Components → Smart Search → Index** (re-index).

## Debugging (Xdebug + VS Code)
1. Install **PHP Debug** (`xdebug.php-debug`) — it's in the recommended extensions.
2. Enable Xdebug in the Joomla container, e.g. add to a php ini:
   ```ini
   zend_extension=xdebug
   xdebug.mode=debug
   xdebug.client_host=host.docker.internal
   xdebug.start_with_request=trigger
   ```
   (On Linux hosts, ensure `host.docker.internal` resolves — add
   `extra_hosts: ["host.docker.internal:host-gateway"]` to the Joomla service.)
3. In VS Code, run **"Listen for Xdebug (Ad Board)"** (`.vscode/launch.json`), set a
   breakpoint, and trigger a request (append `?XDEBUG_TRIGGER=1` or use a browser
   toggle). The `pathMappings` translate the container's installed paths back to
   `src/`, so breakpoints bind to the files you edit.

## VS Code tasks
`Terminal → Run Task…` → **AdBoard: Build package** / **AdBoard: Deploy to Joomla container**.

## Version bumping
See `CLAUDE.md → Definition of done`: bump `src/com_adboard/adboard.xml`,
`src/pkg_adboard.xml`, `media/adboard/joomla.asset.json`, the plugin manifest if it
changed; add a migration for schema changes; update both languages; update `docs/SPEC.md`.
