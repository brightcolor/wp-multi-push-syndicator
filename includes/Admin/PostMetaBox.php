<?php

namespace WMPS\Admin;

use WMPS\Logging\Logger;
use WMPS\Repository\EndpointRepository;
use WMPS\Repository\PushMapRepository;

if (! defined('ABSPATH')) {
    exit;
}

final class PostMetaBox
{
    private EndpointRepository $endpoints;
    private PushMapRepository $mapRepository;
    private Logger $logger;

    public function __construct(EndpointRepository $endpoints, PushMapRepository $mapRepository, Logger $logger)
    {
        $this->endpoints = $endpoints;
        $this->mapRepository = $mapRepository;
        $this->logger = $logger;
    }

    public function register(): void
    {
        add_meta_box(
            'wmps_target_selector',
            __('Push Targets', 'wp-multi-push-syndicator'),
            [$this, 'render'],
            'post',
            'side',
            'high'
        );
    }

    public function render(\WP_Post $post): void
    {
        wp_nonce_field('wmps_save_post_targets', 'wmps_targets_nonce');

        $targets = $this->endpoints->active();
        $selected = get_post_meta($post->ID, '_wmps_selected_targets', true);
        $selected = is_array($selected) ? array_map('sanitize_key', $selected) : [];

        if (empty($targets)) {
            echo '<p>' . esc_html__('No active targets configured. Configure them in WP Multi Push settings.', 'wp-multi-push-syndicator') . '</p>';
            return;
        }

        echo '<p>' . esc_html__('Select one or more targets for this post.', 'wp-multi-push-syndicator') . '</p>';

        foreach ($targets as $target) {
            $isChecked = in_array($target->getId(), $selected, true);
            echo '<label style="display:block;margin-bottom:6px;">';
            echo '<input type="checkbox" name="wmps_selected_targets[]" value="' . esc_attr($target->getId()) . '" ' . checked($isChecked, true, false) . ' /> ';
            echo esc_html($target->getName());
            echo '</label>';
        }

        $mapRows = $this->mapRepository->byPost((int) $post->ID);
        if (! empty($mapRows)) {
            echo '<hr /><p><strong>' . esc_html__('Last Push Status', 'wp-multi-push-syndicator') . '</strong></p>';
            echo '<ul style="margin-left:16px;list-style:disc;">';
            foreach ($mapRows as $row) {
                $status = (string) ($row['status'] ?? 'unknown');
                $targetId = (string) ($row['target_id'] ?? '');
                $remote = (int) ($row['remote_post_id'] ?? 0);
                $strategy = (string) ($row['last_strategy'] ?? '');
                $schedule = (string) ($row['last_scheduled_at_gmt'] ?? '');
                echo '<li><code>' . esc_html($targetId) . '</code>: ' . esc_html($status);
                if ($remote > 0) {
                    echo ', remote #' . esc_html((string) $remote);
                }
                if ($strategy !== '') {
                    echo ', ' . esc_html($strategy);
                }
                if ($schedule !== '') {
                    echo ', ' . esc_html($schedule) . ' GMT';
                }
                echo '</li>';
            }
            echo '</ul>';
        }
    }

    public function saveMetaBox(int $postId, \WP_Post $post, bool $update): void
    {
        if (! isset($_POST['wmps_targets_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wmps_targets_nonce'])), 'wmps_save_post_targets')) {
            return;
        }

        if (! current_user_can('edit_post', $postId)) {
            return;
        }

        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        $submitted = wp_unslash($_POST['wmps_selected_targets'] ?? []);
        $submitted = is_array($submitted) ? $submitted : [];

        $valid = array_keys($this->endpoints->active());

        $selected = [];
        foreach ($submitted as $targetId) {
            $targetId = sanitize_key((string) $targetId);
            if (in_array($targetId, $valid, true)) {
                $selected[] = $targetId;
            }
        }

        update_post_meta($postId, '_wmps_selected_targets', array_values(array_unique($selected)));

        $this->logger->info(
            'post_target_selection_saved',
            'Post target selection saved.',
            ['targets' => $selected],
            $postId
        );
    }
}