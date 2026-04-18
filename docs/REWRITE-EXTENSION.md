# Rewrite Extension

## Current State

Rewrite is intentionally not implemented with external AI dependencies in `0.1.0`.

The plugin ships with a no-op transformer only.

## Extension Interfaces

- `WMPS\\Content\\ContentTransformerInterface`
- `WMPS\\Service\\RewriteManager`

## Hook Points

### Register transformers

Filter: `wmps_register_transformers`

Return array of transformer instances.

### Adjust final payload

Filter: `wmps_transform_payload`

Arguments:

- payload (`title`, `content`, `excerpt`)
- `WP_Post`
- `TargetEndpoint`
- active transformer key

## Per-target transformer selection

Stored in target settings:

- `settings.enabled_transformer`

This allows future per-target rewrite strategy differences.

## Example Skeleton

```php
class MyTransformer implements \WMPS\Content\ContentTransformerInterface {
    public function key(): string { return 'my_transformer'; }

    public function transform(\WP_Post $post, \WMPS\Domain\TargetEndpoint $target, array $payload): array {
        $payload['content'] = my_custom_rewriter($payload['content']);
        return $payload;
    }
}

add_filter('wmps_register_transformers', function(array $transformers) {
    $transformers[] = new MyTransformer();
    return $transformers;
});
```

## Recommended next iteration

- Add provider abstraction (`AIProviderInterface`).
- Add retry/backoff and rate limiting.
- Persist rewrite audit metadata per push.
- Optional per-target prompt templates.