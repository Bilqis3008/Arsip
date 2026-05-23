<?php
require_once 'config/db.php';
echo "BIDANG LIST:\n";
$stmt = $pdo->query("SELECT * FROM bidang");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['id_bidang'] . " | NAME: " . $row['nama_bidang'] . "\n";
}
echo "\nUSERS LIST:\n";
$stmt = $pdo->query("SELECT nip, nama, role, id_bidang, id_seksi FROM users");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "NIP: " . $row['nip'] . " | NAME: " . $row['nama'] . " | ROLE: " . $row['role'] . " | BIDANG: " . $row['id_bidang'] . " | SEKSI: " . $row['id_seksi'] . "\n";
}
