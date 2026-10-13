<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-User-Role, X-User-Id");

$host = "localhost";
$username = "root";
$password = "";
$dbname = "uasdb";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Koneksi Gagal: " . $e->getMessage()]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method tidak diizinkan"]);
    exit();
}

$role = $_SERVER['HTTP_X_USER_ROLE'] ?? '';
$userId = $_SERVER['HTTP_X_USER_ID'] ?? 0;
$idPembayaran = $_GET['id_pembayaran'] ?? null;
$idPendaftaran = $_GET['id_pendaftaran'] ?? null;

try {
    $query = "SELECT b.*, reg.id_pasien, ps.nama_lengkap AS nama_pasien, reg.no_antrian, reg.poli, reg.tanggal_daftar, p.tanggal_periksa
              FROM pembayaran b
              LEFT JOIN pendaftaran reg ON b.id_pendaftaran = reg.id_pendaftaran
              LEFT JOIN pasien ps ON reg.id_pasien = ps.id_pasien
              LEFT JOIN pemeriksaan p ON b.id_pemeriksaan = p.id_pemeriksaan
              WHERE 1=1";

    $params = [];
    if ($idPembayaran) {
        $query .= " AND b.id_pembayaran = :idPembayaran";
        $params[':idPembayaran'] = $idPembayaran;
    }

    if ($idPendaftaran) {
        $query .= " AND b.id_pendaftaran = :idPendaftaran";
        $params[':idPendaftaran'] = $idPendaftaran;
    }

    if ($role === 'pasien') {
        $query .= " AND ps.id_user = :userId";
        $params[':userId'] = $userId;
    }

    $query .= " ORDER BY b.id_pembayaran DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($idPembayaran || $idPendaftaran) {
        if (count($results) === 1) {
            echo json_encode($results[0]);
        } else {
            echo json_encode([]);
        }
    } else {
        echo json_encode($results);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
