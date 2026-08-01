<?php
/**
 * LUX EMPIRE Database Connection
 */

require_once __DIR__ . '/app.php';

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    public $conn;

    public function connect() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);

        } catch (PDOException $exception) {
            error_log(
                "[" . date("Y-m-d H:i:s") . "] DB Connection Error: " . $exception->getMessage() . PHP_EOL,
                3,
                $_SERVER['DOCUMENT_ROOT'] . '/house_truck_platform/logs/error.log'
            );

            die("LUX EMPIRE system connection failed.");
        }

        return $this->conn;
    }
}
?>