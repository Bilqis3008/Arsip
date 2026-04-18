<?php
require_once 'c:/laragon/www/Arsip/config/db.php';

$nip = '12345678';
$nama = 'Admin Sekretariat';
$email = 'sekretariat@kemendikbud.go.id';
$password = 'admin123';
$role = 'sekretariat';

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO users (nip, nama, email, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$nip, $nama, $email, $hashed_password, $role]);
    echo "Akun Admin Sekretariat berhasil dibuat!\n";
    echo "NIP: $nip\n";
    echo "Password: $password\n";
} catch (\PDOException $e) {
    if ($e->getCode() == 23000) {
        echo "Error: Akun dengan NIP atau Email tersebut sudah ada.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
