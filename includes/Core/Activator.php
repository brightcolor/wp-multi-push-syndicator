<?php

namespace WMPS\Core;

if (! defined('ABSPATH')) {
    exit;
}

final class Activator
{
    public static function activate(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $mapTable = $wpdb->prefix . 'wmps_push_map';
        $logTable = $wpdb->prefix . 'wmps_push_log';

        $mapSql = "CREATE TABLE {$mapTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            target_id VARCHAR(64) NOT NULL,
            remote_post_id BIGINT UNSIGNED NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            last_error TEXT NULL,
            last_payload_hash VARCHAR(64) NULL,
            last_scheduled_at_gmt DATETIME NULL,
            last_pushed_at_gmt DATETIME NULL,
            last_strategy VARCHAR(64) NULL,
            updated_at_gmt DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY post_target (post_id, target_id),
            KEY post_id (post_id),
            KEY target_id (target_id)
        ) {$charset};";

        $logSql = "CREATE TABLE {$logTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NULL,
            target_id VARCHAR(64) NULL,
            level VARCHAR(16) NOT NULL,
            event VARCHAR(64) NOT NULL,
            message TEXT NOT NULL,
            context LONGTEXT NULL,
            created_at_gmt DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY target_id (target_id),
            KEY level (level),
            KEY event (event)
        ) {$charset};";

        dbDelta($mapSql);
        dbDelta($logSql);

        update_option('wmps_db_version', WMPS_DB_VERSION);

        if (! get_option('wmps_settings')) {
            update_option('wmps_settings', [
                'minimum_delay_minutes' => 10,
                'maximum_delay_days' => 7,
                'preferred_tolerance_minutes' => 3,
                'default_strategy' => 'fixed_delay',
                'default_fixed_delay_minutes' => 30,
                'default_random_min_minutes' => 10,
                'default_random_max_minutes' => 45,
                'default_preferred_times' => ['10:00', '13:00', '18:00'],
                'enable_logging' => 1,
                'default_post_behavior' => 'none',
                'github_repository' => 'example/wp-multi-push-syndicator',
                'github_release_channel' => 'stable',
            ]);
        }

        if (! get_option('wmps_targets')) {
            update_option('wmps_targets', []);
        }
    }
}