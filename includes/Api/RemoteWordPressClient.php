<?php

namespace WMPS\Api;

use WP_Error;
use WMPS\Domain\TargetEndpoint;

if (! defined('ABSPATH')) {
    exit;
}

final class RemoteWordPressClient
{
    private TargetEndpoint $target;

    public function __construct(TargetEndpoint $target)
    {
        $this->target = $target;
    }

    public function createPost(array $payload)
    {
        return $this->request('POST', '/posts', $payload);
    }

    public function updatePost(int $remotePostId, array $payload)
    {
        return $this->request('POST', '/posts/' . $remotePostId, $payload);
    }

    public function uploadMedia(string $filename, string $bytes, string $mimeType, string $title = '')
    {
        $response = $this->request('POST', '/media', $bytes, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . sanitize_file_name($filename) . '"',
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        if ($title !== '' && isset($response['id'])) {
            $this->request('POST', '/media/' . (int) $response['id'], [
                'title' => $title,
                'alt_text' => $title,
            ]);
        }

        return $response;
    }

    public function request(string $method, string $route, $body = null, array $headers = [])
    {
        $url = $this->target->getRestBase() . '/' . ltrim($route, '/');

        $defaultHeaders = [
            'Accept' => 'application/json',
        ];

        $credentials = $this->target->getUsername() . ':' . $this->target->getAppPassword();
        $defaultHeaders['Authorization'] = 'Basic ' . base64_encode($credentials);

        $args = [
            'method' => strtoupper($method),
            'timeout' => 30,
            'headers' => array_merge($defaultHeaders, $headers),
        ];

        if (is_array($body)) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($body);
        } elseif (is_string($body) && $body !== '') {
            $args['body'] = $body;
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $rawBody = wp_remote_retrieve_body($response);
        $data = json_decode($rawBody, true);

        if ($status < 200 || $status >= 300) {
            $message = is_array($data) && isset($data['message']) ? (string) $data['message'] : __('Unknown remote API error.', 'wp-multi-push-syndicator');

            return new WP_Error(
                'wmps_remote_error',
                sprintf(__('Remote API error (%d): %s', 'wp-multi-push-syndicator'), $status, $message),
                [
                    'status' => $status,
                    'response' => $data,
                    'target' => $this->target->getId(),
                ]
            );
        }

        return is_array($data) ? $data : [];
    }
}