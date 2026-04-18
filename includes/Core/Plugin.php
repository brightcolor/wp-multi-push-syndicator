<?php

namespace WMPS\Core;

use WMPS\Admin\AdminMenu;
use WMPS\Admin\PostMetaBox;
use WMPS\Admin\SettingsPage;
use WMPS\Logging\Logger;
use WMPS\Repository\EndpointRepository;
use WMPS\Repository\LogRepository;
use WMPS\Repository\PushMapRepository;
use WMPS\Service\PushService;
use WMPS\Service\RewriteManager;
use WMPS\Service\SchedulingService;
use WMPS\Service\SettingsService;
use WMPS\Update\GithubUpdater;

if (! defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    public function boot(): void
    {
        $settings = new SettingsService();
        $endpointRepository = new EndpointRepository();
        $mapRepository = new PushMapRepository();
        $logRepository = new LogRepository();
        $logger = new Logger($settings, $logRepository);
        $rewriteManager = new RewriteManager();
        $scheduler = new SchedulingService($settings, $logger);

        $pushService = new PushService(
            $endpointRepository,
            $mapRepository,
            $settings,
            $scheduler,
            $logger,
            $rewriteManager
        );

        $settingsPage = new SettingsPage($settings, $endpointRepository, $logger, $logRepository);
        $adminMenu = new AdminMenu($settingsPage);
        $postMetaBox = new PostMetaBox($endpointRepository, $mapRepository, $logger);
        $updater = new GithubUpdater($settings, $logger);

        add_action('init', [$pushService, 'registerHooks']);
        add_action('admin_init', [$settingsPage, 'register']);
        add_action('admin_menu', [$adminMenu, 'register']);
        add_action('add_meta_boxes', [$postMetaBox, 'register']);
        add_action('save_post_post', [$postMetaBox, 'saveMetaBox'], 10, 3);

        $updater->register();
    }
}