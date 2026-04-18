<?php

namespace WMPS\Service;

use DateTimeImmutable;
use WMPS\Api\RemoteWordPressClient;
use WMPS\Logging\Logger;
use WMPS\Media\MediaTransferService;
use WMPS\Repository\EndpointRepository;
use WMPS\Repository\PushMapRepository;

if (! defined('ABSPATH')) {
    exit;
}

final class PushService
{
    private EndpointRepository $endpoints;
    private PushMapRepository $mapRepository;
    private SettingsService $settings;
    private SchedulingService $scheduling;
    private Logger $logger;
    private RewriteManager $rewrite;
    private MediaTransferService $mediaTransfer;

    public function __construct(
        EndpointRepository $endpoints,
        PushMapRepository $mapRepository,
        SettingsService $settings,
        SchedulingService $scheduling,
        Logger $logger,
        RewriteManager $rewrite
    ) {
        $this->endpoints = $endpoints;
        $this->mapRepository = $mapRepository;
        $this->settings = $settings;
        $this->scheduling = $scheduling;
        $this->logger = $logger;
        $this->rewrite = $rewrite;
        $this->mediaTransfer = new MediaTransferService();
    }

    public function registerHooks(): void
    {
        add_action('save_post_post', [$this, 'pushOnSave'], 20, 3);
    }

    public function pushOnSave(int $postId, \WP_Post $post, bool $update): void
    {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        if (! current_user_can('edit_post', $postId)) {
            return;
        }

        if (! in_array($post->post_status, ['publish', 'future'], true)) {
            return;
        }

        $selectedTargets = get_post_meta($postId, '_wmps_selected_targets', true);
        if (! is_array($selectedTargets) || empty($selectedTargets)) {
            return;
        }

        $allTargets = $this->endpoints->all();

        foreach ($selectedTargets as $targetId) {
            $targetId = sanitize_key((string) $targetId);
            $target = $allTargets[$targetId] ?? null;

            if (! $target || ! $target->isActive()) {
                $this->logger->warning('push_skipped_target', 'Target is missing or inactive.', [], $postId, $targetId);
                continue;
            }

            $this->pushToTarget($post, $targetId);
        }
    }

    private function pushToTarget(\WP_Post $post, string $targetId): void
    {
        $target = $this->endpoints->find($targetId);
        if (! $target) {
            return;
        }

        $postId = (int) $post->ID;

        $sourcePublishTime = $this->resolveSourcePublishTime($post);
        $schedule = $this->scheduling->calculate($target->getSchedule(), $sourcePublishTime, $targetId);

        $client = new RemoteWordPressClient($target);

        $basePayload = [
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
        ];

        $transformed = $this->rewrite->transform($post, $target, $basePayload);
        $media = $this->mediaTransfer->transfer($target, $client, $post, (string) $transformed['content']);

        $payload = [
            'title' => (string) $transformed['title'],
            'content' => (string) $media['content'],
            'excerpt' => (string) $transformed['excerpt'],
            'status' => $schedule['status'],
            'date' => $schedule['scheduled_at_local'],
            'date_gmt' => $schedule['scheduled_at_gmt'],
            'slug' => $post->post_name,
        ];

        if ((int) $media['featured_media'] > 0) {
            $payload['featured_media'] = (int) $media['featured_media'];
        }

        $hash = hash('sha256', wp_json_encode($payload));

        $mapping = $this->mapRepository->find($postId, $targetId);
        if ($mapping && isset($mapping['last_payload_hash']) && hash_equals((string) $mapping['last_payload_hash'], $hash)) {
            $this->logger->info('push_skipped_no_change', 'Skipped push because payload is unchanged.', [], $postId, $targetId);
            return;
        }

        $response = null;
        $status = 'success';
        $error = null;
        $remotePostId = isset($mapping['remote_post_id']) ? (int) $mapping['remote_post_id'] : 0;

        if ($remotePostId > 0) {
            $response = $client->updatePost($remotePostId, $payload);
        } else {
            $response = $client->createPost($payload);
        }

        if (is_wp_error($response)) {
            $status = 'failed';
            $error = $response->get_error_message();

            $this->logger->error(
                'push_failed',
                'Push to remote target failed.',
                [
                    'error' => $error,
                    'schedule' => $schedule,
                ],
                $postId,
                $targetId
            );
        } else {
            $remotePostId = (int) ($response['id'] ?? $remotePostId);
            $this->logger->info(
                'push_success',
                'Push to remote target completed.',
                [
                    'remote_post_id' => $remotePostId,
                    'schedule' => $schedule,
                    'media_count' => count((array) $media['attachments']),
                ],
                $postId,
                $targetId
            );
        }

        $this->mapRepository->upsert($postId, $targetId, [
            'remote_post_id' => $remotePostId ?: null,
            'status' => $status,
            'last_error' => $error,
            'last_payload_hash' => $hash,
            'last_scheduled_at_gmt' => $schedule['scheduled_at_gmt'],
            'last_pushed_at_gmt' => gmdate('Y-m-d H:i:s'),
            'last_strategy' => $schedule['strategy'],
        ]);
    }

    private function resolveSourcePublishTime(\WP_Post $post): DateTimeImmutable
    {
        $timezone = wp_timezone();

        $date = $post->post_date;
        if (! is_string($date) || $date === '' || $date === '0000-00-00 00:00:00') {
            $date = current_time('mysql');
        }

        return new DateTimeImmutable($date, $timezone);
    }
}