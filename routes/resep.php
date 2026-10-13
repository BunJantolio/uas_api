<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-User-Role, X-User-Id");

$host = "localhost";
$username = "root";
$password = "";
$dbname = "uasdb"; // SESUAIKAN DENGAN NAMA DATABASE ANDA

try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $username,
        $password
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'GET') {

    try {
        $idPemeriksaan = $_GET['id_pemeriksaan'] ?? null;

        if ($idPemeriksaan) {
            // Ambil resep berdasarkan id_pemeriksaan
            $stmt = $conn->prepare("
                SELECT *
                FROM resep_obat
                WHERE id_pemeriksaan = :id
                ORDER BY id_resep DESC
            ");
            $stmt->execute([':id' => $idPemeriksaan]);

        } else {
            // Ambil semua resep
            $stmt = $conn->prepare("
                SELECT *
                FROM resep_obat
                ORDER BY id_resep DESC
            ");
            $stmt->execute();
        }

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }

} elseif ($method == 'POST') {

    $data = json_decode(file_get_contents("php://input"), true);

    if (!empty($data['nama_obat'])) {

        try {
            $sql = "INSERT INTO resep_obat
                    (id_pemeriksaan, nama_obat, dosis, frekuensi, jumlah, keterangan)
                    VALUES
                    (:id_p, :nama, :dosis, :freq, :jml, :ket)";

            $stmt = $conn->prepare($sql);

            $stmt->execute([
                ':id_p'  => $data['id_pemeriksaan'],
                ':nama'  => $data['nama_obat'],
                ':dosis' => $data['dosis'],
                ':freq'  => $data['frekuensi'],
                ':jml'   => $data['jumlah'],
                ':ket'   => $data['keterangan'] ?? ''
            ]);

            echo json_encode([
                "message" => "Resep berhasil ditambahkan"
            ]);

        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode([
                "error" => $e->getMessage()
            ]);
        }

    } else {

        http_response_code(400);
        echo json_encode([
            "error" => "Nama obat tidak boleh kosong"
        ]);
    }

} elseif ($method == 'OPTIONS') {

    http_response_code(200);
    exit();

} else {

    http_response_code(405);
    echo json_encode([
        "error" => "Method tidak diizinkan"
    ]);
}
?>