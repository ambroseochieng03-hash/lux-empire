<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/MediaService.php';
require_once __DIR__ . '/MediaJobPublisher.php';
require_once __DIR__ . '/../config/security/RedisThrottle.php';

class House
{
    private PDO $conn;
    private string $table = 'houses';
    private MediaService $media;

    public function __construct()
    {
        $database = new Database();

        $this->conn = $database->connect();

        $this->media = new MediaService();
    }

    /**
         * CREATE HOUSE
         *
         * Creates the house and optionally attaches media.
         *
         * Media rules:
         * - Multiple images allowed.
         * - One video maximum.
         * - Images and video cannot coexist.
         */
        /**
     * CREATE HOUSE
     *
     * Creates the house and optionally attaches media.
     *
     * Media rules:
     * - Multiple images allowed.
     * - One video maximum.
     * - A property cannot contain both images and a video.
     *
     * The entire operation is transactional.
     * If database insertion or media processing fails,
     * the house creation is rolled back and generated
     * media files are removed.
     */
        public function createHouse(array $data): int
        {
            $createdFiles = [];

            $stagedFiles = [];

            try {

                $title = trim($data['title'] ?? '');
                $description = trim($data['description'] ?? '');
                $price = (float) ($data['price'] ?? 0);
                $location = trim($data['location'] ?? '');

                $bedrooms = isset($data['bedrooms'])
                    && $data['bedrooms'] !== ''
                    ? (int) $data['bedrooms']
                    : 0;

                $bathrooms = isset($data['bathrooms'])
                    && $data['bathrooms'] !== ''
                    ? (int) $data['bathrooms']
                    : 0;

                $houseType = trim($data['house_type'] ?? '');

                $rating = isset($data['rating'])
                    ? (int) $data['rating']
                    : 0;

                $hasParking = !empty($data['has_parking']) ? 1 : 0;

                $landlordId = (int) ($data['landlord_id'] ?? 0);

                $latitude = $data['latitude'] ?? null;
                $longitude = $data['longitude'] ?? null;

                /*
                * -----------------------------------------
                * BASIC VALIDATION
                * -----------------------------------------
                */

                if ($title === '') {
                    throw new InvalidArgumentException(
                        'Property title is required.'
                    );
                }

                if ($price <= 0) {
                    throw new InvalidArgumentException(
                        'Property price must be greater than zero.'
                    );
                }

                if ($location === '') {
                    throw new InvalidArgumentException(
                        'Property location is required.'
                    );
                }

                if ($landlordId <= 0) {
                    throw new InvalidArgumentException(
                        'Invalid landlord.'
                    );
                }

                /*
                * -----------------------------------------
                * ATOMIC LISTING-LIMIT ENFORCEMENT
                * -----------------------------------------
                *
                * The endpoint's own count check (in create_house.php) is
                * fast-path UX only — it is NOT atomic, two simultaneous
                * requests can both pass it. This GET_LOCK/RELEASE_LOCK pair
                * is the real, authoritative enforcement. It serializes
                * concurrent createHouse() calls for the SAME landlord only
                * — other landlords are completely unaffected.
                */

                $lockName = 'lux_listing_limit:' . $landlordId;
                $lockStmt = $this->conn->prepare('SELECT GET_LOCK(:lock_name, 5) AS acquired');
                $lockStmt->execute([':lock_name' => $lockName]);

                if ((int) $lockStmt->fetchColumn() !== 1) {
                    throw new RuntimeException(
                        'Server is busy processing another request for this account. Please try again.'
                    );
                }

                try {

                    $maxListings = (int) ($data['max_listings'] ?? 0);

                    if ($maxListings > 0 && $this->countListingsByLandlord($landlordId) >= $maxListings) {
                        throw new RuntimeException(
                            "You've reached the {$maxListings}-listing limit for your plan."
                        );
                    }

                    /*
                    * -----------------------------------------
                    * MEDIA INPUT VALIDATION
                    * -----------------------------------------
                    *
                    * The frontend should never send both.
                    * We enforce the same rule on the backend.
                    */

                    $images = $data['images'] ?? [];
                    $video = $data['video'] ?? null;

                    if (!is_array($images)) {
                        $images = [];
                    }

                    if (!empty($images) && !empty($video)) {
                        throw new RuntimeException(
                            'A property cannot contain both images and a video.'
                        );
                    }

                    /*
                    * -----------------------------------------
                    * START TRANSACTION
                    * -----------------------------------------
                    */

                    $this->conn->beginTransaction();

                    /*
                    * -----------------------------------------
                    * INSERT HOUSE
                    * -----------------------------------------
                    */

                    $query = "
                        INSERT INTO {$this->table}
                        (
                            title,
                            description,
                            price,
                            location,
                            latitude,
                            longitude,
                            bedrooms,
                            bathrooms,
                            house_type,
                            landlord_id,
                            rating,
                            has_parking,
                            status
                        )
                        VALUES
                        (
                            :title,
                            :description,
                            :price,
                            :location,
                            :latitude,
                            :longitude,
                            :bedrooms,
                            :bathrooms,
                            :house_type,
                            :landlord_id,
                            :rating,
                            :has_parking,
                            'available'
                        )
                    ";

                    $stmt = $this->conn->prepare($query);

                    $stmt->execute([
                        ':title' => $title,
                        ':description' => $description,
                        ':price' => $price,
                        ':location' => $location,
                        ':latitude' => $latitude,
                        ':longitude' => $longitude,
                        ':bedrooms' => $bedrooms,
                        ':bathrooms' => $bathrooms,
                        ':house_type' => $houseType,
                        ':landlord_id' => $landlordId,
                        ':rating' => $rating,
                        ':has_parking' => $hasParking
                    ]);

                    $houseId = (int) $this->conn->lastInsertId();

                    /*
                    * -----------------------------------------
                    * PROCESS IMAGES
                    * -----------------------------------------
                    */

                    $imageJobs = [];

                    if (!empty($images)) {

                        foreach ($images as $file) {

                            if (
                                !isset($file['tmp_name'])
                                ||
                                !isset($file['error'])
                                ||
                                $file['error'] === UPLOAD_ERR_NO_FILE
                            ) {
                                continue;
                            }

                            $staged = $this->media->stageImage($file);
                            $stagedFiles[] = $staged['staged_path'];

                            $stmt = $this->conn->prepare("
                                INSERT INTO house_images
                                (
                                    house_id,
                                    image_path,
                                    status,
                                    staged_path
                                )
                                VALUES
                                (
                                    :house_id,
                                    :image_path,
                                    'processing',
                                    :staged_path
                                )
                            ");

                            $stmt->execute([
                                ':house_id' => $houseId,
                                ':image_path' => $staged['final_filename'],
                                ':staged_path' => $staged['staged_path']
                            ]);

                            $imageJobs[] = [
                                'media_id'       => (int) $this->conn->lastInsertId(),
                                'staged_path'    => $staged['staged_path'],
                                'final_filename' => $staged['final_filename'],
                            ];
                        }
                    }

                    /*
                    * -----------------------------------------
                    * PROCESS VIDEO
                    * -----------------------------------------
                    */

                    $videoJob = null;

                    if (!empty($video)) {

                        $staged = $this->media->stageVideo($video);

                        $stagedFiles[] = $staged['staged_path'];

                        $stmt = $this->conn->prepare("
                            INSERT INTO house_images
                            (
                                house_id,
                                image_path,
                                status,
                                staged_path
                            )
                            VALUES
                            (
                                :house_id,
                                :image_path,
                                'processing',
                                :staged_path
                            )
                        ");

                        $stmt->execute([
                            ':house_id' => $houseId,
                            ':image_path' => $staged['final_filename'],
                            ':staged_path' => $staged['staged_path']
                        ]);

                        $videoJob = [
                            'media_id'       => (int) $this->conn->lastInsertId(),
                            'house_id'       => $houseId,
                            'landlord_id'    => $landlordId,
                            'staged_path'    => $staged['staged_path'],
                            'final_filename' => $staged['final_filename'],
                        ];
                    }

                    /*
                    * -----------------------------------------
                    * COMMIT
                    * -----------------------------------------
                    */

                    $this->conn->commit();

                    // Filter dropdown data just changed — don't make
                    // tenants wait up to 5 minutes to see it.
                    try {
                        require_once __DIR__ . '/../config/RedisConnection.php';
                        RedisConnection::get()->del('cache:filter_meta');
                    } catch (Throwable $e) {
                        // Cache invalidation failing is not fatal — worst
                        // case the old data serves for up to 5 more minutes.
                    }

                    foreach ($imageJobs as $imageJob) {
                        try {
                            MediaJobPublisher::publishImageJob($imageJob);
                        } catch (Throwable $e) {
                            error_log('LUX EMPIRE image job publish failed: ' . $e->getMessage());

                            $failStmt = $this->conn->prepare("UPDATE house_images SET status = 'failed' WHERE id = :id");
                            $failStmt->execute([':id' => $imageJob['media_id']]);
                        }
                    }

                    if ($videoJob !== null) {
                        try {
                            MediaJobPublisher::publishVideoJob($videoJob);
                        } catch (Throwable $e) {
                            error_log('LUX EMPIRE media job publish failed: ' . $e->getMessage());

                            $failStmt = $this->conn->prepare("
                                UPDATE house_images SET status = 'failed'
                                WHERE id = :id
                            ");
                            $failStmt->execute([':id' => $videoJob['media_id']]);

                            // The job never reached the worker, so nothing
                            // will ever release this landlord's in-flight
                            // video slot unless we release it right here.
                            RedisThrottle::decrement("video:inflight:{$landlordId}");
                        }
                    }

                    return $houseId;

                } finally {

                    $releaseStmt = $this->conn->prepare('SELECT RELEASE_LOCK(:lock_name)');
                    $releaseStmt->execute([':lock_name' => $lockName]);
                }

            } catch (Throwable $e) {

                /*
                * -----------------------------------------
                * ROLLBACK DATABASE
                * -----------------------------------------
                */

                if ($this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }

                /*
                * -----------------------------------------
                * CLEAN GENERATED MEDIA
                * -----------------------------------------
                */

                foreach ($createdFiles as $filename) {

                    try {

                        $this->media->delete($filename);

                    } catch (Throwable $cleanupError) {

                        error_log(
                            'LUX EMPIRE media cleanup failed: '
                            . $cleanupError->getMessage()
                        );
                    }
                }

                foreach ($stagedFiles as $stagedPath) {
                    if (is_file($stagedPath)) {
                        @unlink($stagedPath);
                    }
                }

                throw new RuntimeException(
                    'Error creating house: '
                    . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

    /**
     * ATTACH MULTIPLE IMAGES
     */
    public function attachImages(
        int $houseId,
        array $files
    ): array {

        /*
         * A video already exists.
         * Images are therefore forbidden.
         */
        if ($this->hasVideo($houseId)) {

            throw new RuntimeException(
                'This property already has a video. '
                . 'Images cannot be added.'
            );
        }

        $uploaded = [];

        foreach ($files as $file) {

            if (
                !isset($file['tmp_name'])
                ||
                $file['error'] === UPLOAD_ERR_NO_FILE
            ) {
                continue;
            }

            $filename =
                $this->media->processImage($file);

            $stmt = $this->conn->prepare("
                INSERT INTO house_images
                (
                    house_id,
                    image_path
                )
                VALUES
                (
                    :house_id,
                    :image_path
                )
            ");

            $stmt->execute([
                ':house_id' => $houseId,
                ':image_path' => $filename
            ]);

            $uploaded[] = $filename;
        }

        return $uploaded;
    }

    /**
     * ATTACH ONE VIDEO
     */
    public function attachVideo(
        int $houseId,
        array $file
    ): string {

        /*
         * A property cannot contain both
         * images and video.
         */
        if ($this->hasImages($houseId)) {

            throw new RuntimeException(
                'This property already has images. '
                . 'Remove the images before adding a video.'
            );
        }

        /*
         * Only one video is allowed.
         */
        if ($this->hasVideo($houseId)) {

            throw new RuntimeException(
                'This property already has a video.'
            );
        }

        $filename =
            $this->media->processVideo($file);

        $stmt = $this->conn->prepare("
            INSERT INTO house_images
            (
                house_id,
                image_path
            )
            VALUES
            (
                :house_id,
                :image_path
            )
        ");

        $stmt->execute([
            ':house_id' => $houseId,
            ':image_path' => $filename
        ]);

        return $filename;
    }

    /**
     * CHECK WHETHER HOUSE HAS IMAGES
     */
    public function hasImages(int $houseId): bool
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM house_images
            WHERE house_id = :house_id
            AND image_path NOT LIKE '%.mp4'
        ");

        $stmt->execute([
            ':house_id' => $houseId
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * CHECK WHETHER HOUSE HAS VIDEO
     */
    public function hasVideo(int $houseId): bool
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM house_images
            WHERE house_id = :house_id
            AND image_path LIKE '%.mp4'
        ");

        $stmt->execute([
            ':house_id' => $houseId
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * GET ALL MEDIA
     */
    public function getHouseMedia(int $houseId): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                id,
                house_id,
                image_path,
                created_at
            FROM house_images
            WHERE house_id = :house_id
            ORDER BY id ASC
        ");

        $stmt->execute([
            ':house_id' => $houseId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * BATCH MEDIA FETCH — same data as getHouseMedia() but for many
     * houses in ONE query instead of one query per house. Use this
     * anywhere you're about to loop over a list of houses and call
     * getHouseMedia() inside the loop (that's an N+1 query bug).
     *
     * Returns [house_id => [media_row, media_row, ...], ...]
     */
    public function getMediaForHouseIds(array $houseIds): array
    {
        $houseIds = array_values(array_unique(array_map('intval', $houseIds)));

        if (empty($houseIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($houseIds), '?'));

        $stmt = $this->conn->prepare("
            SELECT id, house_id, image_path, status, created_at
            FROM house_images
            WHERE house_id IN ({$placeholders}) AND status = 'ready'
            ORDER BY house_id ASC, id ASC
        ");

        $stmt->execute($houseIds);

        $grouped = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $grouped[(int) $row['house_id']][] = $row;
        }

        // Every requested id gets an entry, even if it has zero media,
        // so callers can safely do $mediaByHouse[$id] ?? [] without checking.
        foreach ($houseIds as $id) {
            $grouped[$id] = $grouped[$id] ?? [];
        }

        return $grouped;
    }

    /**
     * Availability filter for everything a TENANT or GUEST can see.
     * A house the landlord has ACCEPTED (status 'booked') stops being
     * listed TENANT_BOOKED_VISIBLE_HOURS after booked_at. Available,
     * reserved and unavailable houses are not affected by this rule.
     */
    private function tenantVisibilitySql(string $alias = 'h'): string
    {
        $hours = defined('TENANT_BOOKED_VISIBLE_HOURS') ? (int) TENANT_BOOKED_VISIBLE_HOURS : 6;

        return "({$alias}.status != 'booked' OR {$alias}.booked_at IS NULL OR {$alias}.booked_at > (NOW() - INTERVAL {$hours} HOUR))";
    }

    /**
     * ORDER BY fragment: available houses first, everything that
     * can't be booked right now (reserved / booked / unavailable) last.
     */
    private function availabilityOrderSql(string $alias = 'h'): string
    {
        return "({$alias}.status <> 'available') ASC";
    }

    /**
     * GET ALL HOUSES
     *
     * Tenant/guest-facing list. Available houses first; accepted
     * (booked) houses disappear TENANT_BOOKED_VISIBLE_HOURS after
     * acceptance. Nothing is deleted here — purely a query filter.
     */
    public function getAllHouses(): array
    {
        $query = "
            SELECT
                h.*,

                u.full_name AS landlord_name,
                u.email AS landlord_email,
                u.phone AS landlord_phone,

                (
                    SELECT hi.image_path
                    FROM house_images hi
                    WHERE hi.house_id = h.id
                    ORDER BY hi.id ASC
                    LIMIT 1
                ) AS image

            FROM houses h

            JOIN users u
                ON h.landlord_id = u.id

            WHERE " . $this->tenantVisibilitySql('h') . "

            ORDER BY " . $this->availabilityOrderSql('h') . ", h.id DESC
        ";

        $stmt = $this->conn->prepare($query);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * GET HOUSES BY LANDLORD
     *
     * Available houses first, unavailable ones at the bottom.
     */
    public function getHousesByLandlord(
        int $landlordId
    ): array {

        $query = "
            SELECT
                h.*,

                (
                    SELECT hi.image_path
                    FROM house_images hi
                    WHERE hi.house_id = h.id
                    ORDER BY hi.id ASC
                    LIMIT 1
                ) AS image

            FROM houses h

            WHERE h.landlord_id = :landlord_id

            ORDER BY " . $this->availabilityOrderSql('h') . ", h.id DESC
        ";

        $stmt = $this->conn->prepare($query);

        $stmt->execute([
            ':landlord_id' => $landlordId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * GET SINGLE HOUSE
     */
    public function getHouseById(int $id): ?array
    {
        $query = "
            SELECT
                h.*,

                u.full_name AS landlord_name,
                u.email AS landlord_email,
                u.phone AS landlord_phone,

                (
                    SELECT hi.image_path
                    FROM house_images hi
                    WHERE hi.house_id = h.id
                    ORDER BY hi.id ASC
                    LIMIT 1
                ) AS image

            FROM houses h

            JOIN users u
                ON h.landlord_id = u.id

            WHERE h.id = :id

            LIMIT 1
        ";

        $stmt = $this->conn->prepare($query);

        $stmt->execute([
            ':id' => $id
        ]);

        $house = $stmt->fetch(PDO::FETCH_ASSOC);

        return $house ?: null;
    }

    /**
     * VERIFY HOUSE OWNERSHIP
     */
    public function belongsToLandlord(
        int $houseId,
        int $landlordId
    ): bool {

        $query = "
            SELECT id
            FROM {$this->table}
            WHERE id = :house_id
            AND landlord_id = :landlord_id
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($query);

        $stmt->execute([
            ':house_id' => $houseId,
            ':landlord_id' => $landlordId
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * How many houses this landlord currently has — used to enforce
     * the free-tier listing cap. An ACCEPTED (booked) house that has
     * passed LANDLORD_BOOKED_VISIBLE_HOURS no longer counts: it is
     * hidden from the landlord and only kept in the database for
     * history.
     */
    public function countListingsByLandlord(int $landlordId): int
    {
        $hours = defined('LANDLORD_BOOKED_VISIBLE_HOURS') ? (int) LANDLORD_BOOKED_VISIBLE_HOURS : 24;

        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM {$this->table}
            WHERE landlord_id = :landlord_id
            AND NOT (
                status = 'booked'
                AND booked_at IS NOT NULL
                AND booked_at < (NOW() - INTERVAL {$hours} HOUR)
            )
        ");
        $stmt->execute([':landlord_id' => $landlordId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * UPDATE HOUSE
     */
    public function updateHouse(
        int $id,
        array $data
    ): bool {

        /*
         * --------------------------------------------------------
         * UPDATE PROPERTY
         *
         * Media behavior:
         *
         * - No uploaded media:
         *     Existing media remains untouched.
         *
         * - New images:
         *     Existing media is replaced with the new images.
         *
         * - New video:
         *     Existing media is replaced with the new video.
         *
         * - Images + video:
         *     Rejected.
         *
         * New physical files are cleaned up if anything fails.
         * Existing physical files are removed only after the
         * database transaction successfully commits.
         * --------------------------------------------------------
         */

        $createdFiles = [];

        $stagedFiles = [];

        try {

            $title = trim($data['title'] ?? '');
            $description = trim($data['description'] ?? '');

            $price = (float) ($data['price'] ?? 0);

            $location = trim(
                $data['location'] ?? ''
            );

            $bedrooms = isset($data['bedrooms'])
                && $data['bedrooms'] !== ''
                ? (int) $data['bedrooms']
                : 0;

            $bathrooms = isset($data['bathrooms'])
                && $data['bathrooms'] !== ''
                ? (int) $data['bathrooms']
                : 0;

            $houseType = trim(
                $data['house_type'] ?? ''
            );

            $rating = isset($data['rating'])
                ? (int) $data['rating']
                : 5;

            $hasParking = !empty($data['has_parking']) ? 1 : 0;

            $latitude =
                ($data['latitude'] ?? '') !== ''
                ? (float) $data['latitude']
                : null;

            $longitude =
                ($data['longitude'] ?? '') !== ''
                ? (float) $data['longitude']
                : null;

            /*
             * ----------------------------------------------------
             * VALIDATION
             * ----------------------------------------------------
             */

            if ($id <= 0) {
                throw new InvalidArgumentException(
                    'Invalid property ID.'
                );
            }

            $ownerStmt = $this->conn->prepare("SELECT landlord_id FROM {$this->table} WHERE id = :id LIMIT 1");
            $ownerStmt->execute([':id' => $id]);
            $trueLandlordId = (int) $ownerStmt->fetchColumn();

            if ($title === '') {
                throw new InvalidArgumentException(
                    'Property title is required.'
                );
            }

            if ($price <= 0) {
                throw new InvalidArgumentException(
                    'Property price must be greater than zero.'
                );
            }

            if ($location === '') {
                throw new InvalidArgumentException(
                    'Property location is required.'
                );
            }

            if ($rating < 1 || $rating > 5) {
                throw new InvalidArgumentException(
                    'Rating must be between 1 and 5.'
                );
            }

            /*
             * ----------------------------------------------------
             * NORMALIZE MEDIA INPUT
             * ----------------------------------------------------
             */

            $files = $data['files'] ?? [];

            if (!is_array($files)) {
                $files = [];
            }

            $images = [];

            if (
                isset($files['images']) &&
                is_array($files['images']['name'] ?? null)
            ) {

                $imageFiles = $files['images'];

                foreach (
                    $imageFiles['name']
                    as $index => $name
                ) {

                    if (
                        !isset(
                            $imageFiles['tmp_name'][$index],
                            $imageFiles['error'][$index],
                            $imageFiles['size'][$index]
                        )
                    ) {
                        continue;
                    }

                    if (
                        $imageFiles['error'][$index]
                        === UPLOAD_ERR_NO_FILE
                    ) {
                        continue;
                    }

                    $images[] = [
                        'name' =>
                            $imageFiles['name'][$index],

                        'type' =>
                            $imageFiles['type'][$index] ?? '',

                        'tmp_name' =>
                            $imageFiles['tmp_name'][$index],

                        'error' =>
                            $imageFiles['error'][$index],

                        'size' =>
                            $imageFiles['size'][$index]
                    ];
                }
            }

            $video = null;

            if (
                isset($files['video']) &&
                is_array($files['video']) &&
                (
                    $files['video']['error']
                    ?? UPLOAD_ERR_NO_FILE
                ) !== UPLOAD_ERR_NO_FILE
            ) {

                $video = [
                    'name' =>
                        $files['video']['name'] ?? '',

                    'type' =>
                        $files['video']['type'] ?? '',

                    'tmp_name' =>
                        $files['video']['tmp_name'] ?? '',

                    'error' =>
                        $files['video']['error']
                        ?? UPLOAD_ERR_NO_FILE,

                    'size' =>
                        $files['video']['size'] ?? 0
                ];
            }

            if (
                !empty($images) &&
                $video !== null
            ) {
                throw new RuntimeException(
                    'A property can contain multiple images or one video, not both.'
                );
            }

            /*
             * ----------------------------------------------------
             * PROCESS NEW MEDIA FIRST
             *
             * This happens before deleting existing media.
             * ----------------------------------------------------
             */

            $imageJobs = [];

            if (!empty($images)) {

                foreach ($images as $file) {

                    $staged = $this->media->stageImage($file);
                    $stagedFiles[] = $staged['staged_path'];

                    $imageJobs[] = [
                        'final_filename' => $staged['final_filename'],
                        'staged_path'    => $staged['staged_path'],
                    ];
                }
            }

            $stagedVideo = null;

            if ($video !== null) {
                $stagedVideo = $this->media->stageVideo($video);
                $stagedFiles[] = $stagedVideo['staged_path'];
            }

            /*
             * ----------------------------------------------------
             * FETCH EXISTING MEDIA
             * ----------------------------------------------------
             */

            $existingMedia = [];

            if (
                !empty($images) ||
                $video !== null
            ) {

                $existingMedia =
                    $this->getHouseMedia($id);
            }

            /*
             * ----------------------------------------------------
             * DATABASE TRANSACTION
             * ----------------------------------------------------
             */

            $this->conn->beginTransaction();

            /*
             * ----------------------------------------------------
             * UPDATE HOUSE
             * ----------------------------------------------------
             */

            $query = "
                UPDATE {$this->table}
                SET
                    title = :title,
                    description = :description,
                    price = :price,
                    location = :location,
                    latitude = :latitude,
                    longitude = :longitude,
                    bedrooms = :bedrooms,
                    bathrooms = :bathrooms,
                    house_type = :house_type,
                    rating = :rating,
                    has_parking = :has_parking
                WHERE id = :id
            ";

            $stmt = $this->conn->prepare($query);

            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':price' => $price,
                ':location' => $location,
                ':latitude' => $latitude,
                ':longitude' => $longitude,
                ':bedrooms' => $bedrooms,
                ':bathrooms' => $bathrooms,
                ':house_type' => $houseType,
                ':rating' => $rating,
                ':has_parking' => $hasParking,
                ':id' => $id
            ]);

            /*
             * ----------------------------------------------------
             * REPLACE MEDIA
             * ----------------------------------------------------
             */

            if (
                !empty($images) ||
                $video !== null
            ) {

                $deleteMedia = $this->conn->prepare("
                    DELETE FROM house_images
                    WHERE house_id = :house_id
                ");

                $deleteMedia->execute([
                    ':house_id' => $id
                ]);

                /*
                 * Insert image rows as "processing" — actual
                 * compression queued after commit, below.
                 */

                foreach ($imageJobs as &$imageJob) {

                    $insert = $this->conn->prepare("
                        INSERT INTO house_images
                        (
                            house_id,
                            image_path,
                            status,
                            staged_path
                        )
                        VALUES
                        (
                            :house_id,
                            :image_path,
                            'processing',
                            :staged_path
                        )
                    ");

                    $insert->execute([
                        ':house_id' => $id,
                        ':image_path' => $imageJob['final_filename'],
                        ':staged_path' => $imageJob['staged_path']
                    ]);

                    $imageJob['media_id'] = (int) $this->conn->lastInsertId();
                }
                unset($imageJob);

                /*
                 * Insert video row as "processing" — the actual
                 * transcode is queued after commit, below.
                 */

                $videoJob = null;

                if ($stagedVideo !== null) {

                    $insert = $this->conn->prepare("
                        INSERT INTO house_images
                        (
                            house_id,
                            image_path,
                            status,
                            staged_path
                        )
                        VALUES
                        (
                            :house_id,
                            :image_path,
                            'processing',
                            :staged_path
                        )
                    ");

                    $insert->execute([
                        ':house_id' => $id,
                        ':image_path' => $stagedVideo['final_filename'],
                        ':staged_path' => $stagedVideo['staged_path']
                    ]);

                    $videoJob = [
                        'media_id'       => (int) $this->conn->lastInsertId(),
                        'house_id'       => $id,
                        'landlord_id'    => $trueLandlordId,
                        'staged_path'    => $stagedVideo['staged_path'],
                        'final_filename' => $stagedVideo['final_filename'],
                    ];
                }
            }

            /*
             * ----------------------------------------------------
             * COMMIT
             * ----------------------------------------------------
             */

            $this->conn->commit();

            // Filter dropdown data AND this specific house's detail
            // cache just changed — clear both rather than waiting
            // out their TTLs.
            try {
                require_once __DIR__ . '/../config/RedisConnection.php';
                $redis = RedisConnection::get();
                $redis->del('cache:filter_meta');
                $redis->del("cache:house:{$id}");
            } catch (Throwable $e) {
                // Cache invalidation failing is not fatal — worst
                // case the old data serves until its TTL expires.
            }

            foreach ($imageJobs as $imageJob) {
                if (!isset($imageJob['media_id'])) {
                    continue; // no new images were actually part of this update
                }
                try {
                    MediaJobPublisher::publishImageJob($imageJob);
                } catch (Throwable $e) {
                    error_log('LUX EMPIRE image job publish failed: ' . $e->getMessage());
                    $failStmt = $this->conn->prepare("UPDATE house_images SET status = 'failed' WHERE id = :id");
                    $failStmt->execute([':id' => $imageJob['media_id']]);
                }
            }

            if (isset($videoJob) && $videoJob !== null) {
                try {
                    MediaJobPublisher::publishVideoJob($videoJob);
                } catch (Throwable $e) {
                    error_log('LUX EMPIRE media job publish failed: ' . $e->getMessage());

                    $failStmt = $this->conn->prepare("
                        UPDATE house_images SET status = 'failed'
                        WHERE id = :id
                    ");
                    $failStmt->execute([':id' => $videoJob['media_id']]);

                    // The job never reached the worker, so nothing will
                    // ever release this in-flight video slot unless we
                    // release it right here. Keyed by the REQUESTING
                    // user, not $trueLandlordId — those differ when an
                    // admin edits someone else's listing, and it's the
                    // requester's throttle bucket that update_house.php
                    // actually incremented.
                    $throttleOwnerId = (int) ($data['requesting_user_id'] ?? $trueLandlordId);
                    RedisThrottle::decrement("video:inflight:{$throttleOwnerId}");
                }
            }

            /*
             * ----------------------------------------------------
             * DELETE OLD PHYSICAL MEDIA
             *
             * Only after successful DB commit.
             * ----------------------------------------------------
             */

            foreach ($existingMedia as $media) {

                try {

                    $this->media->delete(
                        $media['image_path'] ?? ''
                    );

                } catch (Throwable $cleanupError) {

                    error_log(
                        'LUX EMPIRE old media cleanup failed: '
                        . $cleanupError->getMessage()
                    );
                }
            }

            return true;

        } catch (Throwable $e) {

            /*
             * ----------------------------------------------------
             * DATABASE ROLLBACK
             * ----------------------------------------------------
             */

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            /*
             * ----------------------------------------------------
             * REMOVE NEWLY GENERATED MEDIA
             * ----------------------------------------------------
             */

            foreach ($createdFiles as $filename) {

                try {

                    $this->media->delete($filename);

                } catch (Throwable $cleanupError) {

                    error_log(
                        'LUX EMPIRE new media cleanup failed: '
                        . $cleanupError->getMessage()
                    );
                }
            }

            foreach ($stagedFiles as $stagedPath) {
                if (is_file($stagedPath)) {
                    @unlink($stagedPath);
                }
            }

            throw new RuntimeException(
                'Error updating house: '
                . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * DELETE MEDIA
     *
     * Removes the database record and
     * the physical file.
     */
    public function deleteMedia(int $mediaId): bool
    {
        $stmt = $this->conn->prepare("
            SELECT image_path
            FROM house_images
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $mediaId
        ]);

        $media = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$media) {
            return false;
        }

        $deleted = $this->conn->prepare("
            DELETE FROM house_images
            WHERE id = :id
        ");

        $deleted->execute([
            ':id' => $mediaId
        ]);

        if ($deleted->rowCount() > 0) {

            $this->media->delete(
                $media['image_path']
            );

            return true;
        }

        return false;
    }

    /**
     * DELETE HOUSE
     */
    public function deleteHouse(int $id): bool
    {
        /*
         * Get media before deleting the house because
         * ON DELETE CASCADE will remove the database
         * records.
         */
        $media = $this->getHouseMedia($id);

        $query = "
            DELETE FROM {$this->table}
            WHERE id = :id
        ";

        $stmt = $this->conn->prepare($query);

        $stmt->execute([
            ':id' => $id
        ]);

        // Filter dropdown data AND this specific house's detail
        // cache just changed — clear both rather than waiting
        // out their TTLs.
        try {
        require_once __DIR__ . '/../config/RedisConnection.php';
        $redis = RedisConnection::get();
        $redis->del('cache:filter_meta');
        $redis->del("cache:house:{$id}");
        } catch (Throwable $e) {
        // Cache invalidation failing is not fatal — worst
        // case the old data serves until its TTL expires.
        }

        if ($stmt->rowCount() === 0) {
            return false;
        }

        /*
         * Delete physical files.
         */
        foreach ($media as $item) {

            $this->media->delete(
                $item['image_path']
            );
        }

        return true;
    }

    /**
     * SEARCH HOUSES
     *
     * Same visibility + ordering rules as getAllHouses().
     */
    public function searchHouses(
        string $keyword
    ): array {

        $query = "
            SELECT
                h.*,

                u.full_name AS landlord_name,
                u.email AS landlord_email,
                u.phone AS landlord_phone,

                (
                    SELECT hi.image_path
                    FROM house_images hi
                    WHERE hi.house_id = h.id
                    ORDER BY hi.id ASC
                    LIMIT 1
                ) AS image

            FROM houses h

            JOIN users u
                ON h.landlord_id = u.id

            WHERE
            (
                h.title LIKE :title
                OR h.location LIKE :location
                OR h.description LIKE :description
            )
            AND " . $this->tenantVisibilitySql('h') . "

            ORDER BY " . $this->availabilityOrderSql('h') . ", h.id DESC
        ";

        $stmt = $this->conn->prepare($query);

        $search = '%' . $keyword . '%';

        $stmt->execute([
            ':title' => $search,
            ':location' => $search,
            ':description' => $search
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * PRICE BOUNDS
     *
     * Feeds the filter modal's range-slider min/max.
     */
    public function getPriceBounds(): array
    {
        $stmt = $this->conn->query("
            SELECT
                MIN(price) AS min_price,
                MAX(price) AS max_price
            FROM houses
            WHERE " . $this->tenantVisibilitySql('houses') . "
        ");

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'min' => $row['min_price'] !== null ? (float) $row['min_price'] : 0.0,
            'max' => $row['max_price'] !== null ? (float) $row['max_price'] : 0.0,
        ];
    }

    /**
     * DISTINCT HOUSE TYPES — for the filter dropdown, DB-driven not hardcoded.
     */
    public function getDistinctHouseTypes(): array
    {
        $stmt = $this->conn->query("
            SELECT DISTINCT house_type
            FROM houses
            WHERE house_type IS NOT NULL AND house_type != ''
            ORDER BY house_type ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * DISTINCT LOCATIONS — for the filter datalist, DB-driven not hardcoded.
     */
    public function getDistinctLocations(): array
    {
        $stmt = $this->conn->query("
            SELECT DISTINCT location
            FROM houses
            WHERE location IS NOT NULL AND location != ''
            ORDER BY location ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getInstitutions(): array
    {
        $stmt = $this->conn->query("
            SELECT id, name, type, latitude, longitude
            FROM institutions
            ORDER BY name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function filterHouses(array $filters, int $limit = 12, int $offset = 0): array
    {
        if ($offset > 0) {
            $useFilters = ($filters['_mode'] ?? 'exact') === 'relaxed'
                ? $this->relaxFilters($filters)[0]
                : $filters;

            $page = $this->runFilterQuery($useFilters, $limit, $offset);

            return [
                'houses'      => $page['rows'],
                'total'       => $page['total'],
                'exact_match' => ($filters['_mode'] ?? 'exact') !== 'relaxed',
                'relaxed'     => [],
            ];
        }

        $exact = $this->runFilterQuery($filters, $limit, $offset);

        if ($exact['total'] > 0) {
            return [
                'houses'      => $exact['rows'],
                'total'       => $exact['total'],
                'exact_match' => true,
                'relaxed'     => [],
            ];
        }

        [$relaxedFilters, $relaxedFields] = $this->relaxFilters($filters);

        if (empty($relaxedFields)) {
            return [
                'houses'      => [],
                'total'       => 0,
                'exact_match' => true,
                'relaxed'     => [],
            ];
        }

        $broad = $this->runFilterQuery($relaxedFilters, $limit, $offset);

        return [
            'houses'      => $broad['rows'],
            'total'       => $broad['total'],
            'exact_match' => false,
            'relaxed'     => $relaxedFields,
        ];
    }

    /**
     * v1 heuristic, in order of least disruptive first:
     *   1. widen price ceiling by 20%
     *   2. widen the proximity radius by 50% (min +2km)
     *   3. drop house_type entirely
     * location/bedrooms/bathrooms/keyword stay untouched — hard constraints.
     */
    private function relaxFilters(array $filters): array
    {
        $relaxed = $filters;
        $touched = [];

        if (!empty($filters['max_price'])) {
            $relaxed['max_price'] = round(((float) $filters['max_price']) * 1.20, 2);
            $touched[] = 'max_price';
        }

        if (!empty($filters['institution_id']) && !empty($filters['max_distance_km'])) {
            $currentRadius = (float) $filters['max_distance_km'];
            $relaxed['max_distance_km'] = $currentRadius + max($currentRadius * 0.5, 2);
            $touched[] = 'max_distance_km';
        }

        if (!empty($filters['house_type'])) {
            unset($relaxed['house_type']);
            $touched[] = 'house_type';
        }

        return [$relaxed, $touched];
    }

    private function runFilterQuery(array $filters, int $limit, int $offset): array
    {
        $where = [
            $this->tenantVisibilitySql('h'),
            "h.is_hidden = 0"
        ];
        $params = [];
        $distanceSelect = '';

        if (!empty($filters['keyword'])) {
            // Real prepared statements (PDO::ATTR_EMULATE_PREPARES is
            // false) cannot bind one value to the same named
            // placeholder used more than once — each occurrence needs
            // its own name, all bound to the same value. Same fix
            // already applied below for :inst_lat/:inst_lat2.
            $where[] = "(h.title LIKE :keyword_title OR h.location LIKE :keyword_location OR h.description LIKE :keyword_description)";
            $keywordValue = '%' . $filters['keyword'] . '%';
            $params[':keyword_title'] = $keywordValue;
            $params[':keyword_location'] = $keywordValue;
            $params[':keyword_description'] = $keywordValue;
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $where[] = "h.price >= :min_price";
            $params[':min_price'] = (float) $filters['min_price'];
        }

        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $where[] = "h.price <= :max_price";
            $params[':max_price'] = (float) $filters['max_price'];
        }

        if (!empty($filters['house_type'])) {
            $where[] = "h.house_type = :house_type";
            $params[':house_type'] = $filters['house_type'];
        }

        if (!empty($filters['location'])) {
            $where[] = "h.location LIKE :location";
            $params[':location'] = '%' . $filters['location'] . '%';
        }

        if (isset($filters['bedrooms']) && $filters['bedrooms'] !== '') {
            $where[] = "h.bedrooms >= :bedrooms";
            $params[':bedrooms'] = (int) $filters['bedrooms'];
        }

        if (isset($filters['bathrooms']) && $filters['bathrooms'] !== '') {
            $where[] = "h.bathrooms >= :bathrooms";
            $params[':bathrooms'] = (int) $filters['bathrooms'];
        }

        if (!empty($filters['institution_id']) && !empty($filters['max_distance_km'])) {

            $institution = $this->getInstitutionById((int) $filters['institution_id']);

            if ($institution !== null) {

                // Haversine distance in km — computed live against the
                // house's own lat/lng, never pre-stored (either point
                // can move, and this stays cheap at our current scale).
                //
                // BUGFIX: :inst_lat was previously used TWICE in this
                // expression but bound only once — works under PDO's
                // emulated-prepares mode, but throws SQLSTATE[HY093]
                // "Invalid parameter number" the moment emulation is
                // off, since a real prepared statement can't map one
                // bound value onto two placeholder occurrences. Fixed
                // by giving the second occurrence its own name, bound
                // to the same value.
                $distanceExpr = "(6371 * acos(
                    cos(radians(:inst_lat)) * cos(radians(h.latitude))
                    * cos(radians(h.longitude) - radians(:inst_lng))
                    + sin(radians(:inst_lat2)) * sin(radians(h.latitude))
                ))";

                $where[] = "h.latitude IS NOT NULL AND h.longitude IS NOT NULL";
                $where[] = "{$distanceExpr} <= :max_distance_km";

                $params[':inst_lat'] = $institution['latitude'];
                $params[':inst_lat2'] = $institution['latitude'];
                $params[':inst_lng'] = $institution['longitude'];
                $params[':max_distance_km'] = (float) $filters['max_distance_km'];

                $distanceSelect = ", {$distanceExpr} AS distance_km";
            }
        }

        $whereSql = implode(' AND ', $where);
        $sortSql = $this->availabilityOrderSql('h') . ', ' . $this->resolveSort($filters['sort'] ?? 'newest');

        $countStmt = $this->conn->prepare("SELECT COUNT(*) FROM houses h WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->conn->prepare("
            SELECT
                h.*,
                u.full_name AS landlord_name,
                u.email AS landlord_email,
                u.phone AS landlord_phone,
                (
                    SELECT hi.image_path FROM house_images hi
                    WHERE hi.house_id = h.id ORDER BY hi.id ASC LIMIT 1
                ) AS image
                {$distanceSelect}
            FROM houses h
            JOIN users u ON h.landlord_id = u.id
            WHERE {$whereSql}
            ORDER BY {$sortSql}
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows'  => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    private function getInstitutionById(int $id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT id, name, latitude, longitude
            FROM institutions
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function resolveSort(string $sort): string
    {
        return match ($sort) {
            'price_asc'  => 'h.price ASC',
            'price_desc' => 'h.price DESC',
            'oldest'     => 'h.id ASC',
            default      => 'h.id DESC',
        };
    }
}