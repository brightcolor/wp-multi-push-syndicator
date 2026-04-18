<?php

namespace WMPS\Media;

use WMPS\Api\RemoteWordPressClient;
use WMPS\Domain\TargetEndpoint;

if (! defined('ABSPATH')) {
    exit;
}

final class MediaTransferService
{
    /**
     * @return array{content:string,featured_media:int,attachments:array<int,array<string,mixed>>}
     */
    public function transfer(TargetEndpoint $target, RemoteWordPressClient $client, \WP_Post $post, string $content): array
    {
        $uploadMap = [];
        $attachments = $this->collectAttachments($post, $content);

        foreach ($attachments as $attachmentId) {
            $remote = $this->uploadAttachment($client, $attachmentId);
            if (! empty($remote)) {
                $uploadMap[$attachmentId] = $remote;
            }
        }

        $updatedContent = $this->replaceContentUrls($content, $uploadMap);

        $featuredMedia = 0;
        $featuredId = (int) get_post_thumbnail_id($post);
        if ($featuredId > 0 && isset($uploadMap[$featuredId]['id'])) {
            $featuredMedia = (int) $uploadMap[$featuredId]['id'];
        }

        return [
            'content' => $updatedContent,
            'featured_media' => $featuredMedia,
            'attachments' => $uploadMap,
        ];
    }

    /**
     * @return int[]
     */
    private function collectAttachments(\WP_Post $post, string $content): array
    {
        $ids = [];

        $featuredId = (int) get_post_thumbnail_id($post);
        if ($featuredId > 0) {
            $ids[] = $featuredId;
        }

        $attached = get_attached_media('', $post->ID);
        if (is_array($attached)) {
            foreach ($attached as $media) {
                $ids[] = (int) $media->ID;
            }
        }

        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches);
        if (! empty($matches[1])) {
            foreach ($matches[1] as $url) {
                $attachmentId = attachment_url_to_postid((string) $url);
                if ($attachmentId > 0) {
                    $ids[] = (int) $attachmentId;
                }
            }
        }

        preg_match_all('/href=["\']([^"\']+)["\']/i', $content, $hrefMatches);
        if (! empty($hrefMatches[1])) {
            foreach ($hrefMatches[1] as $url) {
                $attachmentId = attachment_url_to_postid((string) $url);
                if ($attachmentId > 0) {
                    $ids[] = (int) $attachmentId;
                }
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));

        return $ids;
    }

    /**
     * @return array<string,mixed>
     */
    private function uploadAttachment(RemoteWordPressClient $client, int $attachmentId): array
    {
        $path = get_attached_file($attachmentId);
        if (! is_string($path) || $path === '' || ! file_exists($path)) {
            return [];
        }

        $bytes = file_get_contents($path);
        if ($bytes === false) {
            return [];
        }

        $mime = get_post_mime_type($attachmentId);
        if (! is_string($mime) || $mime === '') {
            $mime = 'application/octet-stream';
        }

        $filename = basename($path);
        $title = get_the_title($attachmentId);

        $response = $client->uploadMedia($filename, $bytes, $mime, (string) $title);

        if (is_wp_error($response)) {
            return [];
        }

        return [
            'id' => (int) ($response['id'] ?? 0),
            'source_url' => (string) ($response['source_url'] ?? ''),
            'local_url' => (string) wp_get_attachment_url($attachmentId),
            'local_id' => $attachmentId,
        ];
    }

    private function replaceContentUrls(string $content, array $uploadMap): string
    {
        $updated = $content;

        foreach ($uploadMap as $item) {
            $localUrl = (string) ($item['local_url'] ?? '');
            $remoteUrl = (string) ($item['source_url'] ?? '');

            if ($localUrl === '' || $remoteUrl === '') {
                continue;
            }

            $updated = str_replace($localUrl, $remoteUrl, $updated);

            $localScaled = preg_replace('/(\.[a-zA-Z0-9]+)$/', '-scaled$1', $localUrl);
            if (is_string($localScaled)) {
                $updated = str_replace($localScaled, $remoteUrl, $updated);
            }
        }

        return $updated;
    }
}