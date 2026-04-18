<?php

namespace WMPS\Domain;

if (! defined('ABSPATH')) {
    exit;
}

final class TargetEndpoint
{
    private string $id;
    private string $name;
    private string $siteUrl;
    private string $restBase;
    private string $authType;
    private string $username;
    private string $appPassword;
    private bool $active;
    private array $schedule;
    private array $settings;

    public function __construct(array $data)
    {
        $this->id = (string) ($data['id'] ?? '');
        $this->name = (string) ($data['name'] ?? '');
        $this->siteUrl = untrailingslashit((string) ($data['site_url'] ?? ''));
        $this->restBase = untrailingslashit((string) ($data['rest_base'] ?? ''));
        $this->authType = (string) ($data['auth_type'] ?? 'application_password');
        $this->username = (string) ($data['username'] ?? '');
        $this->appPassword = (string) ($data['app_password'] ?? '');
        $this->active = (bool) ($data['active'] ?? false);
        $this->schedule = is_array($data['schedule'] ?? null) ? $data['schedule'] : [];
        $this->settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSiteUrl(): string
    {
        return $this->siteUrl;
    }

    public function getRestBase(): string
    {
        return $this->restBase !== '' ? $this->restBase : $this->siteUrl . '/wp-json/wp/v2';
    }

    public function getAuthType(): string
    {
        return $this->authType;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getAppPassword(): string
    {
        return $this->appPassword;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getSchedule(): array
    {
        return $this->schedule;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function toArray(bool $maskSecrets = false): array
    {
        $password = $this->appPassword;

        if ($maskSecrets && $password !== '') {
            $password = str_repeat('*', max(8, strlen($password)));
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'site_url' => $this->siteUrl,
            'rest_base' => $this->restBase,
            'auth_type' => $this->authType,
            'username' => $this->username,
            'app_password' => $password,
            'active' => $this->active ? 1 : 0,
            'schedule' => $this->schedule,
            'settings' => $this->settings,
        ];
    }
}