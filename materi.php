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
    $sesi = $_GET['sesi'] ?? ''; 

    $sql = "SELECT * FROM materials WHERE 1=1";
    
    // Filter Prodi
    if ($prodi != '') { $sql .= " AND prodi LIKE ?"; }
    // Filter Semester
    if ($semester != '') { $sql .= " AND semester LIKE ?"; }
    // Filter Sesi
    if ($sesi != '') { $sql .= " AND sesi LIKE ?"; }
    // Filter Bab/Sub
    if ($bab != '') { $sql .= " AND bab LIKE ?"; }
    if ($sub != '') { $sql .= " AND subbab LIKE ?"; }

    // Bind Params
    $types = "";
    $params = [];
    
    if ($prodi != '') { $types .= "s"; $params[] = "%$prodi%"; }
    if ($semester != '') { $types .= "s"; $params[] = "%$semester%"; }
    if ($sesi != '') { $types .= "s"; $params[] = "%$sesi%"; }
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

// 2. TAMBAH MATERI
elseif ($method === 'POST' && $action === 'add') {
    $rawInput = file_get_contents("php://input");
    if(!$rawInput) { echo json_encode(["status" => "error", "message" => "No Input"]); exit; }
    $data = json_decode($rawInput, true);
    
    $prodi = isset($data['prodi']) ? $data['prodi'] : '';
    $semester = isset($data['semester']) ? $data['semester'] : '';
    $sesi = isset($data['sesi']) ? $data['sesi'] : '';
    $bab = isset($data['bab']) ? $data['bab'] : '';
    $subbab = isset($data['subbab']) ? $data['subbab'] : '';
    $file = isset($data['file']) ? $data['file'] : '';
    $author = isset($data['author']) ? $data['author'] : '';

    // PERBAIKAN: Jumlah parameter bind harus sesuai (7 placeholder)
    $sql = "INSERT INTO materials (prodi, semester, sesi, bab, subbab, file_url, author_name) VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    // PERBAIKAN: bind_param tipe string 'sssssss' (7 string)
    $stmt->bind_param("sssssss", 
        $prodi, 
        $semester, 
        $sesi, 
        $bab, 
        $subbab, 
        $file, 
        $author
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
    
    // PERBAIKAN: Ambil author dari JSON body yang dikirim JS
    $author = isset($data['author']) ? $data['author'] : '';
    
    $checkSql = "SELECT id FROM materials WHERE id = ? AND author_name = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("is", $data['id'], $author);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        $sql = "DELETE FROM materials WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $data['id']);
        $stmt->execute();
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Akses Ditolak"]);
    }
}

// 4. EDIT MATERI
elseif ($method === 'POST' && $action === 'edit') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    // PERBAIKAN: Ambil author
    $author = isset($data['author']) ? $data['author'] : '';

    $checkSql = "SELECT id FROM materials WHERE id = ? AND author_name = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("is", $data['id'], $author);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        // PERBAIKAN: Jumlah parameter bind (6 update + 1 where = 7)
        $sql = "UPDATE materials SET prodi=?, semester=?, sesi=?, bab=?, subbab=?, file_url=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        // PERBAIKAN: Tipe data 'ssssssi' (6 string, 1 integer untuk ID)
        $stmt->bind_param("ssssssi", 
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
        echo json_encode(["status" => "error", "message" => "Akses Ditolak"]);
    }
}
?>
