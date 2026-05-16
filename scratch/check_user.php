<?php
require_once 'config/db.php';
$stmt = $pdo->query("SELECT nip, nama, role, id_bidang FROM users WHERE nama LIKE '%Bilqis%'");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res, JSON_PRETTY_PRINT);
?>
