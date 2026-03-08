<?php

namespace AISEOClient;

/**
 * Image Client
 * 
 * Handles image processing operations: resizing, format detection,
 * base64 encoding for AI vision APIs, and image metadata extraction.
 */
class ImageClient
{
    /**
     * Get image as base64 encoded string for AI vision analysis.
     */
    public function toBase64(string $filePath, int $maxWidth = 1024): string
    {
        if (!file_exists($filePath)) {
            return '';
        }

        $imageInfo = @getimagesize($filePath);
        if (!$imageInfo) {
            return '';
        }

        $mimeType = $imageInfo['mime'] ?? 'image/jpeg';

        // Resize if too large
        if ($imageInfo[0] > $maxWidth) {
            $resized = $this->resize($filePath, $maxWidth);
            if ($resized) {
                $data = base64_encode($resized);
                return "data:{$mimeType};base64,{$data}";
            }
        }

        $data = base64_encode(file_get_contents($filePath));
        return "data:{$mimeType};base64,{$data}";
    }

    /**
     * Get image metadata.
     */
    public function getMetadata(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $imageInfo = @getimagesize($filePath);
        if (!$imageInfo) {
            return [];
        }

        $meta = [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'mime' => $imageInfo['mime'] ?? 'unknown',
            'filesize' => filesize($filePath),
            'filesize_human' => size_format(filesize($filePath)),
        ];

        // EXIF data if available
        if (function_exists('exif_read_data') && in_array($imageInfo['mime'], ['image/jpeg', 'image/tiff'])) {
            $exif = @exif_read_data($filePath);
            if ($exif) {
                $meta['exif'] = [
                    'camera' => $exif['Model'] ?? null,
                    'exposure' => $exif['ExposureTime'] ?? null,
                    'aperture' => $exif['COMPUTED']['ApertureFNumber'] ?? null,
                    'iso' => $exif['ISOSpeedRatings'] ?? null,
                    'date_taken' => $exif['DateTimeOriginal'] ?? null,
                ];
            }
        }

        return $meta;
    }

    /**
     * Resize image and return binary data.
     */
    private function resize(string $filePath, int $maxWidth): ?string
    {
        $imageInfo = @getimagesize($filePath);
        if (!$imageInfo) {
            return null;
        }

        $srcWidth = $imageInfo[0];
        $srcHeight = $imageInfo[1];
        $ratio = $srcHeight / $srcWidth;
        $newWidth = $maxWidth;
        $newHeight = (int) round($maxWidth * $ratio);

        $src = match ($imageInfo['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($filePath),
            'image/png' => @imagecreatefrompng($filePath),
            'image/gif' => @imagecreatefromgif($filePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($filePath) : null,
            default => null,
        };

        if (!$src) {
            return null;
        }

        $dst = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);

        ob_start();
        imagejpeg($dst, null, 80);
        $data = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        return $data ?: null;
    }

    /**
     * Check if an image URL is valid and accessible.
     */
    public function isAccessible(string $url): bool
    {
        $response = wp_remote_head($url, ['timeout' => 5]);
        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $contentType = wp_remote_retrieve_header($response, 'content-type');

        return $code === 200 && str_starts_with($contentType, 'image/');
    }
}
