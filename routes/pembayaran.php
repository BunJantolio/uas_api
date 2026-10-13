<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
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

if ($method === 'GET') {
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

        echo json_encode($results);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
    exit();
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (empty($data['id_pendaftaran'])) {
        http_response_code(400);
        echo json_encode(["error" => "Field id_pendaftaran wajib diisi"]);
        exit();
    }

    try {
        $idPendaftaran = $data['id_pendaftaran'];
        $biayaPendaftaran = isset($data['biaya_pendaftaran']) ? floatval($data['biaya_pendaftaran']) : 0;
        $biayaPemeriksaan = isset($data['biaya_pemeriksaan']) ? floatval($data['biaya_pemeriksaan']) : 0;
        $biayaObat = isset($data['biaya_obat']) ? floatval($data['biaya_obat']) : 0;
        $jenisPembayaran = $data['jenis_pembayaran'] ?? 'umum';
        $noKlaim = $data['no_klaim'] ?? null;
        $jumlahDibayar = isset($data['jumlah_dibayar']) ? floatval($data['jumlah_dibayar']) : null;
        $status = $data['status'] ?? ($jumlahDibayar !== null && $jumlahDibayar >= ($biayaPendaftaran + $biayaPemeriksaan + $biayaObat) ? 'lunas' : 'belum_bayar');
        $keterangan = $data['keterangan'] ?? null;
        $tanggalBayar = $data['tanggal_bayar'] ?? null;
        $idPemeriksaan = $data['id_pemeriksaan'] ?? null;
        $idAdmin = $data['id_admin'] ?? null;

        if (!$idAdmin) {
            $idAdmin = $conn->query("SELECT id_admin FROM admin LIMIT 1")->fetchColumn();
            if (!$idAdmin) {
                throw new Exception("Data admin tidak ditemukan. Isi tabel admin terlebih dahulu.");
            }
        }

        $invoicePrefix = 'INV';
        $timestamp = time();
        $noInvoice = $invoicePrefix . '-' . $timestamp . '-' . rand(100, 999);

        $sql = "INSERT INTO pembayaran (
                    id_pendaftaran,
                    id_pemeriksaan,
                    id_admin,
                    no_invoice,
                    tanggal_bayar,
                    biaya_pendaftaran,
                    biaya_pemeriksaan,
                    biaya_obat,
                    jenis_pembayaran,
                    no_klaim,
                    jumlah_dibayar,
                    status,
                    keterangan
                ) VALUES (
                    :id_pendaftaran,
                    :id_pemeriksaan,
                    :id_admin,
                    :no_invoice,
                    :tanggal_bayar,
                    :biaya_pendaftaran,
                    :biaya_pemeriksaan,
                    :biaya_obat,
                    :jenis_pembayaran,
                    :no_klaim,
                    :jumlah_dibayar,
                    :status,
                    :keterangan
                )";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id_pendaftaran'    => $idPendaftaran,
            ':id_pemeriksaan'    => $idPemeriksaan,
            ':id_admin'          => $idAdmin,
            ':no_invoice'        => $noInvoice,
            ':tanggal_bayar'     => $tanggalBayar,
            ':biaya_pendaftaran' => $biayaPendaftaran,
            ':biaya_pemeriksaan'=> $biayaPemeriksaan,
            ':biaya_obat'        => $biayaObat,
            ':jenis_pembayaran'  => $jenisPembayaran,
            ':no_klaim'          => $noKlaim,
            ':jumlah_dibayar'    => $jumlahDibayar,
            ':status'            => $status,
            ':keterangan'        => $keterangan
        ]);

        echo json_encode([
            "message" => "Pembayaran berhasil disimpan",
            "id_pembayaran" => $conn->lastInsertId(),
            "no_invoice" => $noInvoice
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Gagal simpan pembayaran: " . $e->getMessage()]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
    exit();
}

http_response_code(405);
echo json_encode(["error" => "Method tidak diizinkan"]);
