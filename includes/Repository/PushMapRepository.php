<?php

namespace WMPS\Repository;

use wpdb;

if (! defined('ABSPATH')) {
    exit;
}

final class PushMapRepository
{
    private wpdb $wpdb;
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'wmps_push_map';
    }

    public function find(int $postId, string $targetId): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE post_id = %d AND target_id = %s LIMIT 1",
            $postId,
            $targetId
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function byPost(int $postId): array
    {
        $sql = $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE post_id = %d", $postId);

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    public function upsert(int $postId, string $targetId, array $data): void
    {
        $existing = $this->find($postId, $targetId);

        $payload = [
            'post_id' => $postId,
            'target_id' => $targetId,
            'remote_post_id' => isset($data['remote_post_id']) ? (int) $data['remote_post_id'] : null,
            'status' => sanitize_key((string) ($data['status'] ?? 'pending')),
            'last_error' => isset($data['last_error']) ? sanitize_textarea_field((string) $data['last_error']) : null,
            'last_payload_hash' => isset($data['last_payload_hash']) ? sanitize_text_field((string) $data['last_payload_hash']) : null,
            'last_scheduled_at_gmt' => isset($data['last_scheduled_at_gmt']) ? sanitize_text_field((string) $data['last_scheduled_at_gmt']) : null,
            'last_pushed_at_gmt' => isset($data['last_pushed_at_gmt']) ? sanitize_text_field((string) $data['last_pushed_at_gmt']) : null,
            'last_strategy' => isset($data['last_strategy']) ? sanitize_key((string) $data['last_strategy']) : null,
            'updated_at_gmt' => gmdate('Y-m-d H:i:s'),
        ];

        $format = ['%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

        if ($existing) {
            $this->wpdb->update(
                $this->table,
                $payload,
                ['id' => (int) $existing['id']],
                $format,
                ['%d']
            );
            return;
        }

        $this->wpdb->insert($this->table, $payload, $format);
    }
}