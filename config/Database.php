<?php
class Database
{
    private $host = "localhost";
    private $db_name = "uasdb";
    private $username = "root";
    private $password = "";
    public $conn;
    public function connect()
    {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->exec("set names utf8");
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(["error" => "Connection Error: " . $e->getMessage()]);
            exit;
        }
        return $this->conn;
    }
}
