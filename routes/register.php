<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type, X-User-Role, X-User-Id");

$host = "localhost";
$username = "root";
$password = "";
$dbname = "uasdb";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Koneksi Gagal: " . $e->getMessage()]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id_user = $data['id_pasien']; // ID yang dikirim Android sebenarnya id_user

    try {
        $conn->beginTransaction();

        // 1. CARI ID_PASIEN DARI TABEL PASIEN
        $stmtPasien = $conn->prepare("SELECT id_pasien FROM pasien WHERE id_user = ?");
        $stmtPasien->execute([$id_user]);
        $pasien = $stmtPasien->fetch(PDO::FETCH_ASSOC);

        if (!$pasien) {
            // Jika belum terdaftar sebagai pasien, buat data dummy agar pendaftaran berhasil
            $noRM = "RM-" . time();
            $nik = "123456" . time();
            $nama = $data['nama_pasien'] ?? 'Pasien Baru';
            
            $stmtInsertPasien = $conn->prepare("INSERT INTO pasien (id_user, no_rekam_medis, nama_lengkap, nik, tempat_lahir, tanggal_lahir, jenis_kelamin, alamat, no_telepon) 
                                                VALUES (?, ?, ?, ?, 'Jakarta', '2000-01-01', 'L', 'Alamat Dummy', '0812')");
            $stmtInsertPasien->execute([$id_user, $noRM, $nama, $nik]);
            $id_pasien = $conn->lastInsertId();
        } else {
            $id_pasien = $pasien['id_pasien'];
        }

        // 2. AMBIL ID_ADMIN & ID_DOKTER PERTAMA (Karena di skema NOT NULL)
        $id_admin = $conn->query("SELECT id_admin FROM admin LIMIT 1")->fetchColumn();
        $id_dokter = $conn->query("SELECT id_dokter FROM dokter LIMIT 1")->fetchColumn();

        if (!$id_admin || !$id_dokter) {
            throw new Exception("Data Admin atau Dokter belum tersedia di database. Mohon isi tabel admin & dokter terlebih dahulu.");
        }

        // 3. PROSES INSERT PENDAFTARAN
        $dateParts = explode('/', $data['tanggal_daftar']);
        $formattedDate = (count($dateParts) == 3) ? "$dateParts[2]-$dateParts[1]-$dateParts[0]" : date('Y-m-d');

        $stmtCount = $conn->prepare("SELECT COUNT(*) FROM pendaftaran WHERE tanggal_daftar = ?");
        $stmtCount->execute([$formattedDate]);
        $count = $stmtCount->fetchColumn();
        $noAntrian = "A" . str_pad($count + 1, 3, "0", STR_PAD_LEFT);

        $sql = "INSERT INTO pendaftaran (id_pasien, id_admin, id_dokter, no_antrian, poli, tanggal_daftar, keluhan, status) 
                VALUES (?, ?, ?, ?, 'Umum', ?, ?, 'menunggu')";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id_pasien, $id_admin, $id_dokter, $noAntrian, $formattedDate, $data['keluhan']]);

        $conn->commit();
        echo json_encode(["message" => "Berhasil", "no_antrian" => $noAntrian]);
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
} elseif ($method == 'GET') {
    // GET logic tetap sama, disesuaikan dengan JOIN tabel pasien
    $role = $_SERVER['HTTP_X_USER_ROLE'] ?? '';
    $userId = $_SERVER['HTTP_X_USER_ID'] ?? 0;

    $query = "SELECT p.*, ps.nama_lengkap as nama_pasien 
              FROM pendaftaran p 
              JOIN pasien ps ON p.id_pasien = ps.id_pasien ";
    
    if ($role === 'pasien') {
        $query .= " WHERE ps.id_user = :userId ";
    }
    $query .= " ORDER BY p.id_pendaftaran DESC";

    $stmt = $conn->prepare($query);
    if ($role === 'pasien') $stmt->bindValue(':userId', $userId);
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}