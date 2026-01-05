<?php
header("Content-Type: application/json");
require_once 'koneksi.php';

 $data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['username']) || !isset($data['old_password']) || !isset($data['new_password'])) {
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
    exit;
}

 $username = $data['username'];
 $old_pass = $data['old_password'];
 $new_pass = $data['new_password'];

// 1. Cek User & Password Lama (PREPARED STATEMENT)
 $sqlCheck = "SELECT id FROM users WHERE username = ? AND password = ?";
 $stmtCheck = $conn->prepare($sqlCheck);
 $stmtCheck->bind_param("ss", $username, $old_pass);
 $stmtCheck->execute();
 $resultCheck = $stmtCheck->get_result();

if ($resultCheck->num_rows > 0) {
    // 2. Update Password Baru (PREPARED STATEMENT)
    $sqlUpdate = "UPDATE users SET password = ? WHERE username = ?";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->bind_param("ss", $new_pass, $username);
    
    if ($stmtUpdate->execute()) {
        echo json_encode(["status" => "success", "message" => "Kata sandi berhasil diubah!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal mengupdate database"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Username atau kata sandi lama salah."]);
}
?>