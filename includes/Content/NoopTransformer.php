<?php

namespace WMPS\Content;

use WMPS\Domain\TargetEndpoint;

if (! defined('ABSPATH')) {
    exit;
}

final class NoopTransformer implements ContentTransformerInterface
{
    public function key(): string
    {
        return 'noop';
    }

    public function transform(\WP_Post $post, TargetEndpoint $target, array $payload): array
    {
        return [
            'title' => (string) ($payload['title'] ?? $post->post_title),
            'content' => (string) ($payload['content'] ?? $post->post_content),
            'excerpt' => (string) ($payload['excerpt'] ?? $post->post_excerpt),
        ];
    }
}