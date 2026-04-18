<?php

namespace WMPS\Service;

if (! defined('ABSPATH')) {
    exit;
}

final class SettingsService
{
    private const OPTION_KEY = 'wmps_settings';

    public function getAll(): array
    {
        $defaults = [
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
        ];

        $saved = get_option(self::OPTION_KEY, []);

        if (! is_array($saved)) {
            $saved = [];
        }

        $merged = wp_parse_args($saved, $defaults);

        if (is_string($merged['default_preferred_times'])) {
            $merged['default_preferred_times'] = array_filter(array_map('trim', explode(',', $merged['default_preferred_times'])));
        }

        if (! is_array($merged['default_preferred_times'])) {
            $merged['default_preferred_times'] = $defaults['default_preferred_times'];
        }

        return $merged;
    }

    public function get(string $key, $default = null)
    {
        $all = $this->getAll();

        return $all[$key] ?? $default;
    }

    public function update(array $settings): array
    {
        $sanitized = $this->sanitize($settings);
        update_option(self::OPTION_KEY, $sanitized, false);

        return $sanitized;
    }

    public function sanitize(array $settings): array
    {
        $preferred = $settings['default_preferred_times'] ?? [];
        if (is_string($preferred)) {
            $preferred = array_filter(array_map('trim', explode(',', $preferred)));
        }

        if (! is_array($preferred)) {
            $preferred = [];
        }

        $preferred = array_values(array_filter(array_map(static function ($value): string {
            return preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', (string) $value) ? (string) $value : '';
        }, $preferred)));

        return [
            'minimum_delay_minutes' => max(10, (int) ($settings['minimum_delay_minutes'] ?? 10)),
            'maximum_delay_days' => max(1, min(30, (int) ($settings['maximum_delay_days'] ?? 7))),
            'preferred_tolerance_minutes' => max(0, min(30, (int) ($settings['preferred_tolerance_minutes'] ?? 3))),
            'default_strategy' => sanitize_key((string) ($settings['default_strategy'] ?? 'fixed_delay')),
            'default_fixed_delay_minutes' => max(10, (int) ($settings['default_fixed_delay_minutes'] ?? 30)),
            'default_random_min_minutes' => max(10, (int) ($settings['default_random_min_minutes'] ?? 10)),
            'default_random_max_minutes' => max(10, (int) ($settings['default_random_max_minutes'] ?? 45)),
            'default_preferred_times' => ! empty($preferred) ? $preferred : ['10:00', '13:00', '18:00'],
            'enable_logging' => ! empty($settings['enable_logging']) ? 1 : 0,
            'default_post_behavior' => sanitize_key((string) ($settings['default_post_behavior'] ?? 'none')),
            'github_repository' => sanitize_text_field((string) ($settings['github_repository'] ?? 'example/wp-multi-push-syndicator')),
            'github_release_channel' => sanitize_key((string) ($settings['github_release_channel'] ?? 'stable')),
        ];
    }
}