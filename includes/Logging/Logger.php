<?php

namespace WMPS\Logging;

use WMPS\Repository\LogRepository;
use WMPS\Service\SettingsService;

if (! defined('ABSPATH')) {
    exit;
}

final class Logger
{
    private SettingsService $settings;
    private LogRepository $repository;

    public function __construct(SettingsService $settings, LogRepository $repository)
    {
        $this->settings = $settings;
        $this->repository = $repository;
    }

    public function info(string $event, string $message, array $context = [], ?int $postId = null, ?string $targetId = null): void
    {
        $this->write('info', $event, $message, $context, $postId, $targetId);
    }

    public function warning(string $event, string $message, array $context = [], ?int $postId = null, ?string $targetId = null): void
    {
        $this->write('warning', $event, $message, $context, $postId, $targetId);
    }

    public function error(string $event, string $message, array $context = [], ?int $postId = null, ?string $targetId = null): void
    {
        $this->write('error', $event, $message, $context, $postId, $targetId);
    }

    private function write(string $level, string $event, string $message, array $context, ?int $postId, ?string $targetId): void
    {
        if ((int) $this->settings->get('enable_logging', 1) !== 1) {
            return;
        }

        $this->repository->insert($postId, $targetId, $level, $event, $message, $context);
    }
}