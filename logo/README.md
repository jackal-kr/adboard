# Ad Board — Brand assets

Logo for the Ad Board Joomla extension.

## Files
- `adboard-icon.svg` — primary square mark (app-icon style). Scales to any size; text-free, so it renders anywhere.
- `adboard-horizontal.svg` — icon + wordmark lockup. The wordmark is outlined to paths (no font needed).
- `png/adboard-icon-{1024,512,256,128,48}.png` — raster icon exports.
- `png/adboard-horizontal.png` — raster lockup (1818px wide).

## Colours
- Emerald gradient `#16BD8B → #0B8F73` (icon background, "Ad" wordmark)
- Slate `#0F172A` ("Board" wordmark)
- Amber pin `#FBBF24 → #F59E0B`

## Where these go for a Joomla extension
- **JED listing:** upload `png/adboard-icon-512.png` (or 256) as the extension logo.
- **Repo / README:** reference `adboard-horizontal.svg` for headers, `adboard-icon.svg` elsewhere.
- **Inside the package (optional):** drop `adboard-icon.svg` into `media/adboard/images/`
  so it ships with the component and can be used on the admin dashboard. Rebuild with
  `./build/build.sh` after adding it, and bump the version per `CLAUDE.md → Definition of done`.

## Clear space & min size
Keep padding of roughly half the pin diameter around the mark. The icon stays legible
down to ~48px; below that (e.g. the 24px admin menu) Joomla normally uses an icon font,
so use a simple glyph there rather than shrinking this mark.
