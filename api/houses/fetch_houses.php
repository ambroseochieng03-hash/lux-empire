<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once '../../config/db.php';
require_once '../../config/security/DoSProtection.php';

DoSProtection::check();

try {

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid house ID.'
        ]);

        exit;
    }

    $houseId = (int) $_GET['id'];
    $cacheKey = "cache:house:{$houseId}";
    $redis = null;

    // The cache is an optimisation, never a dependency: if Redis is down
    // the endpoint still works, just without the cache.
    try {
        require_once '../../config/RedisConnection.php';
        $redis = RedisConnection::get();
        $cached = $redis->get($cacheKey);

        if ($cached !== false) {
            echo $cached;
            exit;
        }
    } catch (Throwable $cacheError) {
        $redis = null;
    }

    $database = new Database();
    $pdo = $database->connect();

    $bookedHours = defined('TENANT_BOOKED_VISIBLE_HOURS') ? (int) TENANT_BOOKED_VISIBLE_HOURS : 6;

    // NOTE: 'status' is cached for up to 60s here, so it is informational
    // only. The booking button state comes from the server-rendered card,
    // and the real gate is Payment::initiateStkPush().
    $stmt = $pdo->prepare("
        SELECT
            h.id,
            h.title,
            h.description,
            h.price,
            h.location,
            h.latitude,
            h.longitude,
            h.bedrooms,
            h.bathrooms,
            h.house_type,
            h.status,
            h.is_hidden,
            h.created_at,
            (
                h.status = 'booked'
                AND h.booked_at IS NOT NULL
                AND h.booked_at < (NOW() - INTERVAL {$bookedHours} HOUR)
            ) AS booked_expired,
            u.id AS landlord_id,
            u.full_name AS landlord_name
        FROM houses h
        JOIN users u
            ON h.landlord_id = u.id
        WHERE h.id = ?
        LIMIT 1
    ");

    $stmt->execute([$houseId]);

    $house = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$house || !empty($house['is_hidden']) || !empty($house['booked_expired'])) {

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'House not found.'
        ]);

        exit;
    }

    unset($house['is_hidden'], $house['booked_expired']);

    $imageStmt = $pdo->prepare("
        SELECT image_path
        FROM house_images
        WHERE house_id = ? AND status = 'ready'
        ORDER BY id ASC
    ");

    $imageStmt->execute([$houseId]);

    $house['images'] = $imageStmt->fetchAll(PDO::FETCH_COLUMN);

    $payload = json_encode([
        'success' => true,
        'house' => $house
    ]);

    // 60s TTL: short enough that a landlord's price/description edit is
    // never stale for long, long enough to absorb a traffic spike on one
    // popular listing.
    if ($redis !== null) {
        try {
            $redis->setex($cacheKey, 60, $payload);
        } catch (Throwable $cacheError) {
            // Not fatal.
        }
    }

    echo $payload;
    exit;

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Could not load this property.'
    ]);

    error_log('LUX EMPIRE fetch_houses error: ' . $e->getMessage());
}