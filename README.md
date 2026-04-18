# WP Multi Push Syndicator

Production-ready WordPress plugin foundation to push a post from one source site to one or many remote WordPress target sites.

## Features

- Multi-target endpoint management in wp-admin.
- Per-post target selection (multi-select checkboxes in post sidebar metabox).
- Per-target create/update logic with remote post ID mapping.
- Media pipeline:
  - featured image upload,
  - inline content image upload and URL replacement,
  - relevant attachment upload.
- Per-target scheduling with pluggable strategies:
  - `fixed_delay`,
  - `random_delay`,
  - `preferred_time`.
- Enforced minimum delay (default 10 min) and maximum scheduling window cap.
- Deterministic scheduling log reason and metadata.
- Rewrite-ready architecture via transformer interface + manager + filters.
- Database logging and per-post/per-target status visibility.
- GitHub Releases update checker (zip asset based).
- SemVer-first project structure.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Source and targets must all expose WordPress REST API.
- Target sites must have users with permission to create/edit posts and upload media.

## Installation

1. Download release ZIP from GitHub Releases.
2. Upload in WordPress: `Plugins -> Add New -> Upload Plugin`.
3. Activate `WP Multi Push Syndicator`.
4. Open `WP Multi Push` menu and configure global settings.
5. Add one or more target endpoints.

## Target Site Preparation

For each target WordPress site:

1. Ensure REST API is reachable (`/wp-json/wp/v2`).
2. Create or choose an editor/author account with post/media rights.
3. Generate an Application Password:
   - User Profile -> Application Passwords -> Add New.
4. Save `username` + `application password` in endpoint config.

## Workflow

1. Configure target endpoints in plugin settings.
2. Edit a post and select one or more targets in `Push Targets` metabox.
3. Save/update/publish the post.
4. Plugin creates or updates remote posts per target.
5. View global logs in `WP Multi Push -> Logs`.
6. Inspect per-target status in post sidebar (`Last Push Status`).

## Scheduling Model

Global defaults:

- Minimum delay minutes (hard floor, never violated)
- Maximum delay days (hard cap, prevents uncontrolled far future scheduling)
- Preferred-time tolerance (+/- minutes)
- Default strategy and fallback values

Per-target overrides:

- Strategy key
- Fixed delay value
- Random min/max delay
- Preferred times list and tolerance

See docs: [`docs/SCHEDULING.md`](docs/SCHEDULING.md)

## Rewrite Extension Point

Current release ships with `NoopTransformer` (no rewrite).

Add custom rewrite via:

- `WMPS\Content\ContentTransformerInterface`
- `wmps_register_transformers` filter
- target setting `enabled_transformer`
- optional `wmps_transform_payload` filter

See docs: [`docs/REWRITE-EXTENSION.md`](docs/REWRITE-EXTENSION.md)

## GitHub Updates

The updater reads latest release from GitHub API and looks for `.zip` release assets.

Configure repository in plugin settings (`owner/repo`).

See docs: [`docs/GITHUB-RELEASES.md`](docs/GITHUB-RELEASES.md)

## Versioning

This project follows Semantic Versioning.

- `MAJOR`: breaking changes
- `MINOR`: backwards-compatible features
- `PATCH`: bug fixes

Current: `0.1.0` (pre-1.0 foundational release).

## Security

- Capability checks for all admin actions.
- Nonce checks for settings and post metadata writes.
- Input sanitization and output escaping in admin UI.
- Explicit error logging for remote/API failures.

## Project Structure

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

## Wiki

Wiki starter pages are included in `/wiki` for direct GitHub Wiki publishing.

## License

GPL-2.0-or-later.