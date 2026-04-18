<?php

namespace WMPS\Content;

use WMPS\Domain\TargetEndpoint;

if (! defined('ABSPATH')) {
    exit;
}

interface ContentTransformerInterface
{
    public function key(): string;

    /**
     * @return array{title:string,content:string,excerpt:string}
     */
    public function transform(\WP_Post $post, TargetEndpoint $target, array $payload): array;
}