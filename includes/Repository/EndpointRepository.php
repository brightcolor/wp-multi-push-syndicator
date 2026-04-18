<?php

namespace WMPS\Repository;

use WMPS\Domain\TargetEndpoint;

if (! defined('ABSPATH')) {
    exit;
}

final class EndpointRepository
{
    private const OPTION_KEY = 'wmps_targets';

    /**
     * @return TargetEndpoint[]
     */
    public function all(): array
    {
        $targets = get_option(self::OPTION_KEY, []);

        if (! is_array($targets)) {
            return [];
        }

        $result = [];
        foreach ($targets as $targetData) {
            if (! is_array($targetData)) {
                continue;
            }

            $target = new TargetEndpoint($targetData);
            if ($target->getId() === '') {
                continue;
            }

            $result[$target->getId()] = $target;
        }

        return $result;
    }

    /**
     * @return TargetEndpoint[]
     */
    public function active(): array
    {
        return array_filter(
            $this->all(),
            static fn (TargetEndpoint $target): bool => $target->isActive()
        );
    }

    public function find(string $id): ?TargetEndpoint
    {
        $all = $this->all();

        return $all[$id] ?? null;
    }

    public function upsert(array $data): TargetEndpoint
    {
        $all = $this->all();

        $id = sanitize_key((string) ($data['id'] ?? ''));
        if ($id === '') {
            $id = 'target_' . wp_generate_password(8, false, false);
        }

        $current = $all[$id] ?? null;

        $sanitized = [
            'id' => $id,
            'name' => sanitize_text_field((string) ($data['name'] ?? '')),
            'site_url' => esc_url_raw((string) ($data['site_url'] ?? '')),
            'rest_base' => esc_url_raw((string) ($data['rest_base'] ?? '')),
            'auth_type' => sanitize_key((string) ($data['auth_type'] ?? 'application_password')),
            'username' => sanitize_text_field((string) ($data['username'] ?? '')),
            'app_password' => (string) ($data['app_password'] ?? ''),
            'active' => ! empty($data['active']) ? 1 : 0,
            'schedule' => $this->sanitizeSchedule($data['schedule'] ?? []),
            'settings' => $this->sanitizeSettings($data['settings'] ?? []),
        ];

        if ($current && $sanitized['app_password'] === '') {
            $sanitized['app_password'] = $current->getAppPassword();
        }

        $all[$id] = new TargetEndpoint($sanitized);

        $this->persist($all);

        return $all[$id];
    }

    public function delete(string $id): void
    {
        $all = $this->all();
        unset($all[$id]);
        $this->persist($all);
    }

    /**
     * @param TargetEndpoint[] $targets
     */
    private function persist(array $targets): void
    {
        $serialized = [];

        foreach ($targets as $target) {
            if (! $target instanceof TargetEndpoint) {
                continue;
            }

            $serialized[] = $target->toArray();
        }

        update_option(self::OPTION_KEY, $serialized, false);
    }

    private function sanitizeSchedule($schedule): array
    {
        $schedule = is_array($schedule) ? $schedule : [];

        $preferred = $schedule['preferred_times'] ?? [];
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
            'strategy' => sanitize_key((string) ($schedule['strategy'] ?? '')),
            'fixed_delay_minutes' => max(0, (int) ($schedule['fixed_delay_minutes'] ?? 0)),
            'random_min_minutes' => max(0, (int) ($schedule['random_min_minutes'] ?? 0)),
            'random_max_minutes' => max(0, (int) ($schedule['random_max_minutes'] ?? 0)),
            'preferred_times' => $preferred,
            'tolerance_minutes' => max(0, (int) ($schedule['tolerance_minutes'] ?? 0)),
        ];
    }

    private function sanitizeSettings($settings): array
    {
        $settings = is_array($settings) ? $settings : [];

        $rawCategoryMap = $settings['category_map'] ?? [];
        $categoryMap = [];

        if (is_string($rawCategoryMap)) {
            $lines = preg_split('/\r\n|\r|\n/', $rawCategoryMap);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $line = trim((string) $line);
                    if ($line === '' || strpos($line, ':') === false) {
                        continue;
                    }

                    [$sourceKey, $remoteId] = array_map('trim', explode(':', $line, 2));
                    $sourceKey = sanitize_text_field($sourceKey);
                    $remoteId = (int) $remoteId;

                    if ($sourceKey !== '' && $remoteId > 0) {
                        $categoryMap[$sourceKey] = $remoteId;
                    }
                }
            }
        } elseif (is_array($rawCategoryMap)) {
            foreach ($rawCategoryMap as $sourceKey => $remoteId) {
                $sourceKey = sanitize_text_field((string) $sourceKey);
                $remoteId = (int) $remoteId;
                if ($sourceKey !== '' && $remoteId > 0) {
                    $categoryMap[$sourceKey] = $remoteId;
                }
            }
        }

        return [
            'enabled_transformer' => sanitize_key((string) ($settings['enabled_transformer'] ?? 'noop')),
            'category_map' => $categoryMap,
        ];
    }
}
