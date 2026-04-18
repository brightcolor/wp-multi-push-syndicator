<?php

namespace WMPS\Scheduling;

use DateTimeImmutable;

if (! defined('ABSPATH')) {
    exit;
}

final class SchedulingContext
{
    private DateTimeImmutable $sourcePublishTime;
    private DateTimeImmutable $now;
    private array $globalSettings;
    private array $targetSchedule;
    private string $targetId;

    public function __construct(
        DateTimeImmutable $sourcePublishTime,
        DateTimeImmutable $now,
        array $globalSettings,
        array $targetSchedule,
        string $targetId
    ) {
        $this->sourcePublishTime = $sourcePublishTime;
        $this->now = $now;
        $this->globalSettings = $globalSettings;
        $this->targetSchedule = $targetSchedule;
        $this->targetId = $targetId;
    }

    public function sourcePublishTime(): DateTimeImmutable
    {
        return $this->sourcePublishTime;
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function globalSettings(): array
    {
        return $this->globalSettings;
    }

    public function targetSchedule(): array
    {
        return $this->targetSchedule;
    }

    public function targetId(): string
    {
        return $this->targetId;
    }
}