# Architecture

## Layered Design

- `Core`: bootstrap, lifecycle, wiring (`Plugin`, `Activator`).
- `Admin`: settings pages, metabox, admin menu.
- `Repository`: persistence adapters for options and custom tables.
- `Service`: application orchestration (`PushService`, `SchedulingService`, `RewriteManager`).
- `Scheduling`: strategy interfaces and concrete schedule calculators.
- `Api`: remote WordPress REST API communication.
- `Media`: media extraction/upload and content URL rewrite.
- `Update`: GitHub release update provider.
- `Logging`: centralized logger abstraction.

## Why this split

- Keeps business logic independent from UI.
- Makes each area testable and replaceable.
- Enables adding new targets/strategies/transformers without touching core workflow.
- Avoids monolithic plugin file and side-effect-heavy functions.

## Request Flow (Push)

1. Post save triggers `PushService`.
2. Service reads selected targets from post meta.
3. For each active target:
   - compute schedule via `SchedulingService` + strategy,
   - transform content via `RewriteManager`,
   - upload and remap media via `MediaTransferService`,
   - create or update remote post via `RemoteWordPressClient`,
   - persist status and remote ID in push-map table,
   - write logs.

## Persistence

- Option `wmps_settings`: global defaults.
- Option `wmps_targets`: endpoint list and per-target schedule/settings.
- Post meta `_wmps_selected_targets`: selected target IDs for post.
- Table `${prefix}wmps_push_map`: per-post/per-target mapping and status.
- Table `${prefix}wmps_push_log`: execution logs.

## Extensibility Points

- Add strategy class implementing `SchedulingStrategyInterface` and register in `SchedulingService`.
- Add transformer implementing `ContentTransformerInterface` via `wmps_register_transformers`.
- Additional push providers can be introduced beside WordPress REST client by adding new API adapters.