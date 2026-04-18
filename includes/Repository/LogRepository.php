<?php

namespace WMPS\Repository;

use wpdb;

if (! defined('ABSPATH')) {
    exit;
}

final class LogRepository
{
    private wpdb $wpdb;
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'wmps_push_log';
    }

    public function insert(?int $postId, ?string $targetId, string $level, string $event, string $message, array $context = []): void
    {
        $this->wpdb->insert(
            $this->table,
            [
                'post_id' => $postId,
                'target_id' => $targetId,
                'level' => sanitize_key($level),
                'event' => sanitize_key($event),
                'message' => wp_strip_all_tags($message),
                'context' => ! empty($context) ? wp_json_encode($context) : null,
                'created_at_gmt' => gmdate('Y-m-d H:i:s'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function latest(int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));
        $sql = $this->wpdb->prepare("SELECT * FROM {$this->table} ORDER BY id DESC LIMIT %d", $limit);
        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }
}