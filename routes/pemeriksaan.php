<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

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
$role = $_SERVER['HTTP_X_USER_ROLE'] ?? '';
$userId = $_SERVER['HTTP_X_USER_ID'] ?? 0;

if ($method == 'GET') {
    try {
        if ($role == 'pasien') {
            // Jika pasien, hanya ambil hasil miliknya sendiri lewat join pendaftaran
            $sql = "SELECT p.* FROM pemeriksaan p 
                    JOIN pendaftaran reg ON p.id_pendaftaran = reg.id_pendaftaran 
                    WHERE reg.id_pasien = :userId 
                    ORDER BY p.tanggal_periksa DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':userId' => $userId]);
        } else {
            // Jika admin/dokter, ambil semua
            $stmt = $conn->prepare("SELECT * FROM pemeriksaan ORDER BY id_pemeriksaan DESC");
            $stmt->execute();
        }
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
} elseif ($method == 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id_pendaftaran'])) {
        http_response_code(400);
        echo json_encode(["error" => "ID Pendaftaran tidak ditemukan"]);
        exit();
    }

    try {
        $conn->beginTransaction();

        // 1. AMBIL ID_DOKTER DARI TABEL PENDAFTARAN
        // Karena di tabel pemeriksaan ada kolom id_dokter, kita ambil dari data pendaftaran
        $stmtPendaftaran = $conn->prepare("SELECT id_dokter FROM pendaftaran WHERE id_pendaftaran = ?");
        $stmtPendaftaran->execute([$data['id_pendaftaran']]);
        $pendaftaran = $stmtPendaftaran->fetch(PDO::FETCH_ASSOC);

        if (!$pendaftaran) {
            throw new Exception("Data Pendaftaran tidak ditemukan");
        }

        $id_dokter = $pendaftaran['id_dokter'];

        // 2. FORMAT TANGGAL KE DATETIME (YYYY-MM-DD HH:MM:SS)
        $tanggal_input = $data['tanggal_periksa'];
        if (strpos($tanggal_input, '/') !== false) {
            // Jika Android mengirim format dd/MM/yyyy
            $dateParts = explode('/', $tanggal_input);
            $formattedDate = "$dateParts[2]-$dateParts[1]-$dateParts[0] " . date('H:i:s');
        } else {
            // Fallback ke waktu sekarang
            $formattedDate = date('Y-m-d H:i:s');
        }

        // 3. INSERT KE TABEL PEMERIKSAAN (Menyesuaikan kolom di gambar)
        $sql = "INSERT INTO pemeriksaan (
                    id_pendaftaran, 
                    id_dokter,
                    tanggal_periksa, 
                    tekanan_darah, 
                    suhu_tubuh, 
                    berat_badan, 
                    tinggi_badan, 
                    catatan_klinis, 
                    diagnosa, 
                    tindakan,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'selesai')";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $data['id_pendaftaran'],
            $id_dokter,
            $formattedDate,
            $data['tekanan_darah'],
            $data['suhu_tubuh'],
            $data['berat_badan'],
            $data['tinggi_badan'],
            $data['catatan_klinis'],
            $data['diagnosa'],
            $data['tindakan']
        ]);

        // 4. UPDATE STATUS PENDAFTARAN MENJADI 'selesai'
        $updateSql = "UPDATE pendaftaran SET status = 'selesai' WHERE id_pendaftaran = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([$data['id_pendaftaran']]);

        $conn->commit();
        echo json_encode(["message" => "Pemeriksaan berhasil disimpan"]);
    } catch (Exception $e) {
        if ($conn->inTransaction())
            $conn->rollBack();
        http_response_code(500);
        echo json_encode(["error" => "Gagal simpan: " . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
}
