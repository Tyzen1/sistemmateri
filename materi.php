<?php
header('Content-Type: application/json');
require 'koneksi.php';

 $method = $_SERVER['REQUEST_METHOD'];
 $action = $_GET['action'] ?? '';

// 1. AMBIL MATERI
if ($method === 'GET' && $action === 'get') {
    $prodi = $_GET['prodi'] ?? '';
    $semester = $_GET['semester'] ?? '';
    $bab = $_GET['bab'] ?? '';
    $sub = $_GET['sub'] ?? '';
    
    // Parameter tambahan untuk Mahasiswa (Filter Sesi) dan Dosen (Filter Author)
    $sesi = $_GET['sesi'] ?? ''; 
    $author = $_GET['author'] ?? '';

    $sql = "SELECT * FROM materials WHERE 1=1";
    
    // Filter Prodi
    if ($prodi != '') { $sql .= " AND prodi LIKE ?"; }
    
    // Filter Semester (Single Select, tapi disimpan string di DB, tapi pencarian pakai LIKE)
    if ($semester != '') { $sql .= " AND semester LIKE ?"; }

    // FILTER SESI KHUSUS UNTUK MAHASISWA (Hanya lihat sesinya sendiri)
    if ($sesi != '') {
        $sql .= " AND sesi LIKE ?";
    }
    
    // Filter Bab
    if ($bab != '') { $sql .= " AND bab LIKE ?"; }
    if ($sub != '') { $sql .= " AND subbab LIKE ?"; }

    // Filter Author (Dosen)
    if ($author != '') {
        $sql .= " AND author_name = ?";
    }

    // Bind params dinamis
    $types = "";
    $params = [];
    
    if ($prodi != '') { $types .= "s"; $params[] = "%$prodi%"; }
    if ($semester != '') { $types .= "s"; $params[] = "%$semester%"; }
    if ($sesi != '') { $types .= "s"; $params[] = "%$sesi%"; } // Filter Sesi
    if ($author != '') { $types .= "s"; $params[] = $author; }
    if ($bab != '') { $types .= "s"; $params[] = "%$bab%"; }
    if ($sub != '') { $types .= "s"; $params[] = "%$sub%"; }

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode($data);
}

// 2. TAMBAH MATERI (POST)
elseif ($method === 'POST' && $action === 'add') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Kolom sesi harus ada di SQL
    $sql = "INSERT INTO materials (prodi, semester, sesi, bab, subbab, file_url, author_name) VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    // Sesuaikan bind_param: "sissssss"
    // s (String) untuk prodi, semester (jika string), sesi, bab, subbab, file, author.
    // Catatan: Jika DB Anda 'semester' adalah INT, dan value dari HTML string "1",
    // PHP biasanya bisa mengubah otomatis "1" ke 1 saat bind string.
    // Tapi jika Anda mengubah DB semester menjadi VARCHAR untuk multi-select, gunakan s.
    $stmt->bind_param("sissssss", 
        $data['prodi'], 
        $data['semester'], 
        $data['sesi'], // <--- KUNCI: Pastikan ini ada
        $data['bab'], 
        $data['subbab'], 
        $data['file'], 
        $data['author']
    );
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
}

// 3. HAPUS MATERI
elseif ($method === 'POST' && $action === 'delete') {
    $data = json_decode(file_get_contents("php://input"), true);
    // Keamanan: Cek pemilik
    $checkSql = "SELECT id FROM materials WHERE id = ? AND author_name = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("is", $data['id'], $data['author']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        $sql = "DELETE FROM materials WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $data['id']);
        $stmt->execute();
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Anda tidak punya akses menghapus materi ini!"]);
    }
}

// 4. EDIT MATERI
elseif ($method === 'POST' && $action === 'edit') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Keamanan: Cek pemilik
    $checkSql = "SELECT id FROM materials WHERE id = ? AND author_name = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("is", $data['id'], $data['author']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        // Update (Sesi String, Semester Int)
        $sql = "UPDATE materials SET prodi=?, semester=?, sesi=?, bab=?, subbab=?, file_url=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sissssi", 
            $data['prodi'], 
            $data['semester'], 
            $data['sesi'], 
            $data['bab'], 
            $data['subbab'], 
            $data['file'], 
            $data['id']
        );
        $stmt->execute();
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Anda tidak punya akses mengedit materi ini!"]);
    }
}
?>