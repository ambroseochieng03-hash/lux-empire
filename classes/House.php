<?php

require_once __DIR__ . '/../config/db.php';

class House {

    private $conn;
    private $table = "houses";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * CREATE HOUSE (Landlord)
     */
    public function createHouse($data)
    {
        try {

            // =====================================
            // SAFE TYPE NORMALIZATION (CRITICAL)
            // =====================================

            $title = trim($data['title'] ?? '');
            $description = trim($data['description'] ?? '');
            $price = (float)($data['price'] ?? 0);
            $location = trim($data['location'] ?? '');

            $bedrooms = isset($data['bedrooms']) && $data['bedrooms'] !== ''
                ? (int)$data['bedrooms']
                : 1;

            $bathrooms = isset($data['bathrooms']) && $data['bathrooms'] !== ''
                ? (int)$data['bathrooms']
                : 1;

            $landlordId = (int)$data['landlord_id'];

            // =====================================
            // INSERT HOUSE
            // =====================================

            $query = "INSERT INTO houses
            (title, description, price, location, bedrooms, bathrooms, landlord_id, rating, status)
            VALUES
            (:title, :description, :price, :location, :bedrooms, :bathrooms, :landlord_id, :rating, 'available')";

            $stmt = $this->conn->prepare($query);

            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':price' => $price,
                ':rating' => $rating,
                ':location' => $location,
                ':bedrooms' => $bedrooms,
                ':bathrooms' => $bathrooms,
                ':landlord_id' => $landlordId,
                ':rating' => (int)($data['rating'] ?? 0)
            ]);

            // =====================================
            // GET HOUSE ID
            // =====================================

            $houseId = $this->conn->lastInsertId();

            // =====================================
            // INSERT IMAGE (OPTIONAL)
            // =====================================

            if (!empty($data['image'])) {

                $imgQuery = "INSERT INTO house_images
                (house_id, image_path)
                VALUES (:house_id, :image_path)";

                $imgStmt = $this->conn->prepare($imgQuery);

                $imgStmt->execute([
                    ':house_id' => $houseId,
                    ':image_path' => $data['image']
                ]);
            }

            return $houseId;

        } catch (PDOException $e) {
            die("Error creating house: " . $e->getMessage());
        }
    }

    /**
     * GET ALL HOUSES (Tenant search)
     */
    public function getAllHouses() {

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
                    LIMIT 1
                ) AS image

            FROM houses h

            JOIN users u
            ON h.landlord_id = u.id

            ORDER BY h.id DESC
        ";

        $stmt = $this->conn->prepare($query);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * GET HOUSES BY LANDLORD
     */
    public function getHousesByLandlord($landlord_id)
    {
        $query = "
            SELECT
                h.*,

                (
                    SELECT hi.image_path
                    FROM house_images hi
                    WHERE hi.house_id = h.id
                    LIMIT 1
                ) AS image

            FROM houses h

            WHERE h.landlord_id = :landlord_id

            ORDER BY h.id DESC
        ";

        $stmt = $this->conn->prepare($query);

        $stmt->execute([
            ':landlord_id' => $landlord_id
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * GET SINGLE HOUSE
     */
    public function getHouseById($id) {

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
                    LIMIT 1
                ) AS image

            FROM houses h
            JOIN users u ON h.landlord_id = u.id
            WHERE h.id = :id
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

        /**
     * VERIFY HOUSE OWNERSHIP
     */
    public function belongsToLandlord($house_id, $landlord_id) {

        $query = "SELECT id
                FROM " . $this->table . "
                WHERE id = :house_id
                AND landlord_id = :landlord_id
                LIMIT 1";

        $stmt = $this->conn->prepare($query);

        $stmt->execute([
            ':house_id' => $house_id,
            ':landlord_id' => $landlord_id
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * UPDATE HOUSE
     */
    public function updateHouse($id, $data) {

        $query = "UPDATE " . $this->table . "
                  SET title = :title,
                      description = :description,
                      price = :price,
                      location = :location,
                      bedrooms = :bedrooms,
                      bathrooms = :bathrooms,
                      image = :image
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        return $stmt->execute([
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':price' => $data['price'],
            ':location' => $data['location'],
            ':bedrooms' => $data['bedrooms'],
            ':bathrooms' => $data['bathrooms'],
            ':image' => $data['image'],
            ':id' => $id
        ]);
    }

    /**
     * DELETE HOUSE
     */
    public function deleteHouse($id) {

        $query = "DELETE FROM " . $this->table . " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }

    /**
     * SEARCH HOUSES
     */
    public function searchHouses($keyword) {

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
                    LIMIT 1
                ) AS image

            FROM houses h

            JOIN users u
            ON h.landlord_id = u.id

            WHERE h.title LIKE :title
            OR h.location LIKE :location
            OR h.description LIKE :description

            ORDER BY h.id DESC
        ";

        $stmt = $this->conn->prepare($query);

        $search = "%" . $keyword . "%";

        $stmt->execute([
            ':title' => $search,
            ':location' => $search,
            ':description' => $search
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>