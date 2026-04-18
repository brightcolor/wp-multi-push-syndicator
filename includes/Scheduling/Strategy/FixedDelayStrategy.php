<?php

namespace WMPS\Scheduling\Strategy;

use DateInterval;
use WMPS\Scheduling\SchedulingContext;

if (! defined('ABSPATH')) {
    exit;
}

final class FixedDelayStrategy implements SchedulingStrategyInterface
{
    public function key(): string
    {
        return 'fixed_delay';
    }

    public function calculate(SchedulingContext $context): array
    {
        $global = $context->globalSettings();
        $schedule = $context->targetSchedule();

        $minimum = max(10, (int) ($global['minimum_delay_minutes'] ?? 10));
        $delay = (int) ($schedule['fixed_delay_minutes'] ?? ($global['default_fixed_delay_minutes'] ?? 30));
        $delay = max($minimum, $delay);

        $scheduled = $context->sourcePublishTime()->add(new DateInterval('PT' . $delay . 'M'));

        return [
            'scheduled_at' => $scheduled,
            'reason' => sprintf('Fixed delay strategy used %d minutes offset.', $delay),
            'meta' => [
                'strategy' => $this->key(),
                'delay_minutes' => $delay,
                'minimum_delay_minutes' => $minimum,
            ],
        ];
    }
}