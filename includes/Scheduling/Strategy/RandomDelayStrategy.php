<?php

namespace WMPS\Scheduling\Strategy;

use DateInterval;
use WMPS\Scheduling\SchedulingContext;

if (! defined('ABSPATH')) {
    exit;
}

final class RandomDelayStrategy implements SchedulingStrategyInterface
{
    public function key(): string
    {
        return 'random_delay';
    }

    public function calculate(SchedulingContext $context): array
    {
        $global = $context->globalSettings();
        $schedule = $context->targetSchedule();

        $minimum = max(10, (int) ($global['minimum_delay_minutes'] ?? 10));

        $min = (int) ($schedule['random_min_minutes'] ?? ($global['default_random_min_minutes'] ?? 10));
        $max = (int) ($schedule['random_max_minutes'] ?? ($global['default_random_max_minutes'] ?? 45));

        $min = max($minimum, $min);
        $max = max($min, $max);

        $seed = abs(crc32($context->targetId() . '|' . $context->sourcePublishTime()->format('c')));
        $span = ($max - $min) + 1;
        $delay = $min + ($seed % $span);

        $scheduled = $context->sourcePublishTime()->add(new DateInterval('PT' . $delay . 'M'));

        return [
            'scheduled_at' => $scheduled,
            'reason' => sprintf('Deterministic random delay strategy selected %d minutes in [%d,%d].', $delay, $min, $max),
            'meta' => [
                'strategy' => $this->key(),
                'selected_delay_minutes' => $delay,
                'window_min_minutes' => $min,
                'window_max_minutes' => $max,
                'seed' => $seed,
            ],
        ];
    }
}