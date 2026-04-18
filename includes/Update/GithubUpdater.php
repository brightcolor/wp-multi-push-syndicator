<?php

namespace WMPS\Update;

use WMPS\Logging\Logger;
use WMPS\Service\SettingsService;

if (! defined('ABSPATH')) {
    exit;
}

final class GithubUpdater
{
    private SettingsService $settings;
    private Logger $logger;

    public function __construct(SettingsService $settings, Logger $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    public function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'checkForUpdate']);
        add_filter('plugins_api', [$this, 'pluginInfo'], 10, 3);
    }

    public function checkForUpdate($transient)
    {
        if (! is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $pluginBasename = plugin_basename(WMPS_PLUGIN_FILE);

        if (! isset($transient->checked[$pluginBasename])) {
            return $transient;
        }

        $release = $this->fetchLatestRelease();
        if (! $release) {
            return $transient;
        }

        $latest = ltrim((string) ($release['tag_name'] ?? ''), 'v');

        if ($latest === '' || version_compare($latest, WMPS_VERSION, '<=')) {
            return $transient;
        }

        $asset = $this->findZipAsset($release);
        if (! $asset) {
            return $transient;
        }

        $transient->response[$pluginBasename] = (object) [
            'slug' => dirname($pluginBasename),
            'plugin' => $pluginBasename,
            'new_version' => $latest,
            'url' => (string) ($release['html_url'] ?? ''),
            'package' => (string) ($asset['browser_download_url'] ?? ''),
            'icons' => [],
            'tested' => get_bloginfo('version'),
            'requires_php' => '7.4',
        ];

        return $transient;
    }

    public function pluginInfo($result, string $action, $args)
    {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== 'wp-multi-push-syndicator') {
            return $result;
        }

        $release = $this->fetchLatestRelease();
        if (! $release) {
            return $result;
        }

        $latest = ltrim((string) ($release['tag_name'] ?? ''), 'v');

        return (object) [
            'name' => 'WP Multi Push Syndicator',
            'slug' => 'wp-multi-push-syndicator',
            'version' => $latest ?: WMPS_VERSION,
            'author' => '<a href="https://github.com">GitHub</a>',
            'homepage' => (string) ($release['html_url'] ?? ''),
            'requires' => '6.0',
            'requires_php' => '7.4',
            'sections' => [
                'description' => __('Push posts to multiple WordPress targets with per-target scheduling.', 'wp-multi-push-syndicator'),
                'changelog' => nl2br(esc_html((string) ($release['body'] ?? ''))),
            ],
            'download_link' => (string) (($this->findZipAsset($release)['browser_download_url'] ?? '')),
        ];
    }

    private function fetchLatestRelease(): ?array
    {
        $repository = (string) $this->settings->get('github_repository', 'example/wp-multi-push-syndicator');
        if (! preg_match('/^[a-zA-Z0-9_.-]+\/[a-zA-Z0-9_.-]+$/', $repository)) {
            return null;
        }

        $cacheKey = 'wmps_release_' . md5($repository);
        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $url = 'https://api.github.com/repos/' . $repository . '/releases/latest';
        $response = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'WP-Multi-Push-Syndicator/' . WMPS_VERSION,
            ],
        ]);

        if (is_wp_error($response)) {
            $this->logger->warning('github_release_fetch_failed', 'Failed to fetch GitHub release.', ['error' => $response->get_error_message()]);
            return null;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($status !== 200 || ! is_array($json)) {
            $this->logger->warning('github_release_fetch_invalid', 'GitHub release response invalid.', ['status' => $status]);
            return null;
        }

        set_transient($cacheKey, $json, HOUR_IN_SECONDS * 6);

        return $json;
    }

    private function findZipAsset(array $release): ?array
    {
        $assets = $release['assets'] ?? [];
        if (! is_array($assets)) {
            return null;
        }

        foreach ($assets as $asset) {
            if (! is_array($asset)) {
                continue;
            }

            $name = (string) ($asset['name'] ?? '');
            if (substr($name, -4) === '.zip' && ! empty($asset['browser_download_url'])) {
                return $asset;
            }
        }

        return null;
    }
}