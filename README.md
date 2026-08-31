# Ad Board — `com_adboard`

Moderated classifieds component for **Joomla 6.1** (S.O.D. Dojazdów garden-plot
association), packaged with a Smart Search (Finder) plugin. See
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
This repo doesn't ship a Joomla stack — it plugs into yours.

1. **First install (required, once per environment):** build the zip and install it
   through the Joomla installer. This runs `script.php` (creates `#__adboard`, seeds
   categories/expiry, sets Manager ACL, registers the action-log config). File-sync
   cannot do this.
2. **Fast iteration:** after the first install, run
   ```bash
   ./docker/dev-deploy.sh
   ```
   It stages the exact install layout and copies it into the running container.
   PHP / templates / CSS / JS are live on the next page load. Configure it if your
   container differs:
   ```bash
   JOOMLA_CONTAINER=my-joomla JOOMLA_ROOT=/var/www/html ./docker/dev-deploy.sh
   ```
   (or put those in a `.env` beside your compose).
3. **Reinstall the zip** when you change SQL, a migration, `adboard.xml`, `config.xml`,
   or `script.php`.
4. **Zero-copy alternative:** `docker/docker-compose.override.yml` bind-mounts the
   source instead of copying — see the notes in that file.

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
