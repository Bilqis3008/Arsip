<?php
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nip = filter_input(INPUT_POST, 'nip', FILTER_SANITIZE_STRING);
    $nama = filter_input(INPUT_POST, 'nama', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $no_hp = filter_input(INPUT_POST, 'no_hp', FILTER_SANITIZE_STRING);
    $jabatan = filter_input(INPUT_POST, 'jabatan', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    $id_bidang = filter_input(INPUT_POST, 'id_bidang', FILTER_VALIDATE_INT);
    $id_seksi = filter_input(INPUT_POST, 'id_seksi', FILTER_VALIDATE_INT);

    // Hash Password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (nip, nama, email, no_hp, jabatan, password, id_bidang, id_seksi, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $nip, 
            $nama, 
            $email, 
            $no_hp, 
            $jabatan, 
            $hashed_password, 
            $id_bidang, 
            $id_seksi ?: null, 
            'staff'
        ]);

        echo "<script>
                alert('RegistrasiBerhasil! Silakan login.');
                window.location.href = 'login.php';
              </script>";
        exit;
    } catch (\PDOException $e) {
        if ($e->getCode() == 23000) {
            // Error Duplicate NIP/Email
            echo "<script>
                    alert('NIP atau Email sudah terdaftar.');
                    window.history.back();
                  </script>";
        } else {
            die("Error: " . $e->getMessage());
        }
    }
}
?>
