<?php
require_once "../config/Database.php";
require_once "../models/User.php";

class UserController {
    private $user;
    public function __construct() {
        $database = new Database();
        $db = $database->connect();
        $this->user = new User($db);
    }

    public function index() {
        try {
            // Mengambil role dari header HTTP_X_USER_ROLE
            $role = $_SERVER['HTTP_X_USER_ROLE'] ?? '';

            // PROTEKSI: Sesuai tabel, Daftar User hanya untuk Admin
            if ($role !== 'admin') {
                http_response_code(403);
                echo json_encode([
                    "success" => false,
                    "message" => "Akses Ditolak: Hanya Admin yang diizinkan mengakses daftar user."
                ]);
                return;
            }

            $data = $this->user->getAll();
            echo json_encode($data ?: []);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => $e->getMessage()]);
        }
    }

    public function store() {
        try {
            // Store biasanya digunakan untuk registrasi publik atau admin tambah user
            $data = json_decode(file_get_contents("php://input"), true);
            if (!$data) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "No data provided"]);
                return;
            }
            $result = $this->user->insert($data);

            echo json_encode([
                "success" => $result
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }
}
