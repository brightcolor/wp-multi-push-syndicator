<?php

namespace WMPS\Scheduling\Strategy;

use DateInterval;
use DateTimeImmutable;
use WMPS\Scheduling\SchedulingContext;

if (! defined('ABSPATH')) {
    exit;
}

final class PreferredTimeStrategy implements SchedulingStrategyInterface
{
    public function key(): string
    {
        return 'preferred_time';
    }

    public function calculate(SchedulingContext $context): array
    {
        $global = $context->globalSettings();
        $schedule = $context->targetSchedule();

        $minimumDelay = max(10, (int) ($global['minimum_delay_minutes'] ?? 10));
        $tolerance = (int) ($schedule['tolerance_minutes'] ?? ($global['preferred_tolerance_minutes'] ?? 3));
        $tolerance = max(0, min(30, $tolerance));

        $preferred = $schedule['preferred_times'] ?? ($global['default_preferred_times'] ?? ['10:00', '13:00', '18:00']);
        if (! is_array($preferred) || empty($preferred)) {
            $preferred = ['10:00'];
        }

        $notBefore = $context->sourcePublishTime()->add(new DateInterval('PT' . $minimumDelay . 'M'));
        if ($notBefore < $context->now()) {
            $notBefore = $context->now()->add(new DateInterval('PT1M'));
        }

        $candidate = $this->nextCandidate($preferred, $notBefore);

        if ($tolerance > 0) {
            $jitter = $this->deterministicJitter($context->targetId(), $context->sourcePublishTime()->format('c'), $tolerance);
            $candidate = $candidate->add(new DateInterval('PT' . abs($jitter) . 'M'));
            if ($jitter < 0) {
                $candidate = $candidate->sub(new DateInterval('PT' . abs($jitter) . 'M'));
            }
        }

        if ($candidate < $notBefore) {
            $candidate = $this->nextCandidate($preferred, $notBefore->add(new DateInterval('PT1M')));
        }

        return [
            'scheduled_at' => $candidate,
            'reason' => sprintf(
                'Preferred time strategy selected next valid slot with tolerance %d minutes. Earliest allowed: %s',
                $tolerance,
                $notBefore->format(DATE_ATOM)
            ),
            'meta' => [
                'strategy' => $this->key(),
                'preferred_times' => array_values($preferred),
                'tolerance_minutes' => $tolerance,
                'not_before' => $notBefore->format(DATE_ATOM),
                'selected' => $candidate->format(DATE_ATOM),
            ],
        ];
    }

    /**
     * @param array<int,string> $preferredTimes
     */
    private function nextCandidate(array $preferredTimes, DateTimeImmutable $notBefore): DateTimeImmutable
    {
        $baseDate = $notBefore;

        for ($dayOffset = 0; $dayOffset <= 14; $dayOffset++) {
            $date = $baseDate->add(new DateInterval('P' . $dayOffset . 'D'));

            foreach ($preferredTimes as $time) {
                if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', (string) $time, $matches)) {
                    continue;
                }

                $candidate = $date->setTime((int) $matches[1], (int) $matches[2], 0);

                if ($candidate >= $notBefore) {
                    return $candidate;
                }
            }
        }

        return $notBefore->add(new DateInterval('PT10M'));
    }

    private function deterministicJitter(string $targetId, string $sourceTime, int $tolerance): int
    {
        $seed = abs(crc32($targetId . '|' . $sourceTime . '|preferred'));
        $range = ($tolerance * 2) + 1;

        return (int) (($seed % $range) - $tolerance);
    }
}