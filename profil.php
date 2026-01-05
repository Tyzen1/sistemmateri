<?php
header("Content-Type: application/json");
require_once 'koneksi.php';

// 1. MENGAMBIL DATA (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get') {
    
    if (!isset($_GET['user_id'])) {
        echo json_encode(["status" => "error", "message" => "ID User diperlukan"]);
        exit;
    }

    $user_id = intval($_GET['user_id']);

    // --- AMBIL DATA USER (NAMA, PRODI) ---
    $sqlUser = "SELECT id, username, nama_lengkap, role, prodi FROM users WHERE id = ?";
    $stmtUser = $conn->prepare($sqlUser);
    $stmtUser->bind_param("i", $user_id);
    $stmtUser->execute();
    $resultUser = $stmtUser->get_result();

    if ($resultUser->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "User tidak ditemukan"]);
        exit;
    }
    $userData = $resultUser->fetch_assoc();

    // --- AMBIL DATA PROFIL ---
    if (!$conn->select_db('biodata_akademik')) {
        echo json_encode(["status" => "error", "message" => "Gagal koneksi DB Biodata"]);
        exit;
    }

    $sqlProfile = "SELECT * FROM profile WHERE user_id = ?";
    $stmtProfile = $conn->prepare($sqlProfile);
    $stmtProfile->bind_param("i", $user_id);
    $stmtProfile->execute();
    $resultProfile = $stmtProfile->get_result();
    
    $profileData = [];
    if ($resultProfile->num_rows > 0) {
        $profileData = $resultProfile->fetch_assoc();
    } else {
        // Data Default jika belum ada
        $profileData = [
            'avatar_url' => 'https://picsum.photos/seed/user/200/200.jpg',
            'nama_institusi' => 'Universitas Teknologi Digital',
            'sesi_belajar' => 'Ganjil 2023/2024',
            'pembimbing_akademik' => '-'
        ];
    }

    // --- GABUNGKAN ---
    $finalData = array_merge($userData, $profileData);
    echo json_encode(["status" => "success", "data" => $finalData]);
}

// 2. MENYIMPAN DATA (UPDATE)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'update') {
    
    $input = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($input['user_id'])) {
        echo json_encode(["status" => "error", "message" => "ID User diperlukan"]);
        exit;
    }

    $user_id = intval($input['user_id']);
    
    // --- UPDATE DATABASE 1 (USERS) ---
    $nama_lengkap = isset($input['nama']) ? $input['nama'] : '';
    $prodi = isset($input['prodi']) ? $input['prodi'] : '';

    $sqlUpdateUser = "UPDATE users SET nama_lengkap = ?, prodi = ? WHERE id = ?";
    $stmtUpdateUser = $conn->prepare($sqlUpdateUser);
    $stmtUpdateUser->bind_param("ssi", $nama_lengkap, $prodi, $user_id);
    
    if (!$stmtUpdateUser->execute()) {
        echo json_encode(["status" => "error", "message" => "Gagal Update Users: " . $conn->error]);
        exit;
    }

    // --- UPDATE DATABASE 2 (PROFILE) ---
    if (!$conn->select_db('biodata_akademik')) {
        echo json_encode(["status" => "error", "message" => "Gagal pindah DB Biodata"]);
        exit;
    }

    $avatar = isset($input['avatar']) ? $input['avatar'] : '';
    $institusi = isset($input['institusi']) ? $input['institusi'] : '';
    $sesi = isset($input['sesi']) ? $input['sesi'] : '';
    $pembimbing = isset($input['pembimbing']) ? $input['pembimbing'] : '';

    // Cek apakah data profil sudah ada untuk user ini
    $checkSql = "SELECT user_id FROM profile WHERE user_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $user_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        // UPDATE
        $sqlUpdateProfile = "UPDATE profile SET avatar_url = ?, nama_institusi = ?, sesi_belajar = ?, pembimbing_akademik = ? WHERE user_id = ?";
        $stmtUpdateProfile = $conn->prepare($sqlUpdateProfile);
        $stmtUpdateProfile->bind_param("ssssi", $avatar, $institusi, $sesi, $pembimbing, $user_id);
        
        if (!$stmtUpdateProfile->execute()) {
            echo json_encode(["status" => "error", "message" => "Gagal Update Profile: " . $conn->error]);
            exit;
        }
    } else {
        // INSERT
        $sqlInsertProfile = "INSERT INTO profile (user_id, avatar_url, nama_institusi, sesi_belajar, pembimbing_akademik) VALUES (?, ?, ?, ?, ?)";
        $stmtInsertProfile = $conn->prepare($sqlInsertProfile);
        $stmtInsertProfile->bind_param("issss", $user_id, $avatar, $institusi, $sesi, $pembimbing);
        
        if (!$stmtInsertProfile->execute()) {
            echo json_encode(["status" => "error", "message" => "Gagal Insert Profile: " . $conn->error]);
            exit;
        }
    }

    echo json_encode(["status" => "success", "message" => "Data berhasil disimpan"]);
}
?>