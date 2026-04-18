<?php

namespace WMPS\Service;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use WMPS\Logging\Logger;
use WMPS\Scheduling\SchedulingContext;
use WMPS\Scheduling\Strategy\FixedDelayStrategy;
use WMPS\Scheduling\Strategy\PreferredTimeStrategy;
use WMPS\Scheduling\Strategy\RandomDelayStrategy;
use WMPS\Scheduling\Strategy\SchedulingStrategyInterface;

if (! defined('ABSPATH')) {
    exit;
}

final class SchedulingService
{
    private SettingsService $settings;
    private Logger $logger;

    /**
     * @var array<string,SchedulingStrategyInterface>
     */
    private array $strategies = [];

    public function __construct(SettingsService $settings, Logger $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;

        $this->registerStrategy(new FixedDelayStrategy());
        $this->registerStrategy(new RandomDelayStrategy());
        $this->registerStrategy(new PreferredTimeStrategy());
    }

    public function registerStrategy(SchedulingStrategyInterface $strategy): void
    {
        $this->strategies[$strategy->key()] = $strategy;
    }

    /**
     * @return array{scheduled_at_local:string,scheduled_at_gmt:string,status:string,strategy:string,reason:string,meta:array<string,mixed>}
     */
    public function calculate(array $targetSchedule, DateTimeImmutable $sourcePublishTimeLocal, string $targetId): array
    {
        $siteTimezone = wp_timezone();
        $sourceLocal = $sourcePublishTimeLocal->setTimezone($siteTimezone);
        $nowLocal = new DateTimeImmutable('now', $siteTimezone);

        $global = $this->settings->getAll();

        $strategyKey = sanitize_key((string) ($targetSchedule['strategy'] ?? $global['default_strategy']));
        $strategy = $this->strategies[$strategyKey] ?? $this->strategies['fixed_delay'];

        $context = new SchedulingContext($sourceLocal, $nowLocal, $global, $targetSchedule, $targetId);
        $result = $strategy->calculate($context);

        $scheduledLocal = $result['scheduled_at'];

        $minimumDelay = max(10, (int) ($global['minimum_delay_minutes'] ?? 10));
        $minimumAllowed = $sourceLocal->add(new DateInterval('PT' . $minimumDelay . 'M'));

        if ($scheduledLocal < $minimumAllowed) {
            $scheduledLocal = $minimumAllowed;
            $result['reason'] .= ' Minimum delay enforcement adjusted the result.';
        }

        $maxDays = max(1, min(30, (int) ($global['maximum_delay_days'] ?? 7)));
        $maximumAllowed = $sourceLocal->add(new DateInterval('P' . $maxDays . 'D'));
        if ($scheduledLocal > $maximumAllowed) {
            $scheduledLocal = $maximumAllowed;
            $result['reason'] .= ' Maximum delay cap adjusted the result.';
        }

        $scheduledGmt = $scheduledLocal->setTimezone(new DateTimeZone('UTC'));
        $status = $scheduledLocal > $nowLocal ? 'future' : 'publish';

        $payload = [
            'scheduled_at_local' => $scheduledLocal->format('Y-m-d H:i:s'),
            'scheduled_at_gmt' => $scheduledGmt->format('Y-m-d H:i:s'),
            'status' => $status,
            'strategy' => $strategy->key(),
            'reason' => $result['reason'],
            'meta' => $result['meta'],
        ];

        $this->logger->info(
            'scheduling_calculated',
            'Target schedule calculated.',
            [
                'target_id' => $targetId,
                'payload' => $payload,
            ]
        );

        return $payload;
    }
}