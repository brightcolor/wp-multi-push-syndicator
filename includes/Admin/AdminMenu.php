<?php

namespace WMPS\Admin;

if (! defined('ABSPATH')) {
    exit;
}

final class AdminMenu
{
    private SettingsPage $settingsPage;

    public function __construct(SettingsPage $settingsPage)
    {
        $this->settingsPage = $settingsPage;
    }

    public function register(): void
    {
        $capability = 'manage_options';

        add_menu_page(
            __('WP Multi Push', 'wp-multi-push-syndicator'),
            __('WP Multi Push', 'wp-multi-push-syndicator'),
            $capability,
            'wmps-settings',
            [$this->settingsPage, 'renderSettingsPage'],
            'dashicons-share-alt2',
            70
        );

        add_submenu_page(
            'wmps-settings',
            __('Settings', 'wp-multi-push-syndicator'),
            __('Settings', 'wp-multi-push-syndicator'),
            $capability,
            'wmps-settings',
            [$this->settingsPage, 'renderSettingsPage']
        );

        add_submenu_page(
            'wmps-settings',
            __('Logs', 'wp-multi-push-syndicator'),
            __('Logs', 'wp-multi-push-syndicator'),
            $capability,
            'wmps-logs',
            [$this->settingsPage, 'renderLogsPage']
        );
    }
}