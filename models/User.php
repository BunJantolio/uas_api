<?php
class User {
    private $conn;
    private $table = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    // ================= GET ALL =================
    public function getAll() {
        // Menggunakan alias agar sesuai dengan Model User di Kotlin (id dan name)
        $query = "SELECT id_user as id, username as name, username, password, role FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================= INSERT =================
    public function insert($data) {
        try {
            $this->conn->beginTransaction();

            // 1. Insert ke tabel users
            $query = "INSERT INTO " . $this->table . " (username, email, password, role) VALUES (:username, :email, :password, :role)";
            $stmt = $this->conn->prepare($query);

            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
            // Gunakan username + unik ID untuk email agar tidak terjadi Integrity constraint violation
            $email = $data['username'] . "_" . time() . "@uasweb.com";

            $stmt->execute([
                ':username' => $data['username'],
                ':email'    => $email,
                ':password' => $passwordHash,
                ':role'     => $data['role']
            ]);

            $id_user = $this->conn->lastInsertId();

            // 2. OTOMATIS Buat data dasar di tabel role masing-masing (Sesuai Skema SQL)
            if ($data['role'] === 'pasien') {
                $noRM = "RM-" . time();
                $nik = substr(str_shuffle("0123456789"), 0, 16); // Dummy NIK 16 digit
                $stmtPasien = $this->conn->prepare("INSERT INTO pasien (id_user, no_rekam_medis, nama_lengkap, nik, tempat_lahir, tanggal_lahir, jenis_kelamin, alamat, no_telepon)
                                                   VALUES (?, ?, ?, ?, 'Jakarta', '2000-01-01', 'L', 'Alamat Default', '0812')");
                $stmtPasien->execute([$id_user, $noRM, $data['username'], $nik]);
            }
            else if ($data['role'] === 'admin') {
                $nip = "ADM-" . time();
                $stmtAdmin = $this->conn->prepare("INSERT INTO admin (id_user, nama_lengkap, nip, no_telepon) VALUES (?, ?, ?, '0811')");
                $stmtAdmin->execute([$id_user, $data['username'], $nip]);
            }
            else if ($data['role'] === 'dokter') {
                $nip = "DKT-" . time();
                $str = "STR-" . time();
                $stmtDokter = $this->conn->prepare("INSERT INTO dokter (id_user, nama_lengkap, nip, spesialisasi, no_str, no_telepon) VALUES (?, ?, ?, 'Umum', ?, '0813')");
                $stmtDokter->execute([$id_user, $data['username'], $nip, $str]);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log($e->getMessage());
            return false;
        }
    }

    // ================= LOGIN =================
    public function login($username, $password) {
        $query = "SELECT id_user as id, username as name, username, password, role FROM " . $this->table . "
                WHERE username = :username";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':username' => $username]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            return $user;
        }
        return false;
    }
}
