<?php

namespace WMPS\Admin;

use WMPS\Logging\Logger;
use WMPS\Repository\EndpointRepository;
use WMPS\Repository\LogRepository;
use WMPS\Service\SettingsService;

if (! defined('ABSPATH')) {
    exit;
}

final class SettingsPage
{
    private SettingsService $settings;
    private EndpointRepository $endpointRepository;
    private Logger $logger;
    private LogRepository $logRepository;

    public function __construct(SettingsService $settings, EndpointRepository $endpointRepository, Logger $logger, LogRepository $logRepository)
    {
        $this->settings = $settings;
        $this->endpointRepository = $endpointRepository;
        $this->logger = $logger;
        $this->logRepository = $logRepository;
    }

    public function register(): void
    {
        add_action('admin_post_wmps_save_settings', [$this, 'handleSaveSettings']);
        add_action('admin_post_wmps_save_target', [$this, 'handleSaveTarget']);
        add_action('admin_post_wmps_delete_target', [$this, 'handleDeleteTarget']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, 'wmps') === false) {
            return;
        }

        wp_enqueue_style(
            'wmps-admin',
            WMPS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WMPS_VERSION
        );
    }

    public function handleSaveSettings(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to do this.', 'wp-multi-push-syndicator'));
        }

        check_admin_referer('wmps_save_settings');

        $raw = wp_unslash($_POST['settings'] ?? []);
        $raw = is_array($raw) ? $raw : [];

        $saved = $this->settings->update($raw);

        $this->logger->info('settings_saved', 'Global settings updated.', ['settings' => $saved]);

        wp_safe_redirect(add_query_arg([
            'page' => 'wmps-settings',
            'wmps_notice' => 'settings_saved',
        ], admin_url('admin.php')));
        exit;
    }

    public function handleSaveTarget(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to do this.', 'wp-multi-push-syndicator'));
        }

        check_admin_referer('wmps_save_target');

        $raw = wp_unslash($_POST['target'] ?? []);
        $raw = is_array($raw) ? $raw : [];

        $target = $this->endpointRepository->upsert($raw);

        $this->logger->info('target_saved', 'Target endpoint saved.', ['target_id' => $target->getId()]);

        wp_safe_redirect(add_query_arg([
            'page' => 'wmps-settings',
            'wmps_notice' => 'target_saved',
            'target' => $target->getId(),
        ], admin_url('admin.php')));
        exit;
    }

    public function handleDeleteTarget(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to do this.', 'wp-multi-push-syndicator'));
        }

        check_admin_referer('wmps_delete_target');

        $targetId = sanitize_key((string) ($_POST['target_id'] ?? ''));
        if ($targetId !== '') {
            $this->endpointRepository->delete($targetId);
            $this->logger->warning('target_deleted', 'Target endpoint deleted.', ['target_id' => $targetId]);
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'wmps-settings',
            'wmps_notice' => 'target_deleted',
        ], admin_url('admin.php')));
        exit;
    }

    public function renderSettingsPage(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = $this->settings->getAll();
        $targets = $this->endpointRepository->all();
        $editTargetId = isset($_GET['target']) ? sanitize_key((string) $_GET['target']) : '';
        $editTarget = $editTargetId !== '' && isset($targets[$editTargetId]) ? $targets[$editTargetId] : null;

        echo '<div class="wrap wmps-admin">';
        echo '<h1>' . esc_html__('WP Multi Push Syndicator', 'wp-multi-push-syndicator') . '</h1>';
        $this->renderNotices();

        echo '<h2>' . esc_html__('Global Settings', 'wp-multi-push-syndicator') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('wmps_save_settings');
        echo '<input type="hidden" name="action" value="wmps_save_settings" />';

        echo '<table class="form-table">';
        $this->numberRow('minimum_delay_minutes', __('Minimum Delay (minutes)', 'wp-multi-push-syndicator'), (int) $settings['minimum_delay_minutes'], 10, 720, 1);
        $this->numberRow('maximum_delay_days', __('Maximum Delay (days)', 'wp-multi-push-syndicator'), (int) $settings['maximum_delay_days'], 1, 30, 1);
        $this->numberRow('preferred_tolerance_minutes', __('Preferred Time Tolerance (+/- minutes)', 'wp-multi-push-syndicator'), (int) $settings['preferred_tolerance_minutes'], 0, 30, 1);
        $this->selectRow('default_strategy', __('Default Scheduling Strategy', 'wp-multi-push-syndicator'), (string) $settings['default_strategy'], [
            'fixed_delay' => __('Fixed Delay', 'wp-multi-push-syndicator'),
            'random_delay' => __('Random Delay', 'wp-multi-push-syndicator'),
            'preferred_time' => __('Preferred Time', 'wp-multi-push-syndicator'),
        ]);
        $this->numberRow('default_fixed_delay_minutes', __('Default Fixed Delay (minutes)', 'wp-multi-push-syndicator'), (int) $settings['default_fixed_delay_minutes'], 10, 720, 1);
        $this->numberRow('default_random_min_minutes', __('Default Random Min (minutes)', 'wp-multi-push-syndicator'), (int) $settings['default_random_min_minutes'], 10, 720, 1);
        $this->numberRow('default_random_max_minutes', __('Default Random Max (minutes)', 'wp-multi-push-syndicator'), (int) $settings['default_random_max_minutes'], 10, 720, 1);
        $this->textRow('default_preferred_times', __('Default Preferred Times (CSV HH:MM)', 'wp-multi-push-syndicator'), implode(',', (array) $settings['default_preferred_times']));
        $this->textRow('github_repository', __('GitHub Repository (owner/repo)', 'wp-multi-push-syndicator'), (string) $settings['github_repository']);
        echo '<tr><th scope="row">' . esc_html__('Enable Logging', 'wp-multi-push-syndicator') . '</th><td><label><input type="checkbox" name="settings[enable_logging]" value="1" ' . checked((int) $settings['enable_logging'], 1, false) . ' /> ' . esc_html__('Store push logs in database', 'wp-multi-push-syndicator') . '</label></td></tr>';
        echo '</table>';

        submit_button(__('Save Settings', 'wp-multi-push-syndicator'));
        echo '</form>';

        echo '<hr />';
        echo '<h2>' . esc_html__('Target Endpoints', 'wp-multi-push-syndicator') . '</h2>';

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Name', 'wp-multi-push-syndicator') . '</th>';
        echo '<th>' . esc_html__('URL', 'wp-multi-push-syndicator') . '</th>';
        echo '<th>' . esc_html__('Strategy', 'wp-multi-push-syndicator') . '</th>';
        echo '<th>' . esc_html__('Status', 'wp-multi-push-syndicator') . '</th>';
        echo '<th>' . esc_html__('Actions', 'wp-multi-push-syndicator') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($targets)) {
            echo '<tr><td colspan="5">' . esc_html__('No targets configured yet.', 'wp-multi-push-syndicator') . '</td></tr>';
        } else {
            foreach ($targets as $target) {
                $schedule = $target->getSchedule();
                $strategy = $schedule['strategy'] ?? '(global)';

                echo '<tr>';
                echo '<td>' . esc_html($target->getName()) . '<br/><code>' . esc_html($target->getId()) . '</code></td>';
                echo '<td>' . esc_html($target->getSiteUrl()) . '</td>';
                echo '<td>' . esc_html((string) $strategy) . '</td>';
                echo '<td>' . ($target->isActive() ? esc_html__('Active', 'wp-multi-push-syndicator') : esc_html__('Inactive', 'wp-multi-push-syndicator')) . '</td>';
                echo '<td>';
                echo '<a class="button button-small" href="' . esc_url(add_query_arg(['page' => 'wmps-settings', 'target' => $target->getId()], admin_url('admin.php'))) . '">' . esc_html__('Edit', 'wp-multi-push-syndicator') . '</a> ';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline;">';
                wp_nonce_field('wmps_delete_target');
                echo '<input type="hidden" name="action" value="wmps_delete_target" />';
                echo '<input type="hidden" name="target_id" value="' . esc_attr($target->getId()) . '" />';
                echo '<button class="button button-small" type="submit" onclick="return confirm(\'' . esc_js(__('Delete this target?', 'wp-multi-push-syndicator')) . '\');">' . esc_html__('Delete', 'wp-multi-push-syndicator') . '</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        echo '<h3>' . esc_html($editTarget ? __('Edit Target', 'wp-multi-push-syndicator') : __('Add Target', 'wp-multi-push-syndicator')) . '</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('wmps_save_target');
        echo '<input type="hidden" name="action" value="wmps_save_target" />';

        $targetData = $editTarget ? $editTarget->toArray() : [
            'id' => '',
            'name' => '',
            'site_url' => '',
            'rest_base' => '',
            'auth_type' => 'application_password',
            'username' => '',
            'app_password' => '',
            'active' => 1,
            'schedule' => [
                'strategy' => '',
                'fixed_delay_minutes' => '',
                'random_min_minutes' => '',
                'random_max_minutes' => '',
                'preferred_times' => [],
                'tolerance_minutes' => '',
            ],
            'settings' => ['enabled_transformer' => 'noop'],
        ];

        echo '<table class="form-table">';
        echo '<tr><th>' . esc_html__('Target ID', 'wp-multi-push-syndicator') . '</th><td><input name="target[id]" type="text" class="regular-text" value="' . esc_attr((string) $targetData['id']) . '" placeholder="target_news_de" /></td></tr>';
        echo '<tr><th>' . esc_html__('Display Name', 'wp-multi-push-syndicator') . '</th><td><input name="target[name]" type="text" class="regular-text" value="' . esc_attr((string) $targetData['name']) . '" required /></td></tr>';
        echo '<tr><th>' . esc_html__('Site URL', 'wp-multi-push-syndicator') . '</th><td><input name="target[site_url]" type="url" class="regular-text" value="' . esc_attr((string) $targetData['site_url']) . '" required /></td></tr>';
        echo '<tr><th>' . esc_html__('REST Base URL', 'wp-multi-push-syndicator') . '</th><td><input name="target[rest_base]" type="url" class="regular-text" value="' . esc_attr((string) $targetData['rest_base']) . '" placeholder="https://example.com/wp-json/wp/v2" /></td></tr>';
        echo '<tr><th>' . esc_html__('Auth Type', 'wp-multi-push-syndicator') . '</th><td><select name="target[auth_type]"><option value="application_password" selected="selected">Application Password</option></select></td></tr>';
        echo '<tr><th>' . esc_html__('Username', 'wp-multi-push-syndicator') . '</th><td><input name="target[username]" type="text" class="regular-text" value="' . esc_attr((string) $targetData['username']) . '" required /></td></tr>';
        echo '<tr><th>' . esc_html__('Application Password', 'wp-multi-push-syndicator') . '</th><td><input name="target[app_password]" type="password" class="regular-text" value="" placeholder="' . esc_attr($editTarget ? __('Leave empty to keep existing password', 'wp-multi-push-syndicator') : '') . '" ' . ($editTarget ? '' : 'required') . ' /></td></tr>';
        echo '<tr><th>' . esc_html__('Active', 'wp-multi-push-syndicator') . '</th><td><label><input type="checkbox" name="target[active]" value="1" ' . checked((int) $targetData['active'], 1, false) . ' /> ' . esc_html__('Enable this target', 'wp-multi-push-syndicator') . '</label></td></tr>';

        $schedule = is_array($targetData['schedule']) ? $targetData['schedule'] : [];
        echo '<tr><th>' . esc_html__('Scheduling Strategy', 'wp-multi-push-syndicator') . '</th><td><select name="target[schedule][strategy]">';
        echo '<option value="">' . esc_html__('Use global default', 'wp-multi-push-syndicator') . '</option>';
        echo '<option value="fixed_delay" ' . selected(($schedule['strategy'] ?? ''), 'fixed_delay', false) . '>' . esc_html__('Fixed Delay', 'wp-multi-push-syndicator') . '</option>';
        echo '<option value="random_delay" ' . selected(($schedule['strategy'] ?? ''), 'random_delay', false) . '>' . esc_html__('Random Delay', 'wp-multi-push-syndicator') . '</option>';
        echo '<option value="preferred_time" ' . selected(($schedule['strategy'] ?? ''), 'preferred_time', false) . '>' . esc_html__('Preferred Time', 'wp-multi-push-syndicator') . '</option>';
        echo '</select></td></tr>';
        echo '<tr><th>' . esc_html__('Fixed Delay Minutes', 'wp-multi-push-syndicator') . '</th><td><input type="number" name="target[schedule][fixed_delay_minutes]" value="' . esc_attr((string) ($schedule['fixed_delay_minutes'] ?? '')) . '" min="10" /></td></tr>';
        echo '<tr><th>' . esc_html__('Random Delay Window (min/max)', 'wp-multi-push-syndicator') . '</th><td><input type="number" name="target[schedule][random_min_minutes]" value="' . esc_attr((string) ($schedule['random_min_minutes'] ?? '')) . '" min="10" /> / <input type="number" name="target[schedule][random_max_minutes]" value="' . esc_attr((string) ($schedule['random_max_minutes'] ?? '')) . '" min="10" /></td></tr>';
        $preferred = isset($schedule['preferred_times']) && is_array($schedule['preferred_times']) ? implode(',', $schedule['preferred_times']) : '';
        echo '<tr><th>' . esc_html__('Preferred Times (CSV HH:MM)', 'wp-multi-push-syndicator') . '</th><td><input type="text" class="regular-text" name="target[schedule][preferred_times]" value="' . esc_attr($preferred) . '" /></td></tr>';
        echo '<tr><th>' . esc_html__('Tolerance (+/- minutes)', 'wp-multi-push-syndicator') . '</th><td><input type="number" name="target[schedule][tolerance_minutes]" value="' . esc_attr((string) ($schedule['tolerance_minutes'] ?? '')) . '" min="0" max="30" /></td></tr>';

        $targetSettings = is_array($targetData['settings']) ? $targetData['settings'] : [];
        echo '<tr><th>' . esc_html__('Transformer Key', 'wp-multi-push-syndicator') . '</th><td><input name="target[settings][enabled_transformer]" type="text" class="regular-text" value="' . esc_attr((string) ($targetSettings['enabled_transformer'] ?? 'noop')) . '" /></td></tr>';
        echo '</table>';

        submit_button($editTarget ? __('Update Target', 'wp-multi-push-syndicator') : __('Create Target', 'wp-multi-push-syndicator'));

        echo '</form>';
        echo '</div>';
    }

    public function renderLogsPage(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $rows = $this->logRepository->latest(300);

        echo '<div class="wrap wmps-admin">';
        echo '<h1>' . esc_html__('WP Multi Push Logs', 'wp-multi-push-syndicator') . '</h1>';

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Time (GMT)', 'wp-multi-push-syndicator') . '</th>';
        echo '<th>' . esc_html__('Level', 'wp-multi-push-syndicator') . '</th>';
        echo '<th>' . esc_html__('Event', 'wp-multi-push-syndicator') . '</th>';
        echo '<th>' . esc_html__('Post', 'wp-multi-push-syndicator') . '</th>';
        echo '<th>' . esc_html__('Target', 'wp-multi-push-syndicator') . '</th>';
        echo '<th>' . esc_html__('Message', 'wp-multi-push-syndicator') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($rows)) {
            echo '<tr><td colspan="6">' . esc_html__('No logs available.', 'wp-multi-push-syndicator') . '</td></tr>';
        } else {
            foreach ($rows as $row) {
                $postId = (int) ($row['post_id'] ?? 0);
                $postLink = $postId > 0 ? get_edit_post_link($postId) : '';
                echo '<tr>';
                echo '<td>' . esc_html((string) ($row['created_at_gmt'] ?? '')) . '</td>';
                echo '<td><code>' . esc_html((string) ($row['level'] ?? '')) . '</code></td>';
                echo '<td><code>' . esc_html((string) ($row['event'] ?? '')) . '</code></td>';
                echo '<td>';
                if ($postLink) {
                    echo '<a href="' . esc_url($postLink) . '">#' . esc_html((string) $postId) . '</a>';
                } else {
                    echo '&mdash;';
                }
                echo '</td>';
                echo '<td>' . esc_html((string) ($row['target_id'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($row['message'] ?? '')) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private function renderNotices(): void
    {
        $notice = isset($_GET['wmps_notice']) ? sanitize_key((string) $_GET['wmps_notice']) : '';

        $messages = [
            'settings_saved' => __('Settings saved.', 'wp-multi-push-syndicator'),
            'target_saved' => __('Target saved.', 'wp-multi-push-syndicator'),
            'target_deleted' => __('Target deleted.', 'wp-multi-push-syndicator'),
        ];

        if ($notice === '' || ! isset($messages[$notice])) {
            return;
        }

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$notice]) . '</p></div>';
    }

    private function numberRow(string $key, string $label, int $value, int $min, int $max, int $step): void
    {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td><input type="number" name="settings[' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '" min="' . esc_attr((string) $min) . '" max="' . esc_attr((string) $max) . '" step="' . esc_attr((string) $step) . '" /></td></tr>';
    }

    private function textRow(string $key, string $label, string $value): void
    {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td><input type="text" name="settings[' . esc_attr($key) . ']" class="regular-text" value="' . esc_attr($value) . '" /></td></tr>';
    }

    private function selectRow(string $key, string $label, string $selectedValue, array $choices): void
    {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td><select name="settings[' . esc_attr($key) . ']">';

        foreach ($choices as $value => $choiceLabel) {
            echo '<option value="' . esc_attr((string) $value) . '" ' . selected($selectedValue, (string) $value, false) . '>' . esc_html((string) $choiceLabel) . '</option>';
        }

        echo '</select></td></tr>';
    }
}