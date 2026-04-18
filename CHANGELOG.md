# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog and this project follows Semantic Versioning.

## [0.2.0] - 2026-04-18

### Added

- Per-target category mapping support (`source slug/ID -> remote category ID`) with admin UI field.
- Category assignment in remote post payload based on configured mapping.
- Warning log entries for unmapped source categories per target.

## [0.1.0] - 2026-04-18

### Added

- Initial plugin bootstrap, activation, DB schema and autoloader.
- Admin settings UI for global options and endpoint management.
- Post sidebar metabox for per-post multi-target selection.
- Per-target mapping table for remote post IDs and push state.
- Database-backed logging and log viewer screen.
- REST API client for remote WordPress post/media operations.
- Media transfer pipeline with featured/inline/attachment handling.
- Scheduling engine with strategy pattern:
  - fixed delay,
  - deterministic random delay,
  - preferred-time with tolerance and next-valid fallback.
- Rewrite-ready abstraction (`ContentTransformerInterface`, manager, hooks).
- GitHub release updater scaffold for plugin updates.
- Documentation set and wiki starter pages.
- German language file scaffold.
