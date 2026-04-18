# Scheduling Strategies

## Goals

- Always publish on target after source publish time.
- Enforce strict minimum delay (default: 10 minutes).
- Keep scheduling within a bounded future window (default max: 7 days).
- Deterministically log why a timestamp was chosen.

## Strategy Interface

`WMPS\\Scheduling\\Strategy\\SchedulingStrategyInterface`

Required output:

- `scheduled_at` (`DateTimeImmutable`)
- `reason` (human-readable explanation)
- `meta` (structured diagnostic context)

## Built-in Strategies

### 1) `fixed_delay`

- Uses fixed minute offset.
- Effective delay = `max(minimum_delay, configured_delay)`.

### 2) `random_delay`

- Uses deterministic pseudo-random delay in `[min, max]`.
- Seeded by `target_id + source_publish_time` to keep repeatable behavior for same input.
- Effective min is always at least global minimum delay.

### 3) `preferred_time`

- Uses preferred clock times (e.g. `10:00,13:00,18:00`).
- Applies deterministic tolerance jitter (`+/- n minutes`).
- Computes earliest allowed time (`source + minimum_delay`, also `>= now + 1m`).
- If preferred slot is invalid due to minimum delay/now/tolerance, automatically picks next valid future slot.

## Global Constraints Applied After Strategy

- Hard minimum delay enforcement.
- Hard maximum-delay cap (`maximum_delay_days`).
- Status decision:
  - `future` if scheduled > now
  - `publish` otherwise

## Timezone Handling

- Uses WordPress site timezone (`wp_timezone()`) for local scheduling.
- Stores both local and GMT strings for remote API payload.

## Adding New Strategy

1. Create class in `includes/Scheduling/Strategy` implementing interface.
2. Register in `SchedulingService::__construct()` or via a custom extension wrapper.
3. Use strategy key in endpoint schedule config.