<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/MediaService.php';

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

        try {

            $title = trim($data['title'] ?? '');
            $description = trim($data['description'] ?? '');
            $price = (float) ($data['price'] ?? 0);
            $location = trim($data['location'] ?? '');

            $bedrooms = isset($data['bedrooms'])
                && $data['bedrooms'] !== ''
                ? (int) $data['bedrooms']
                : 1;

            $bathrooms = isset($data['bathrooms'])
                && $data['bathrooms'] !== ''
                ? (int) $data['bathrooms']
                : 1;

            $houseType = trim($data['house_type'] ?? '');

            $rating = isset($data['rating'])
                ? (int) $data['rating']
                : 0;

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
                ':rating' => $rating
            ]);

            $houseId = (int) $this->conn->lastInsertId();

            /*
            * -----------------------------------------
            * PROCESS IMAGES
            * -----------------------------------------
            */

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

                    $filename =
                        $this->media->processImage($file);

                    $createdFiles[] = $filename;

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
                }
            }

            /*
            * -----------------------------------------
            * PROCESS VIDEO
            * -----------------------------------------
            */

            if (!empty($video)) {

                $filename =
                    $this->media->processVideo($video);

                $createdFiles[] = $filename;

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
            }

            /*
            * -----------------------------------------
            * COMMIT
            * -----------------------------------------
            */

            $this->conn->commit();

            return $houseId;

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
            *
            * Database rollback cannot remove physical
            * files created by MediaService, so clean
            * them manually.
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
     * GET ALL HOUSES
     *
     * Excludes houses whose 12-hour post-acceptance visibility
     * window has elapsed (status = 'booked' AND booked_at is more
     * than 12 hours ago). Nothing is deleted — this is purely a
     * listing-query filter, per the agreed booked_at + render-time
     * check approach. Houses that are 'available', 'rented', or
     * 'booked' within the last 12 hours are still returned.
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

            WHERE (
                h.status != 'booked'
                OR h.booked_at IS NULL
                OR h.booked_at > (NOW() - INTERVAL 12 HOUR)
            )

            ORDER BY h.id DESC
        ";

        $stmt = $this->conn->prepare($query);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * GET HOUSES BY LANDLORD
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

            ORDER BY h.id DESC
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
                : 1;

            $bathrooms = isset($data['bathrooms'])
                && $data['bathrooms'] !== ''
                ? (int) $data['bathrooms']
                : 1;

            $houseType = trim(
                $data['house_type'] ?? ''
            );

            $rating = isset($data['rating'])
                ? (int) $data['rating']
                : 5;

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

            if ($bedrooms < 1) {
                throw new InvalidArgumentException(
                    'Bedrooms must be at least 1.'
                );
            }

            if ($bathrooms < 1) {
                throw new InvalidArgumentException(
                    'Bathrooms must be at least 1.'
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

            if (!empty($images)) {

                foreach ($images as $file) {

                    $filename =
                        $this->media->processImage($file);

                    $createdFiles[] = $filename;
                }
            }

            if ($video !== null) {

                $filename =
                    $this->media->processVideo($video);

                $createdFiles[] = $filename;
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
                    rating = :rating
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
                 * Insert processed images.
                 */

                foreach ($images as $index => $file) {

                    $filename = $createdFiles[$index];

                    $insert = $this->conn->prepare("
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

                    $insert->execute([
                        ':house_id' => $id,
                        ':image_path' => $filename
                    ]);
                }

                /*
                 * Insert processed video.
                 */

                if ($video !== null) {

                    $videoFilename =
                        $createdFiles[count($createdFiles) - 1];

                    $insert = $this->conn->prepare("
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

                    $insert->execute([
                        ':house_id' => $id,
                        ':image_path' => $videoFilename
                    ]);
                }
            }

            /*
             * ----------------------------------------------------
             * COMMIT
             * ----------------------------------------------------
             */

            $this->conn->commit();

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
     * Same 12-hour lifecycle exclusion as getAllHouses() — search
     * must keep working (per spec) and must stay consistent with
     * the main listing.
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
            AND (
                h.status != 'booked'
                OR h.booked_at IS NULL
                OR h.booked_at > (NOW() - INTERVAL 12 HOUR)
            )

            ORDER BY h.id DESC
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
            WHERE status != 'booked'
            OR booked_at IS NULL
            OR booked_at > (NOW() - INTERVAL 12 HOUR)
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
            "(h.status != 'booked' OR h.booked_at IS NULL OR h.booked_at > (NOW() - INTERVAL 12 HOUR))"
        ];
        $params = [];
        $distanceSelect = '';

        if (!empty($filters['keyword'])) {
            $where[] = "(h.title LIKE :keyword OR h.location LIKE :keyword OR h.description LIKE :keyword)";
            $params[':keyword'] = '%' . $filters['keyword'] . '%';
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
                $distanceExpr = "(6371 * acos(
                    cos(radians(:inst_lat)) * cos(radians(h.latitude))
                    * cos(radians(h.longitude) - radians(:inst_lng))
                    + sin(radians(:inst_lat)) * sin(radians(h.latitude))
                ))";

                $where[] = "h.latitude IS NOT NULL AND h.longitude IS NOT NULL";
                $where[] = "{$distanceExpr} <= :max_distance_km";

                $params[':inst_lat'] = $institution['latitude'];
                $params[':inst_lng'] = $institution['longitude'];
                $params[':max_distance_km'] = (float) $filters['max_distance_km'];

                $distanceSelect = ", {$distanceExpr} AS distance_km";
            }
        }

        $whereSql = implode(' AND ', $where);
        $sortSql = $this->resolveSort($filters['sort'] ?? 'newest');

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