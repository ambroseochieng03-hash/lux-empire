<?php

/**
 * LUX EMPIRE
 * Media worker — handles BOTH video transcode and image compress
 * jobs, polling two separate JetStream consumers on the same
 * MEDIA_JOBS stream in a single process.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../classes/MediaService.php';
require_once __DIR__ . '/../config/RedisConnection.php';
require_once __DIR__ . '/../config/security/RedisThrottle.php';

use Basis\Nats\Client;
use Basis\Nats\Configuration;

$configuration = new Configuration([
    'host' => NATS_HOST,
    'port' => NATS_PORT,
    'user' => NATS_USER !== '' ? NATS_USER : null,
    'pass' => NATS_PASS !== '' ? NATS_PASS : null,
]);

$client = new Client($configuration);
$stream = $client->getApi()->getStream('MEDIA_JOBS');

$videoConsumer = $stream->getConsumer('media_transcoder');
$videoConsumer->getConfiguration()->setSubjectFilter('media.video.transcode');
$videoConsumer->create();
$videoQueue = $videoConsumer->getQueue();

$imageConsumer = $stream->getConsumer('media_image_compressor');
$imageConsumer->getConfiguration()->setSubjectFilter('media.image.compress');
$imageConsumer->create();
$imageQueue = $imageConsumer->getQueue();

$uploadDir = dirname(__DIR__) . '/assets/uploads/house_images/';
$mediaService = new MediaService();

function isControlMessage($message): bool
{
    return ($message->payload->headers['Status-Code'] ?? null) === '408';
}

function handleVideoJob($message, PDO $pdo, string $uploadDir): void
{
    $job = json_decode((string) $message->payload, true);

    if (!is_array($job) || empty($job['staged_path']) || empty($job['final_filename']) || empty($job['media_id'])) {
        error_log('LUX EMPIRE media worker: malformed video job, dropping: ' . (string) $message->payload);
        $message->ack();
        return;
    }

    $landlordId = $job['landlord_id'] ?? null;

    try {
        $stagedPath = $job['staged_path'];
        $targetPath = $uploadDir . $job['final_filename'];

        $check = $pdo->prepare("SELECT status FROM house_images WHERE id = ?");
        $check->execute([$job['media_id']]);
        $current = $check->fetchColumn();

        if ($current === false) {
            if (is_file($stagedPath)) {
                @unlink($stagedPath);
            }
            $message->ack();
            return;
        }

        if ($current === 'ready') {
            $message->ack();
            return;
        }

        if (!is_file($stagedPath)) {
            error_log("LUX EMPIRE media worker: staged video missing for media_id {$job['media_id']}");
            $pdo->prepare("UPDATE house_images SET status = 'failed' WHERE id = ?")->execute([$job['media_id']]);
            $message->ack();
            return;
        }

        $input = escapeshellarg($stagedPath);
        $output = escapeshellarg($targetPath);

        $command = 'nice -n 10 ffmpeg -y -i ' . $input
            . ' -c:v libx264 -preset medium -crf 28 -c:a aac -movflags +faststart '
            . $output . ' 2>&1';

        exec($command, $outputLines, $returnCode);

        if ($returnCode !== 0 || !file_exists($targetPath) || filesize($targetPath) === 0) {
            if (file_exists($targetPath)) {
                @unlink($targetPath);
            }
            error_log("LUX EMPIRE media worker: ffmpeg failed for media_id {$job['media_id']}: " . implode("\n", $outputLines));
            $pdo->prepare("UPDATE house_images SET status = 'failed' WHERE id = ?")->execute([$job['media_id']]);
            @unlink($stagedPath);
            $message->ack();
            return;
        }

        $pdo->prepare("UPDATE house_images SET status = 'ready' WHERE id = ?")->execute([$job['media_id']]);
        @unlink($stagedPath);

        echo "[" . date('Y-m-d H:i:s') . "] Transcoded media_id {$job['media_id']}" . PHP_EOL;

        $message->ack();

    } finally {
        // Free the landlord's in-flight slot regardless of outcome —
        // success, failure, or a duplicate delivery all end the same way.
        if ($landlordId !== null) {
            RedisThrottle::decrement("video:inflight:{$landlordId}");
        }
    }
}

function handleImageJob($message, PDO $pdo, MediaService $mediaService): void
{
    $job = json_decode((string) $message->payload, true);

    if (!is_array($job) || empty($job['staged_path']) || empty($job['final_filename']) || empty($job['media_id'])) {
        error_log('LUX EMPIRE media worker: malformed image job, dropping: ' . (string) $message->payload);
        $message->ack();
        return;
    }

    $stagedPath = $job['staged_path'];

    $check = $pdo->prepare("SELECT status FROM house_images WHERE id = ?");
    $check->execute([$job['media_id']]);
    $current = $check->fetchColumn();

    if ($current === false) {
        if (is_file($stagedPath)) {
            @unlink($stagedPath);
        }
        $message->ack();
        return;
    }

    if ($current === 'ready') {
        $message->ack();
        return;
    }

    if (!is_file($stagedPath)) {
        error_log("LUX EMPIRE media worker: staged image missing for media_id {$job['media_id']}");
        $pdo->prepare("UPDATE house_images SET status = 'failed' WHERE id = ?")->execute([$job['media_id']]);
        $message->ack();
        return;
    }

    try {
        $mediaService->compressStagedImage($stagedPath, $job['final_filename']);
        $pdo->prepare("UPDATE house_images SET status = 'ready' WHERE id = ?")->execute([$job['media_id']]);
        @unlink($stagedPath);

        echo "[" . date('Y-m-d H:i:s') . "] Compressed media_id {$job['media_id']}" . PHP_EOL;

    } catch (Throwable $e) {
        error_log("LUX EMPIRE media worker: image compress failed for media_id {$job['media_id']}: " . $e->getMessage());
        $pdo->prepare("UPDATE house_images SET status = 'failed' WHERE id = ?")->execute([$job['media_id']]);
        @unlink($stagedPath);
    }

    $message->ack();
}

echo "[" . date('Y-m-d H:i:s') . "] LUX EMPIRE media worker started (video + image)." . PHP_EOL;

while (true) {

    $database = new Database();
    $pdo = $database->connect();

    $handledSomething = false;

    $videoMessage = $videoQueue->next();
    if ($videoMessage !== null && !isControlMessage($videoMessage)) {
        $handledSomething = true;
        handleVideoJob($videoMessage, $pdo, $uploadDir);
    }

    $imageMessage = $imageQueue->next();
    if ($imageMessage !== null && !isControlMessage($imageMessage)) {
        $handledSomething = true;
        handleImageJob($imageMessage, $pdo, $mediaService);
    }

    if (!$handledSomething) {
        usleep(200000);
    }
}