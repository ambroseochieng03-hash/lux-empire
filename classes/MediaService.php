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

require_once __DIR__ . '/../config/app.php';

class MediaService
{
    private string $uploadDirectory;

    private int $maxImageSize = MAX_IMAGE_SIZE_BYTES;
    private int $maxVideoSize = MAX_VIDEO_SIZE_BYTES;

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
                'Image must be below ' . $this->maxImageSize . ' bytes.'
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

        // Reject before GD allocates a buffer for it — a small file
        // can still decode to an enormous pixel grid and exhaust
        // worker memory (a "decompression bomb").
        if (($imageInfo[0] * $imageInfo[1]) > 40_000_000) {
            throw new RuntimeException(
                'Image dimensions are too large.'
            );
        }

        // Cross-check GD's own read of the container against finfo's
        // — cheap extra assurance the file isn't lying about its type.
        if (($imageInfo['mime'] ?? null) !== $mime) {
            throw new RuntimeException(
                'Image content does not match its declared type.'
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

            

            throw new RuntimeException(
                'Failed to save processed image.'
            );
        }

       

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
                'Video must be below ' . $this->maxVideoSize . ' bytes.'
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
            'timeout 120'
            . ' ffmpeg'
            . ' -y'
            . ' -protocol_whitelist file,pipe'
            . ' -i ' . $input
            . ' -map 0:v:0 -map 0:a:0?'   // don't blindly pass through every stream in the container
            . ' -c:v libx264'
            . ' -preset medium'
            . ' -crf 28'
            . ' -c:a aac'
            . ' -movflags +faststart'
            . ' -max_muxing_queue_size 1024'
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

    /**
     * Validate and stage an uploaded video for ASYNC processing.
     *
     * Does the same fast checks processVideo() does (error, size, real
     * MIME type) so upload-time validation UX is unchanged, but does NOT
     * run ffmpeg. Instead it moves the file to a persistent staging
     * location (uploaded files' tmp_name is deleted when the request
     * ends, so it can't be read by a worker process later) and returns
     * enough info for a caller to queue the actual transcode job.
     */
    public function stageVideo(array $file): array
    {
        if (!isset($file['tmp_name'], $file['error'], $file['size'])) {
            throw new InvalidArgumentException('Invalid video upload.');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Video upload failed.');
        }

        if ($file['size'] > $this->maxVideoSize) {
            throw new RuntimeException('Video must be below 1GB.');
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
            throw new RuntimeException('Unsupported video format.');
        }

        $stagingDir = dirname(__DIR__) . '/storage/media_staging/';

        if (!is_dir($stagingDir) && !mkdir($stagingDir, 0755, true)) {
            throw new RuntimeException('Unable to create media staging directory.');
        }

        $freeBytes = disk_free_space($stagingDir);

        if ($freeBytes === false || $freeBytes < MIN_FREE_DISK_BYTES) {
            throw new RuntimeException('Server storage is temporarily full. Please try again shortly.');
        }

        $stagedFilename = 'staged_' . bin2hex(random_bytes(16));
        $stagedPath = $stagingDir . $stagedFilename;

        if (!move_uploaded_file($tmpFile, $stagedPath)) {
            throw new RuntimeException('Failed to stage video upload.');
        }

        return [
            'final_filename' => $this->generateFilename('mp4'),
            'staged_path'    => $stagedPath,
        ];
    }

    /**
     * Validate and stage an uploaded image for ASYNC processing —
     * same role as stageVideo(), just for images. Does the fast
     * checks (error, size, real MIME type) synchronously; the actual
     * GD resize/compress work happens later, in the worker.
     */
    public function stageImage(array $file): array
    {
        if (!isset($file['tmp_name'], $file['error'], $file['size'])) {
            throw new InvalidArgumentException('Invalid image upload.');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Image upload failed.');
        }

        if ($file['size'] > $this->maxImageSize) {
            throw new RuntimeException('Each image must be below ' . (int) ($this->maxImageSize / 1024 / 1024) . 'MB.');
        }

        $tmpFile = $file['tmp_name'];
        $mime = $this->detectMimeType($tmpFile);

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($mime, $allowedMimeTypes, true)) {
            throw new RuntimeException('Only JPEG, PNG and WebP images are allowed.');
        }

        $stagingDir = dirname(__DIR__) . '/storage/media_staging/';

        if (!is_dir($stagingDir) && !mkdir($stagingDir, 0755, true)) {
            throw new RuntimeException('Unable to create media staging directory.');
        }

        $freeBytes = disk_free_space($stagingDir);

        if ($freeBytes === false || $freeBytes < MIN_FREE_DISK_BYTES) {
            throw new RuntimeException('Server storage is temporarily full. Please try again shortly.');
        }

        $stagedFilename = 'staged_' . bin2hex(random_bytes(16));
        $stagedPath = $stagingDir . $stagedFilename;

        if (!move_uploaded_file($tmpFile, $stagedPath)) {
            throw new RuntimeException('Failed to stage image upload.');
        }

        return [
            'final_filename' => $this->generateFilename('webp'),
            'staged_path'    => $stagedPath,
        ];
    }

    /**
     * Actually compress a staged image — called by the worker, not
     * the request. Identical GD logic to the old synchronous
     * processImage(), just reading from an already-staged file
     * instead of $_FILES, and writing straight to the final path.
     */
    public function compressStagedImage(string $stagedPath, string $targetFilename): bool
    {
        $mime = $this->detectMimeType($stagedPath);

        $imageInfo = getimagesize($stagedPath);

        if ($imageInfo === false) {
            throw new RuntimeException('Invalid image file.');
        }

        if (($imageInfo[0] * $imageInfo[1]) > 40_000_000) {
            throw new RuntimeException('Image dimensions are too large.');
        }

        if (($imageInfo['mime'] ?? null) !== $mime) {
            throw new RuntimeException('Image content does not match its declared type.');
        }

        switch ($mime) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($stagedPath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($stagedPath);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($stagedPath);
                break;
            default:
                throw new RuntimeException('Unsupported image format.');
        }

        if (!$source) {
            throw new RuntimeException('Unable to process image.');
        }

        $originalWidth = imagesx($source);
        $originalHeight = imagesy($source);
        $maxWidth = 1600;

        if ($originalWidth > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) floor(($originalHeight * $newWidth) / $originalWidth);
        } else {
            $newWidth = $originalWidth;
            $newHeight = $originalHeight;
        }

        $compressed = imagecreatetruecolor($newWidth, $newHeight);

        if (!$compressed) {
            
            throw new RuntimeException('Unable to create processed image.');
        }

        imagealphablending($compressed, false);
        imagesavealpha($compressed, true);

        imagecopyresampled($compressed, $source, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

        $targetPath = $this->uploadDirectory . $targetFilename;
        $ok = imagewebp($compressed, $targetPath, 82);



        if (!$ok) {
            throw new RuntimeException('Failed to save processed image.');
        }

        return true;
    }
}
