<?php

declare(strict_types=1);

/**
 * LUX EMPIRE
 * Central Media Service
 *
 * Handles property images and videos.
 *
 * Rules:
 * - Multiple images are allowed.
 * - Only one video is allowed.
 * - A property cannot contain both images and a video.
 * - Generated filenames always begin with LUXEMPIRE_.
 */

class MediaService
{
    private string $uploadDirectory;

    private int $maxImageSize = 40 * 1024 * 1024; // 40 MB
    private int $maxVideoSize = 1024 * 1024 * 1024; // 1 GB

    public function __construct()
    {
        $this->uploadDirectory =
            dirname(__DIR__)
            . '/assets/uploads/house_images/';

        $this->ensureUploadDirectory();
    }

    /**
     * Ensure the media directory exists.
     */
    private function ensureUploadDirectory(): void
    {
        if (!is_dir($this->uploadDirectory)) {

            if (!mkdir($this->uploadDirectory, 0755, true)) {
                throw new RuntimeException(
                    'Unable to create media upload directory.'
                );
            }
        }
    }

    /**
     * Generate a secure LUX EMPIRE filename.
     */
    private function generateFilename(string $extension): string
    {
        return 'LUXEMPIRE_'
            . bin2hex(random_bytes(16))
            . '.'
            . strtolower($extension);
    }

    /**
     * Detect the real MIME type of an uploaded file.
     */
    private function detectMimeType(string $tmpFile): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        $mime = $finfo->file($tmpFile);

        if ($mime === false) {
            throw new RuntimeException(
                'Unable to determine file type.'
            );
        }

        return $mime;
    }

    /**
     * Upload and process an image.
     *
     * Returns the generated filename.
     */
    public function processImage(array $file): string
    {
        if (
            !isset(
                $file['tmp_name'],
                $file['error'],
                $file['size']
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid image upload.'
            );
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                'Image upload failed.'
            );
        }

        if ($file['size'] > $this->maxImageSize) {
            throw new RuntimeException(
                'Image must be below 40MB.'
            );
        }

        $tmpFile = $file['tmp_name'];

        $mime = $this->detectMimeType($tmpFile);

        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];

        if (!in_array($mime, $allowedMimeTypes, true)) {
            throw new RuntimeException(
                'Only JPEG, PNG and WebP images are allowed.'
            );
        }

        $imageInfo = getimagesize($tmpFile);

        if ($imageInfo === false) {
            throw new RuntimeException(
                'Invalid image file.'
            );
        }

        switch ($mime) {

            case 'image/jpeg':
                $source = imagecreatefromjpeg($tmpFile);
                break;

            case 'image/png':
                $source = imagecreatefrompng($tmpFile);
                break;

            case 'image/webp':
                $source = imagecreatefromwebp($tmpFile);
                break;

            default:
                throw new RuntimeException(
                    'Unsupported image format.'
                );
        }

        if (!$source) {
            throw new RuntimeException(
                'Unable to process image.'
            );
        }

        $originalWidth = imagesx($source);
        $originalHeight = imagesy($source);

        $maxWidth = 1600;

        if ($originalWidth > $maxWidth) {

            $newWidth = $maxWidth;

            $newHeight = (int) floor(
                ($originalHeight * $newWidth)
                / $originalWidth
            );

        } else {

            $newWidth = $originalWidth;
            $newHeight = $originalHeight;
        }

        $compressed = imagecreatetruecolor(
            $newWidth,
            $newHeight
        );

        if (!$compressed) {

            imagedestroy($source);

            throw new RuntimeException(
                'Unable to create processed image.'
            );
        }

        /*
         * Preserve transparency.
         */
        imagealphablending($compressed, false);
        imagesavealpha($compressed, true);

        imagecopyresampled(
            $compressed,
            $source,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $originalWidth,
            $originalHeight
        );

        $filename = $this->generateFilename('webp');

        $targetPath =
            $this->uploadDirectory
            . $filename;

        if (!imagewebp(
            $compressed,
            $targetPath,
            82
        )) {

            imagedestroy($source);
            imagedestroy($compressed);

            throw new RuntimeException(
                'Failed to save processed image.'
            );
        }

        imagedestroy($source);
        imagedestroy($compressed);

        return $filename;
    }

    /**
     * Upload and process a video.
     *
     * Returns the generated filename.
     */
    public function processVideo(array $file): string
    {
        if (
            !isset(
                $file['tmp_name'],
                $file['error'],
                $file['size']
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid video upload.'
            );
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                'Video upload failed.'
            );
        }

        if ($file['size'] > $this->maxVideoSize) {
            throw new RuntimeException(
                'Video must be below 1GB.'
            );
        }

        $tmpFile = $file['tmp_name'];

        $mime = $this->detectMimeType($tmpFile);

        $allowedMimeTypes = [
            'video/mp4',
            'video/webm',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-matroska'
        ];

        if (!in_array($mime, $allowedMimeTypes, true)) {
            throw new RuntimeException(
                'Unsupported video format.'
            );
        }

        /*
         * FFmpeg will normalize the uploaded video
         * into MP4 for consistent browser playback.
         */
        $filename = $this->generateFilename('mp4');

        $targetPath =
            $this->uploadDirectory
            . $filename;

        $input =
            escapeshellarg($tmpFile);

        $output =
            escapeshellarg($targetPath);

        $command =
            'ffmpeg'
            . ' -y'
            . ' -i ' . $input
            . ' -c:v libx264'
            . ' -preset medium'
            . ' -crf 28'
            . ' -c:a aac'
            . ' -movflags +faststart'
            . ' ' . $output
            . ' 2>&1';

        exec(
            $command,
            $outputLines,
            $returnCode
        );

        if (
            $returnCode !== 0
            ||
            !file_exists($targetPath)
            ||
            filesize($targetPath) === 0
        ) {

            if (file_exists($targetPath)) {
                unlink($targetPath);
            }

            throw new RuntimeException(
                'Failed to process video.'
            );
        }

        return $filename;
    }

    /**
     * Delete a media file.
     */
    public function delete(string $filename): bool
    {
        if ($filename === '') {
            return false;
        }

        $filename = basename($filename);

        $path =
            $this->uploadDirectory
            . $filename;

        if (!file_exists($path)) {
            return false;
        }

        return unlink($path);
    }

    /**
     * Get absolute path to a media file.
     */
    public function getPath(string $filename): string
    {
        return $this->uploadDirectory
            . basename($filename);
    }
}
