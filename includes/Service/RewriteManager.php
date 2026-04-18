<?php

namespace WMPS\Service;

use WMPS\Content\ContentTransformerInterface;
use WMPS\Content\NoopTransformer;
use WMPS\Domain\TargetEndpoint;

if (! defined('ABSPATH')) {
    exit;
}

final class RewriteManager
{
    /**
     * @var array<string,ContentTransformerInterface>
     */
    private array $transformers = [];

    public function __construct()
    {
        $this->registerTransformer(new NoopTransformer());

        $transformers = apply_filters('wmps_register_transformers', []);
        if (is_array($transformers)) {
            foreach ($transformers as $transformer) {
                if ($transformer instanceof ContentTransformerInterface) {
                    $this->registerTransformer($transformer);
                }
            }
        }
    }

    public function registerTransformer(ContentTransformerInterface $transformer): void
    {
        $this->transformers[$transformer->key()] = $transformer;
    }

    /**
     * @return array{title:string,content:string,excerpt:string}
     */
    public function transform(\WP_Post $post, TargetEndpoint $target, array $payload): array
    {
        $targetSettings = $target->getSettings();
        $key = sanitize_key((string) ($targetSettings['enabled_transformer'] ?? 'noop'));
        $transformer = $this->transformers[$key] ?? $this->transformers['noop'];

        $transformed = $transformer->transform($post, $target, $payload);

        return apply_filters('wmps_transform_payload', $transformed, $post, $target, $transformer->key());
    }
}