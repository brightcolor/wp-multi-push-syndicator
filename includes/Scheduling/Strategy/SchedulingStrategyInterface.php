<?php

namespace WMPS\Scheduling\Strategy;

use DateTimeImmutable;
use WMPS\Scheduling\SchedulingContext;

if (! defined('ABSPATH')) {
    exit;
}

interface SchedulingStrategyInterface
{
    public function key(): string;

    /**
     * @return array{scheduled_at:DateTimeImmutable,reason:string,meta:array<string,mixed>}
     */
    public function calculate(SchedulingContext $context): array;
}