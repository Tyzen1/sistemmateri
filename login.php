<?php
header('Content-Type: application/json');
require 'koneksi.php';

// Terima data dari JavaScript (Username, Password, DAN Role)
 $data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['username']) || !isset($data['password']) || !isset($data['role'])) {
    echo json_encode(["status" => "error", "message" => "Data login tidak lengkap"]);
    exit;
}

 $username = $conn->real_escape_string($data['username']);
 $password = $conn->real_escape_string($data['password']);
 $role = $conn->real_escape_string($data['role']); // Role yang dipilih user (Mahasiswa/Dosen)

// PERBAIKAN: Query mencocokan Username, Password, DAN Role sekaligus
 $sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password' AND role = '$role'";
 $result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Jika ketiganya cocok baru bisa masuk
    $user = $result->fetch_assoc();
    
    // Pindah ke Database 2 (biodata_akademik)
    if (!$conn->select_db('biodata_akademik')) {
        echo json_encode(["status" => "error", "message" => "Gagal mengakses database biodata"]);
        exit;
    }

    $sql2 = "SELECT * FROM profile WHERE user_id = " . $user['id'];
    $res2 = $conn->query($sql2);

    if ($res2 && $res2->num_rows > 0) {
        $profile = $res2->fetch_assoc();
    } else {
        $profile = [
            'avatar_url' => 'https://picsum.photos/seed/user/200/200.jpg',
            'nama_institusi' => 'Universitas Teknologi Digital',
            'sesi_belajar' => 'Ganjil 2023/2024',
            'pembimbing_akademik' => '-'
        ];
    }

    // Gabungkan Data
    $user['avatar'] = $profile['avatar_url'];
    $user['institusi'] = $profile['nama_institusi'];
    $user['sesi'] = $profile['sesi_belajar'];
    $user['pembimbing'] = $profile['pembimbing_akademik'];

    echo json_encode(["status" => "success", "data" => $user]);

} else {
    // Jika salah satu tidak cocok (Misal Login sebagai Mahasiswa tapi pakai akun Dosen)
    echo json_encode(["status" => "error", "message" => "Username, Kata Sandi, atau Peran tidak cocok."]);
}
?>